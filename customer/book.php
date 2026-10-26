<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('customer');

$preSelectedService = filter_var($_GET['service_id'] ?? null, FILTER_VALIDATE_INT) ?: '';
$preSelectedVehicle = filter_var($_GET['vehicle_id'] ?? null, FILTER_VALIDATE_INT) ?: '';

$pageTitle = 'Book Service Appointment';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Book a Service Appointment</h1>
    <p class="muted">Real-time workshop bay reservation powered by asynchronous Fetch API.</p>
  </div>
</div>

<div id="bookingContainer">
  <div class="loading"><span class="spinner"></span> Loading booking options…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const container = document.getElementById('bookingContainer');

  // Fetch initial data: vehicles and active services
  const [vehiclesRes, servicesRes] = await Promise.all([
    VSB.api('api/vehicles.php'),
    VSB.api('api/services.php')
  ]);

  const vehicles = (vehiclesRes.ok && Array.isArray(vehiclesRes.data)) ? vehiclesRes.data : [];
  const services = (servicesRes.ok && Array.isArray(servicesRes.data)) ? servicesRes.data : [];

  if (vehicles.length === 0) {
    container.innerHTML = `
      <div class="card empty">
        <h3>No Vehicles Registered</h3>
        <p class="muted">You must register at least one vehicle before booking a workshop appointment.</p>
        <a class="btn gold sm mt" href="<?php echo BASE_URL; ?>customer/vehicles.php">+ Register a Vehicle</a>
      </div>
    `;
    return;
  }

  // Calculate min & max dates
  const today = new Date();
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, '0');
  const dd = String(today.getDate()).padStart(2, '0');
  const minDate = `${yyyy}-${mm}-${dd}`;

  const maxDateObj = new Date();
  maxDateObj.setDate(today.getDate() + 60);
  const maxDate = `${maxDateObj.getFullYear()}-${String(maxDateObj.getMonth() + 1).padStart(2, '0')}-${String(maxDateObj.getDate()).padStart(2, '0')}`;

  // Default selection state
  const state = {
    vehicleId: parseInt('<?php echo $preSelectedVehicle; ?>', 10) || vehicles[0].vehicle_id,
    serviceId: parseInt('<?php echo $preSelectedService; ?>', 10) || (services[0]?.service_id || null),
    date: minDate,
    slotTime: '',
    notes: ''
  };

  container.innerHTML = `
    <div class="split">
      <form id="bookingForm" class="card" novalidate>
        <!-- Step 1: Vehicle -->
        <div class="field">
          <label for="vehicleSelect">1. Select Registered Vehicle</label>
          <select id="vehicleSelect" name="vehicle_id" required>
            ${vehicles.map(v => `
              <option value="${v.vehicle_id}" ${v.vehicle_id === state.vehicleId ? 'selected' : ''}>
                ${VSB.escapeHtml(v.reg_number)} — ${v.year} ${VSB.escapeHtml(v.make)} ${VSB.escapeHtml(v.model)}
              </option>
            `).join('')}
          </select>
        </div>

        <!-- Step 2: Service -->
        <div class="field">
          <label>2. Choose Workshop Service</label>
          <div class="grid g2" id="servicePickers" style="gap: 10px;">
            ${services.map(s => `
              <label class="pick ${s.service_id === state.serviceId ? 'sel' : ''}">
                <input type="radio" name="service_id" value="${s.service_id}" ${s.service_id === state.serviceId ? 'checked' : ''}>
                <b>${VSB.escapeHtml(s.name)}</b>
                <span class="muted" style="font-size:.82rem;">${s.duration_mins} mins</span> · 
                <b style="display:inline; color:var(--navy);">${VSB.formatMoney(s.price)}</b>
              </label>
            `).join('')}
          </div>
        </div>

        <!-- Step 3: Date -->
        <div class="field">
          <label for="bookingDate">3. Preferred Appointment Date</label>
          <input type="date" id="bookingDate" name="date" min="${minDate}" max="${maxDate}" value="${state.date}" required>
          <div class="hint">The workshop operates Monday to Saturday. (Closed on Sundays).</div>
        </div>

        <!-- Step 4: Live Fetch Slots -->
        <div class="field">
          <div style="display: flex; justify-content: space-between; align-items: baseline;">
            <label>4. Real-Time Bay Availability (Fetch API)</label>
            <span id="lastCheckedTime" class="muted" style="font-size: .78rem;"></span>
          </div>
          <div id="slotsContainer" style="margin-top: 6px;">
            <div class="loading"><span class="spinner"></span> Checking availability…</div>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-top:6px;">
            <button type="button" class="btn ghost sm" id="refreshSlotsBtn" style="padding:4px 10px; font-size:.78rem;">↻ Refresh Slots</button>
            <span class="muted" style="font-size:.75rem;">Slots reflect real-time 30-min holds &amp; bookings</span>
          </div>
        </div>

        <!-- Notes -->
        <div class="field">
          <label for="bookingNotes">Additional Notes / Fault Description (Optional)</label>
          <textarea id="bookingNotes" name="notes" rows="2" placeholder="e.g. Unusual noise in braking or engine rattling…"></textarea>
        </div>

        <div id="formAlert" class="alert alert-error" style="display:none;"></div>

        <button type="submit" class="btn gold block" id="submitBookingBtn" disabled>Confirm &amp; Proceed to Payment</button>
      </form>

      <!-- Live Order Summary Sidebar -->
      <aside class="card summary">
        <h3>Booking Summary</h3>
        <div id="summaryContent"></div>
      </aside>
    </div>
  `;

  const vehicleSelect = document.getElementById('vehicleSelect');
  const dateInput = document.getElementById('bookingDate');
  const slotsBox = document.getElementById('slotsContainer');
  const summaryBox = document.getElementById('summaryContent');
  const submitBtn = document.getElementById('submitBookingBtn');
  const alertBox = document.getElementById('formAlert');
  const lastCheckedLabel = document.getElementById('lastCheckedTime');
  const refreshBtn = document.getElementById('refreshSlotsBtn');

  function updateSummary() {
    const chosenVeh = vehicles.find(v => v.vehicle_id === state.vehicleId);
    const chosenSvc = services.find(s => s.service_id === state.serviceId);

    summaryBox.innerHTML = `
      <dl>
        <dt>Selected Vehicle</dt>
        <dd>${chosenVeh ? `${VSB.escapeHtml(chosenVeh.reg_number)} (${VSB.escapeHtml(chosenVeh.make)} ${VSB.escapeHtml(chosenVeh.model)})` : '—'}</dd>
        
        <dt>Workshop Service</dt>
        <dd>${chosenSvc ? VSB.escapeHtml(chosenSvc.name) : '—'}</dd>

        <dt>Appointment Date</dt>
        <dd>${state.date ? VSB.formatDate(state.date) : '—'}</dd>

        <dt>Time Slot</dt>
        <dd>${state.slotTime ? `${state.slotTime}` : '<span class="muted">Not selected</span>'}</dd>
      </dl>

      <div class="total">
        <span>Total Amount</span>
        <span>${chosenSvc ? VSB.formatMoney(chosenSvc.price) : '—'}</span>
      </div>
    `;

    const isValid = state.vehicleId && state.serviceId && state.date && state.slotTime;
    submitBtn.disabled = !isValid;
  }

  // Asynchronous Fetch operation for slot availability
  let inFlightReqId = 0;
  async function checkSlots() {
    state.slotTime = '';
    updateSummary();

    if (!state.date || !state.serviceId) {
      slotsBox.innerHTML = '<p class="muted">Please select a service and date.</p>';
      return;
    }

    const currentReq = ++inFlightReqId;
    slotsBox.innerHTML = '<div class="loading"><span class="spinner"></span> Checking bay availability…</div>';

    const res = await VSB.api(`api/slots.php?date=${encodeURIComponent(state.date)}&service_id=${encodeURIComponent(state.serviceId)}`);

    // Guard against race condition: discard if date or service changed while request was in-flight
    if (currentReq !== inFlightReqId) return;

    const now = new Date();
    lastCheckedLabel.textContent = `Last checked at ${now.toLocaleTimeString()}`;

    if (res.ok && Array.isArray(res.data) && res.data.length > 0) {
      slotsBox.innerHTML = `
        <div class="slots slot-grid">
          ${res.data.map(slot => {
            const isAvail = slot.available;
            const remaining = slot.remaining;
            return `
              <button type="button" 
                      class="slot slot-btn" 
                      data-time="${slot.time}" 
                      ${isAvail ? '' : 'disabled aria-disabled="true"'}
                      aria-pressed="false">
                <b>${slot.time}</b>
                <small>${isAvail ? `${remaining} bay${remaining > 1 ? 's' : ''} left` : 'Full'}</small>
              </button>
            `;
          }).join('')}
        </div>
      `;

      // Bind slot buttons
      slotsBox.querySelectorAll('.slot-btn:not([disabled])').forEach(btn => {
        btn.onclick = () => {
          slotsBox.querySelectorAll('.slot-btn').forEach(b => {
            b.classList.remove('sel', 'selected');
            b.setAttribute('aria-pressed', 'false');
          });
          btn.classList.add('sel', 'selected');
          btn.setAttribute('aria-pressed', 'true');
          state.slotTime = btn.dataset.time;
          updateSummary();
        };
      });
    } else {
      slotsBox.innerHTML = `<p class="error-text">${VSB.escapeHtml(res.message || 'No slots available on this date.')}</p>`;
    }
  }

  // Event handlers
  vehicleSelect.onchange = (e) => {
    state.vehicleId = parseInt(e.target.value, 10);
    updateSummary();
  };

  document.querySelectorAll('input[name="service_id"]').forEach(radio => {
    radio.onchange = (e) => {
      state.serviceId = parseInt(e.target.value, 10);
      document.querySelectorAll('.pick').forEach(p => {
        p.classList.toggle('sel', p.querySelector('input').checked);
      });
      checkSlots();
    };
  });

  dateInput.onchange = (e) => {
    state.date = e.target.value;
    checkSlots();
  };

  document.getElementById('bookingNotes').oninput = (e) => {
    state.notes = e.target.value;
  };

  refreshBtn.onclick = () => {
    checkSlots();
    VSB.toast('Refreshed slot availability', 'ok');
  };

  // Submit booking hold
  document.getElementById('bookingForm').onsubmit = async function (e) {
    e.preventDefault();
    alertBox.style.display = 'none';

    if (!state.vehicleId || !state.serviceId || !state.date || !state.slotTime) {
      alertBox.textContent = 'Please complete all steps (Vehicle, Service, Date, Time slot).';
      alertBox.style.display = 'block';
      return;
    }

    VSB.setLoading(submitBtn, true, 'Holding workshop bay…');

    const res = await VSB.api('api/book.php', {
      method: 'POST',
      body: {
        vehicle_id: state.vehicleId,
        service_id: state.serviceId,
        date: state.date,
        slot_time: state.slotTime,
        notes: state.notes
      }
    });

    VSB.setLoading(submitBtn, false);

    if (res.ok && res.success) {
      VSB.toast('Slot reserved! Proceeding to payment…', 'ok');
      window.location.href = VSB.url(`customer/payment.php?booking_id=${res.data.booking_id}`);
    } else if (res.status === 409) {
      // Concurrency race condition: slot was taken by another user
      alertBox.textContent = res.message || 'Sorry, that slot was just taken by another customer. Please choose another slot.';
      alertBox.style.display = 'block';
      checkSlots(); // Auto-refresh slot grid
    } else {
      alertBox.textContent = res.message || 'Failed to place booking hold.';
      alertBox.style.display = 'block';
    }
  };

  // Initial load
  updateSummary();
  checkSlots();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
