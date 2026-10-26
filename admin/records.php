<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('admin');

$pageTitle = 'Search Vehicle Service Records';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Vehicle Service History Records</h1>
    <p class="muted">Search comprehensive historical vehicle maintenance logs across the university fleet.</p>
  </div>
</div>

<!-- Search & Filter Card -->
<div class="card mb" style="padding: 16px 20px;">
  <div class="grid g4" style="align-items: flex-end;">
    <div class="field" style="margin: 0;">
      <label for="recQ">Search Records</label>
      <input id="recQ" type="search" placeholder="Plate, make, model, customer, ref…">
    </div>

    <div class="field" style="margin: 0;">
      <label for="recService">Service</label>
      <select id="recService">
        <option value="">All Services</option>
      </select>
    </div>

    <div class="field" style="margin: 0;">
      <label for="recStatus">Service Status</label>
      <select id="recStatus">
        <option value="">All Workflow Statuses</option>
        <option value="not_started">Not Started</option>
        <option value="received">Received</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="ready_for_pickup">Ready for Pickup</option>
      </select>
    </div>

    <div style="display:flex; gap:8px;">
      <button type="button" class="btn gold" id="recSearchBtn" style="flex:1;">Search</button>
      <button type="button" class="btn ghost" id="recResetBtn">Reset</button>
    </div>
  </div>
</div>

<div id="recordsTableContainer">
  <div class="loading"><span class="spinner"></span> Loading historical records…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const container = document.getElementById('recordsTableContainer');
  const qInput = document.getElementById('recQ');
  const srvSelect = document.getElementById('recService');
  const statusSelect = document.getElementById('recStatus');
  let searchTimer = null;
  let inFlightReqId = 0;

  // Populate services dropdown
  try {
    const sRes = await VSB.api('api/services.php');
    if (sRes.ok && Array.isArray(sRes.data)) {
      sRes.data.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.service_id;
        opt.textContent = s.name;
        srvSelect.appendChild(opt);
      });
    }
  } catch (e) {}

  async function loadRecords() {
    const myReq = ++inFlightReqId;
    container.innerHTML = '<div class="loading"><span class="spinner"></span> Searching service records…</div>';

    const params = {};
    if (qInput.value.trim()) params.q = qInput.value.trim();
    if (srvSelect.value) params.service_id = srvSelect.value;
    if (statusSelect.value) params.service_status = statusSelect.value;

    const res = await VSB.api('api/admin/records.php', { query: params });

    if (myReq !== inFlightReqId) return; // Stale request protection

    if (res.ok && Array.isArray(res.data)) {
      if (res.data.length === 0) {
        container.innerHTML = '<div class="card empty"><p>No vehicle service records match your search criteria.</p></div>';
        return;
      }

      container.innerHTML = `
        <div style="margin-bottom:8px;" class="muted">Found ${res.data.length} matching record(s)</div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Booking Ref</th>
                <th>Vehicle</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Date Completed/Scheduled</th>
                <th>Service Status</th>
                <th>Booking Status</th>
              </tr>
            </thead>
            <tbody>
              ${res.data.map(r => `
                <tr>
                  <td>
                    <b>${VSB.escapeHtml(r.booking_ref)}</b>
                  </td>
                  <td>
                    <b>${VSB.escapeHtml(r.vehicle?.reg_number || '—')}</b><br>
                    <small class="muted">${VSB.escapeHtml(r.vehicle?.make || '')} ${VSB.escapeHtml(r.vehicle?.model || '')} (${r.vehicle?.year || ''})</small>
                  </td>
                  <td>
                    ${VSB.escapeHtml(r.customer?.full_name || 'Customer')}<br>
                    <small class="muted">${VSB.escapeHtml(r.customer?.email || '')}</small>
                  </td>
                  <td>
                    ${VSB.escapeHtml(r.service?.name || '—')}<br>
                    <b>${VSB.formatMoney(r.amount)}</b>
                  </td>
                  <td>
                    ${VSB.formatDate(r.booking_date)}<br>
                    <small class="muted">${VSB.escapeHtml(r.slot_time)}</small>
                  </td>
                  <td>${VSB.statusBadge(r.service_status)}</td>
                  <td>${VSB.statusBadge(r.booking_status)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;
    } else {
      container.innerHTML = '<p class="error-text">Failed to query historical records.</p>';
    }
  }

  // 300ms live debounced search
  qInput.oninput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadRecords, 300);
  };
  srvSelect.onchange = loadRecords;
  statusSelect.onchange = loadRecords;
  document.getElementById('recSearchBtn').onclick = loadRecords;
  document.getElementById('recResetBtn').onclick = () => {
    qInput.value = '';
    srvSelect.value = '';
    statusSelect.value = '';
    loadRecords();
  };

  loadRecords();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
