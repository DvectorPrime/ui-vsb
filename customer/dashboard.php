<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('customer');

$pageTitle = 'Customer Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?></h1>
    <p class="muted">University of Ibadan Maintenance Directorate · Customer Portal</p>
  </div>
  <a class="btn gold" href="<?php echo BASE_URL; ?>customer/book.php">+ Book a Service</a>
</div>

<div class="grid g4 mb" id="dashboardStats">
  <div class="card stat"><b id="statVehicles">—</b><span>Registered Vehicles</span></div>
  <div class="card stat"><b id="statActive">—</b><span>Active Bookings</span></div>
  <div class="card stat"><b id="statCompleted">—</b><span>Completed Services</span></div>
  <div class="card stat"><b id="statPendingPay">—</b><span>Awaiting Payment</span></div>
</div>

<div class="split">
  <div class="card" id="latestBookingCard">
    <h3>Next Appointment</h3>
    <div id="latestBookingContent">
      <div class="loading"><span class="spinner"></span> Loading appointment details…</div>
    </div>
  </div>

  <aside class="card">
    <h3>Quick Actions</h3>
    <div class="grid" style="gap: 10px;">
      <a class="btn ghost" href="<?php echo BASE_URL; ?>customer/vehicles.php">My Vehicles</a>
      <a class="btn ghost" href="<?php echo BASE_URL; ?>customer/services.php">Services &amp; Pricing</a>
      <a class="btn ghost" href="<?php echo BASE_URL; ?>customer/track.php">All Bookings &amp; Tracking</a>
    </div>
  </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const latestBox = document.getElementById('latestBookingContent');

  try {
    const [dashRes, bookingsRes] = await Promise.all([
      VSB.api('api/dashboard.php'),
      VSB.api('api/bookings.php')
    ]);

    const bookings = (bookingsRes.ok && Array.isArray(bookingsRes.data)) ? bookingsRes.data : [];
    const active = bookings.filter(b => !['completed', 'rejected', 'cancelled'].includes(b.booking_status));
    const completed = bookings.filter(b => b.booking_status === 'approved' && b.service_status === 'ready_for_pickup');
    const unpaid = bookings.filter(b => b.booking_status === 'pending_payment');

    // Update KPI cards
    document.getElementById('statVehicles').textContent = dashRes.data?.vehicle_count ?? '0';
    document.getElementById('statActive').textContent = active.length;
    document.getElementById('statCompleted').textContent = completed.length;
    document.getElementById('statPendingPay').textContent = unpaid.length;

    // Render Latest / Next appointment
    const nextBooking = dashRes.data?.latest_booking || active[0] || bookings[0];
    if (nextBooking) {
      latestBox.innerHTML = `
        <div style="margin-bottom: 12px;">
          <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap;">
            <h4>${VSB.escapeHtml(nextBooking.service?.name || 'Service')} · ${VSB.escapeHtml(nextBooking.vehicle?.reg_number || '')}</h4>
            <div>${VSB.statusBadge(nextBooking.booking_status)}</div>
          </div>
          <p class="muted" style="margin: 4px 0 0;">
            ${VSB.formatDate(nextBooking.booking_date)} at ${VSB.escapeHtml(nextBooking.slot_time)} · Ref: <b>${VSB.escapeHtml(nextBooking.booking_ref)}</b>
          </p>
        </div>

        ${VSB.trackerHTML(nextBooking.service_status, nextBooking.booking_status)}

        <div class="actions mt" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
          <div><b>Amount: ${VSB.formatMoney(nextBooking.amount)}</b></div>
          <div class="actions">
            <a class="btn ghost sm" href="<?php echo BASE_URL; ?>customer/track.php?ref=${encodeURIComponent(nextBooking.booking_ref)}">View Tracking Details</a>
            ${nextBooking.booking_status === 'pending_payment' ? `
              <a class="btn gold sm" href="<?php echo BASE_URL; ?>customer/payment.php?booking_id=${nextBooking.booking_id}">Pay Now</a>
            ` : ''}
          </div>
        </div>
      `;
    } else {
      latestBox.innerHTML = `
        <div class="empty">
          <p class="muted">No upcoming workshop appointments.</p>
          <a class="btn gold sm" href="<?php echo BASE_URL; ?>customer/book.php">Book your first service</a>
        </div>
      `;
    }
  } catch (err) {
    latestBox.innerHTML = '<p class="error-text">Failed to load dashboard data.</p>';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
