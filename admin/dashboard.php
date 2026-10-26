<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('admin');

$pageTitle = 'Workshop Administration Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Workshop Administration Dashboard</h1>
    <p class="muted">Maintenance Directorate · University of Ibadan Workshop Overview</p>
  </div>
  <div class="actions">
    <a class="btn gold sm" href="<?php echo BASE_URL; ?>admin/bookings.php">Manage Bookings</a>
    <a class="btn ghost sm" href="<?php echo BASE_URL; ?>admin/services.php">Service Catalogue</a>
  </div>
</div>

<div class="grid g4 mb" id="adminKPIs">
  <div class="card stat"><b id="kpiToday">—</b><span>Bookings Today</span></div>
  <div class="card stat"><b id="kpiPending">—</b><span>Awaiting Approval</span></div>
  <div class="card stat"><b id="kpiRevenue">—</b><span>Total Revenue Collected</span></div>
  <div class="card stat"><b id="kpiServices">—</b><span>Active Services</span></div>
</div>

<div class="card">
  <div class="page-head" style="margin-bottom: 12px;">
    <h3>Recent Workshop Bookings</h3>
    <a class="btn ghost sm" href="<?php echo BASE_URL; ?>admin/bookings.php">View All Bookings →</a>
  </div>
  <div id="recentBookingsList">
    <div class="loading"><span class="spinner"></span> Loading recent bookings…</div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const recentBox = document.getElementById('recentBookingsList');

  try {
    const res = await VSB.api('api/admin/dashboard.php');
    if (res.ok && res.data) {
      const d = res.data;
      document.getElementById('kpiToday').textContent = d.bookings_today ?? 0;
      document.getElementById('kpiPending').textContent = d.pending_approvals ?? 0;
      document.getElementById('kpiRevenue').textContent = VSB.formatMoney(d.total_revenue ?? 0);
      document.getElementById('kpiServices').textContent = d.active_services ?? 0;

      const recent = d.recent_bookings || [];
      if (recent.length > 0) {
        recentBox.innerHTML = `
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Ref</th>
                  <th>Customer</th>
                  <th>Vehicle</th>
                  <th>Service</th>
                  <th>Date &amp; Time</th>
                  <th>Booking Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                ${recent.map(b => `
                  <tr>
                    <td><b>${VSB.escapeHtml(b.booking_ref)}</b></td>
                    <td>
                      ${VSB.escapeHtml(b.customer?.full_name || 'Customer')}<br>
                      <small class="muted">${VSB.escapeHtml(b.customer?.email || '')}</small>
                    </td>
                    <td>
                      ${VSB.escapeHtml(b.vehicle?.reg_number || '—')}<br>
                      <small class="muted">${VSB.escapeHtml(b.vehicle?.make || '')} ${VSB.escapeHtml(b.vehicle?.model || '')}</small>
                    </td>
                    <td>${VSB.escapeHtml(b.service?.name || '—')}</td>
                    <td>${VSB.formatDate(b.booking_date)}<br><small class="muted">${VSB.escapeHtml(b.slot_time)}</small></td>
                    <td>${VSB.statusBadge(b.booking_status)}</td>
                    <td>
                      <a class="btn ghost sm" href="<?php echo BASE_URL; ?>admin/bookings.php?q=${encodeURIComponent(b.booking_ref)}">Manage</a>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      } else {
        recentBox.innerHTML = '<div class="empty"><p class="muted">No recent bookings recorded.</p></div>';
      }
    } else {
      recentBox.innerHTML = '<p class="error-text">Failed to load admin metrics.</p>';
    }
  } catch (err) {
    recentBox.innerHTML = '<p class="error-text">Network error loading dashboard metrics.</p>';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
