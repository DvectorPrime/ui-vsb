<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('customer');

$bookingRef = trim((string) ($_GET['ref'] ?? ''));
if ($bookingRef === '') {
    header('Location: ' . BASE_URL . 'customer/dashboard.php');
    exit;
}

$pageTitle = 'Booking & Payment Confirmation';
require_once __DIR__ . '/../includes/header.php';
?>

<div id="confirmationContainer">
  <div class="loading"><span class="spinner"></span> Loading receipt details…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const container = document.getElementById('confirmationContainer');
  const ref = '<?php echo htmlspecialchars($bookingRef, ENT_QUOTES, 'UTF-8'); ?>';

  const res = await VSB.api(`api/bookings.php?ref=${encodeURIComponent(ref)}`);

  if (!res.ok || !res.data) {
    container.innerHTML = `
      <div class="card empty">
        <h3>Receipt Not Found</h3>
        <p class="muted">Could not find booking reference ${VSB.escapeHtml(ref)}.</p>
        <a class="btn gold sm mt" href="<?php echo BASE_URL; ?>customer/dashboard.php">Back to Dashboard</a>
      </div>
    `;
    return;
  }

  const b = res.data;
  const p = b.payment || {};

  container.innerHTML = `
    <div class="card success-box">
      <div class="tick">✓</div>
      <h2>Payment Confirmed!</h2>
      <p class="muted">Your appointment has been reserved and is awaiting workshop approval.</p>

      <div style="margin: 16px 0;">
        <span class="ref" id="refCopyBox" title="Click to copy">${VSB.escapeHtml(b.booking_ref)}</span>
        <div style="margin-top:6px;">
          <button type="button" class="btn ghost sm" id="copyBtn">📋 Copy Reference</button>
        </div>
      </div>

      <div class="summary" style="text-align: left; background:#fafbfc; padding:16px; border-radius:8px; border:1px solid var(--line);">
        <dl>
          <dt>Transaction Reference</dt>
          <dd><b>${VSB.escapeHtml(p.transaction_ref || 'N/A')}</b></dd>

          <dt>Vehicle</dt>
          <dd>${VSB.escapeHtml(b.vehicle?.reg_number)} (${VSB.escapeHtml(b.vehicle?.make)} ${VSB.escapeHtml(b.vehicle?.model)})</dd>

          <dt>Workshop Service</dt>
          <dd>${VSB.escapeHtml(b.service?.name)}</dd>

          <dt>Scheduled Appointment</dt>
          <dd>${VSB.formatDate(b.booking_date)} at ${VSB.escapeHtml(b.slot_time)}</dd>

          <dt>Payment Method</dt>
          <dd>Card ending in •••• ${VSB.escapeHtml(p.card_last4 || '4242')}</dd>

          <dt>Payment Date</dt>
          <dd>${VSB.formatDateTime(p.paid_at || b.created_at)}</dd>
        </dl>

        <div class="total">
          <span>Amount Paid</span>
          <span style="color:var(--ok);">${VSB.formatMoney(p.amount || b.amount)}</span>
        </div>
      </div>

      <div class="actions no-print mt" style="justify-content: center; gap: 10px;">
        <button type="button" class="btn ghost" onclick="window.print()">🖨 Print Receipt</button>
        <a class="btn gold" href="<?php echo BASE_URL; ?>customer/track.php?ref=${encodeURIComponent(b.booking_ref)}">Track Vehicle Progress →</a>
      </div>
    </div>
  `;

  document.getElementById('copyBtn').onclick = () => {
    navigator.clipboard.writeText(b.booking_ref).then(() => {
      VSB.toast('Booking reference copied to clipboard!', 'ok');
    });
  };
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
