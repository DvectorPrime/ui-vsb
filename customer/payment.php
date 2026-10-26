<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('customer');

$bookingId = filter_var($_GET['booking_id'] ?? null, FILTER_VALIDATE_INT);
if (!$bookingId) {
    header('Location: ' . BASE_URL . 'customer/dashboard.php');
    exit;
}

$pageTitle = 'Complete Payment';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="banner-test-mode">
  ⚡ TEST MODE — Simulated payment gateway. No real money will be charged.
</div>

<div class="page-head">
  <div>
    <h1>Complete Workshop Payment</h1>
    <p class="muted">Hold reserved for 30 minutes. Enter simulated card details below.</p>
  </div>
</div>

<div id="paymentContainer">
  <div class="loading"><span class="spinner"></span> Loading booking details…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const container = document.getElementById('paymentContainer');
  const targetBookingId = <?php echo (int) $bookingId; ?>;

  // Retrieve customer bookings to locate this booking
  const res = await VSB.api('api/bookings.php');
  const bookings = (res.ok && Array.isArray(res.data)) ? res.data : [];
  const booking = bookings.find(b => b.booking_id === targetBookingId);

  if (!booking) {
    container.innerHTML = `
      <div class="card empty">
        <h3>Booking Not Found</h3>
        <p class="muted">Could not find booking record #${targetBookingId} for your account.</p>
        <a class="btn gold sm mt" href="<?php echo BASE_URL; ?>customer/dashboard.php">Back to Dashboard</a>
      </div>
    `;
    return;
  }

  // Check booking lifecycle status
  if (booking.booking_status !== 'pending_payment') {
    let msg = 'This booking is not available for payment.';
    let btnText = 'View My Bookings';
    let btnUrl = 'customer/track.php';

    if (booking.booking_status === 'pending_approval' || booking.booking_status === 'approved') {
      msg = 'Payment for this booking has already been completed!';
      btnText = 'View Confirmation Receipt';
      btnUrl = `customer/confirmation.php?ref=${encodeURIComponent(booking.booking_ref)}`;
    } else if (booking.booking_status === 'cancelled') {
      msg = 'This booking hold has expired. Please create a new booking.';
      btnText = 'Book Another Slot';
      btnUrl = 'customer/book.php';
    }

    container.innerHTML = `
      <div class="card empty">
        <h3>${VSB.escapeHtml(msg)}</h3>
        <p class="muted">Current Status: ${VSB.statusBadge(booking.booking_status)}</p>
        <a class="btn gold sm mt" href="<?php echo BASE_URL; ?>${btnUrl}">${btnText}</a>
      </div>
    `;
    return;
  }

  // Calculate 30-min hold expiry
  const createdAtMs = new Date(booking.created_at.replace(' ', 'T')).getTime();
  const expiresAtMs = createdAtMs + (30 * 60 * 1000);

  container.innerHTML = `
    <div class="split">
      <form id="paymentForm" class="card" novalidate>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:14px; border-bottom:1px solid var(--line); padding-bottom:10px;">
          <h3 style="margin:0;">Card Payment Details</h3>
          <span id="countdownTimer" class="countdown">Calculating hold…</span>
        </div>

        <div class="field">
          <label for="cardNumber">Card Number (16 Digits)</label>
          <input id="cardNumber" name="card_number" type="text" inputmode="numeric" placeholder="4242 4242 4242 4242" maxlength="19" required autocomplete="off">
        </div>

        <div class="field">
          <label for="cardName">Cardholder Full Name</label>
          <input id="cardName" name="card_name" type="text" placeholder="e.g. ADA OBI" required autocomplete="off">
        </div>

        <div class="row row-tight">
          <div class="field">
            <label for="cardExpiry">Expiry Date (MM/YY)</label>
            <input id="cardExpiry" name="expiry" type="text" placeholder="MM/YY" maxlength="5" inputmode="numeric" required>
          </div>
          <div class="field">
            <label for="cardCvv">CVV (3 Digits)</label>
            <input id="cardCvv" name="cvv" type="password" placeholder="123" maxlength="3" inputmode="numeric" required>
          </div>
        </div>

        <div id="paymentAlert" class="alert alert-error" style="display:none;"></div>

        <button type="submit" class="btn gold block" id="payBtn" style="margin-top:6px;">
          Pay ${VSB.formatMoney(booking.amount)}
        </button>

        <!-- Test card helper pills -->
        <div class="demo">
          <b>Test Card Simulator:</b> Click below to auto-fill test scenarios
          <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
            <button type="button" class="btn ghost sm" id="cardSuccess">✓ Success (4242)</button>
            <button type="button" class="btn ghost sm" id="cardDecline">✗ Declined (0002)</button>
            <button type="button" class="btn ghost sm" id="cardFunds">✗ Insufficient Funds (9995)</button>
            <button type="button" class="btn ghost sm" id="cardExpired">✗ Expired Card</button>
          </div>
        </div>
      </form>

      <!-- Order Summary Sidebar -->
      <aside class="card summary">
        <h3>Order Summary</h3>
        <dl>
          <dt>Booking Reference</dt>
          <dd><span class="ref" style="font-size:1.15rem; padding:4px 10px;">${VSB.escapeHtml(booking.booking_ref)}</span></dd>

          <dt>Vehicle</dt>
          <dd>${VSB.escapeHtml(booking.vehicle?.reg_number)} (${VSB.escapeHtml(booking.vehicle?.make)} ${VSB.escapeHtml(booking.vehicle?.model)})</dd>

          <dt>Service Package</dt>
          <dd>${VSB.escapeHtml(booking.service?.name)}</dd>

          <dt>Appointment Date &amp; Time</dt>
          <dd>${VSB.formatDate(booking.booking_date)} at ${VSB.escapeHtml(booking.slot_time)}</dd>
        </dl>

        <div class="total">
          <span>Amount to Pay</span>
          <span>${VSB.formatMoney(booking.amount)}</span>
        </div>
      </aside>
    </div>
  `;

  const numInput = document.getElementById('cardNumber');
  const nameInput = document.getElementById('cardName');
  const expInput = document.getElementById('cardExpiry');
  const cvvInput = document.getElementById('cardCvv');
  const alertBox = document.getElementById('paymentAlert');
  const payBtn = document.getElementById('payBtn');
  const countdownEl = document.getElementById('countdownTimer');

  // Auto-format card groupings
  numInput.oninput = (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 16);
    e.target.value = v.replace(/(.{4})/g, '$1 ').trim();
  };

  // Auto-insert slash for expiry MM/YY
  expInput.oninput = (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 4);
    if (v.length > 2) {
      v = v.slice(0, 2) + '/' + v.slice(2);
    }
    e.target.value = v;
  };

  // Test card helpers
  document.getElementById('cardSuccess').onclick = () => {
    numInput.value = '4242 4242 4242 4242';
    nameInput.value = 'ADA OBI';
    expInput.value = '12/28';
    cvvInput.value = '123';
  };
  document.getElementById('cardDecline').onclick = () => {
    numInput.value = '4000 0000 0000 0002';
    nameInput.value = 'TEST DECLINE';
    expInput.value = '12/28';
    cvvInput.value = '123';
  };
  document.getElementById('cardFunds').onclick = () => {
    numInput.value = '4000 0000 0000 9995';
    nameInput.value = 'NO FUNDS';
    expInput.value = '11/27';
    cvvInput.value = '456';
  };
  document.getElementById('cardExpired').onclick = () => {
    numInput.value = '4242 4242 4242 4242';
    nameInput.value = 'EXPIRED CARD';
    expInput.value = '05/20';
    cvvInput.value = '123';
  };

  // 30-Minute Hold Countdown Timer
  const timerInterval = setInterval(() => {
    const remainingMs = expiresAtMs - Date.now();
    if (remainingMs <= 0) {
      clearInterval(timerInterval);
      countdownEl.textContent = 'Hold expired!';
      countdownEl.style.color = 'var(--bad)';
      payBtn.disabled = true;
      alertBox.textContent = 'Booking hold has expired. Please return to the booking page to reserve a new bay.';
      alertBox.style.display = 'block';
    } else {
      const mins = Math.floor(remainingMs / 60000);
      const secs = Math.floor((remainingMs % 60000) / 1000);
      countdownEl.textContent = `Hold expires in ${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
  }, 1000);

  // Form submission
  document.getElementById('paymentForm').onsubmit = async function (e) {
    e.preventDefault();
    alertBox.style.display = 'none';

    const cardPayload = {
      booking_id: targetBookingId,
      card_name: nameInput.value.trim(),
      card_number: numInput.value.replace(/\s+|-/g, ''),
      expiry: expInput.value.trim(),
      cvv: cvvInput.value.trim()
    };

    // Client-side validation checks
    if (!cardPayload.card_name) {
      alertBox.textContent = 'Please enter the name on the card.';
      alertBox.style.display = 'block';
      return;
    }
    const luhnErr = VSB.validate.luhn(cardPayload.card_number);
    if (luhnErr) {
      alertBox.textContent = luhnErr;
      alertBox.style.display = 'block';
      return;
    }
    const expErr = VSB.validate.expiry(cardPayload.expiry);
    if (expErr) {
      alertBox.textContent = expErr;
      alertBox.style.display = 'block';
      return;
    }
    const cvvErr = VSB.validate.cvv(cardPayload.cvv);
    if (cvvErr) {
      alertBox.textContent = cvvErr;
      alertBox.style.display = 'block';
      return;
    }

    VSB.setLoading(payBtn, true, 'Contacting payment gateway…');

    const payRes = await VSB.api('api/payment.php', {
      method: 'POST',
      body: cardPayload
    });

    VSB.setLoading(payBtn, false);

    if (payRes.ok && payRes.success) {
      clearInterval(timerInterval);
      VSB.toast('Payment successful!', 'ok');
      window.location.href = VSB.url(`customer/confirmation.php?ref=${encodeURIComponent(booking.booking_ref)}`);
    } else if (payRes.status === 422) {
      // Gateway decline scenario (card_declined, insufficient_funds, expired_card)
      alertBox.textContent = payRes.message || 'Your card was declined by the simulated gateway. Please try another card.';
      alertBox.style.display = 'block';
    } else {
      alertBox.textContent = payRes.message || 'Payment processing failed.';
      alertBox.style.display = 'block';
    }
  };
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
