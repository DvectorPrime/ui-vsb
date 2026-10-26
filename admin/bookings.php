<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('admin');

$filterQuery  = trim((string) ($_GET['q'] ?? ''));
$filterStatus = trim((string) ($_GET['status'] ?? ''));

$pageTitle = 'Workshop Bookings Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Workshop Bookings Management</h1>
    <p class="muted">Review pending approvals, assign bays, and progress workshop service statuses.</p>
  </div>
</div>

<!-- Filter Bar -->
<div class="card mb" style="padding: 16px 20px;">
  <form id="filterForm" class="grid g4" style="align-items: flex-end;" onsubmit="return false;">
    <div class="field" style="margin: 0;">
      <label for="filterQ">Search Query</label>
      <input id="filterQ" name="q" type="text" placeholder="Ref, plate, customer, email…" value="<?php echo htmlspecialchars($filterQuery); ?>">
    </div>

    <div class="field" style="margin: 0;">
      <label for="filterStatus">Booking Status</label>
      <select id="filterStatus" name="status">
        <option value="">All Statuses</option>
        <option value="pending_approval" <?php echo ($filterStatus === 'pending_approval') ? 'selected' : ''; ?>>Pending Approval</option>
        <option value="approved" <?php echo ($filterStatus === 'approved') ? 'selected' : ''; ?>>Approved</option>
        <option value="pending_payment" <?php echo ($filterStatus === 'pending_payment') ? 'selected' : ''; ?>>Pending Payment</option>
        <option value="rejected" <?php echo ($filterStatus === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
        <option value="cancelled" <?php echo ($filterStatus === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
      </select>
    </div>

    <div class="field" style="margin: 0;">
      <label for="filterDateFrom">Date From</label>
      <input id="filterDateFrom" name="date_from" type="date">
    </div>

    <div style="display: flex; gap: 8px;">
      <button type="button" class="btn gold" id="applyFilterBtn" style="flex:1;">Filter</button>
      <button type="button" class="btn ghost" id="resetFilterBtn">Reset</button>
    </div>
  </form>
</div>

<div id="bookingsTableContainer">
  <div class="loading"><span class="spinner"></span> Loading bookings…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('bookingsTableContainer');
  const qInput = document.getElementById('filterQ');
  const statusSelect = document.getElementById('filterStatus');
  const dateFromInput = document.getElementById('filterDateFrom');

  async function loadBookings() {
    container.innerHTML = '<div class="loading"><span class="spinner"></span> Loading bookings…</div>';

    const params = {};
    if (qInput.value.trim()) params.q = qInput.value.trim();
    if (statusSelect.value) params.status = statusSelect.value;
    if (dateFromInput.value) params.date_from = dateFromInput.value;

    const res = await VSB.api('api/admin/bookings.php', { query: params });

    if (res.ok && Array.isArray(res.data)) {
      if (res.data.length === 0) {
        container.innerHTML = '<div class="card empty"><p>No bookings match the specified filters.</p></div>';
        return;
      }

      container.innerHTML = `
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Booking Ref</th>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Service &amp; Fee</th>
                <th>Appointment</th>
                <th>Status</th>
                <th>Workflow Actions</th>
              </tr>
            </thead>
            <tbody>
              ${res.data.map(b => {
                const isPending = (b.booking_status === 'pending_approval');
                const isApproved = (b.booking_status === 'approved');

                // Available forward steps for service_status stepper
                const stepperForwardOptions = {
                  'not_started': ['received', 'in_progress'],
                  'received': ['in_progress', 'completed'],
                  'in_progress': ['completed', 'ready_for_pickup'],
                  'completed': ['ready_for_pickup'],
                  'ready_for_pickup': []
                };

                const nextOptions = stepperForwardOptions[b.service_status] || [];

                return `
                  <tr>
                    <td>
                      <b>${VSB.escapeHtml(b.booking_ref)}</b><br>
                      <button type="button" class="btn ghost sm" data-view-ref="${VSB.escapeHtml(b.booking_ref)}" style="margin-top:4px; padding:2px 8px; font-size:.74rem;">
                        View Detail &amp; History
                      </button>
                    </td>
                    <td>
                      <b>${VSB.escapeHtml(b.customer?.full_name || 'Customer')}</b><br>
                      <small class="muted">${VSB.escapeHtml(b.customer?.email || '')} · ${VSB.escapeHtml(b.customer?.phone || '')}</small>
                    </td>
                    <td>
                      <b>${VSB.escapeHtml(b.vehicle?.reg_number || '—')}</b><br>
                      <small class="muted">${VSB.escapeHtml(b.vehicle?.make || '')} ${VSB.escapeHtml(b.vehicle?.model || '')} (${b.vehicle?.year || ''})</small>
                    </td>
                    <td>
                      ${VSB.escapeHtml(b.service?.name || '—')}<br>
                      <b>${VSB.formatMoney(b.amount)}</b>
                    </td>
                    <td>
                      ${VSB.formatDate(b.booking_date)}<br>
                      <small class="muted">${VSB.escapeHtml(b.slot_time)}</small>
                    </td>
                    <td>
                      ${VSB.statusBadge(b.booking_status)}
                      ${isApproved ? `<div style="margin-top:4px;">${VSB.statusBadge(b.service_status)}</div>` : ''}
                    </td>
                    <td>
                      ${isPending ? `
                        <div class="actions">
                          <button type="button" class="btn sm" data-approve-id="${b.booking_id}" style="background:var(--ok);">Approve</button>
                          <button type="button" class="btn danger sm" data-reject-id="${b.booking_id}">Reject</button>
                        </div>
                      ` : ''}

                      ${isApproved && nextOptions.length > 0 ? `
                        <div style="display:flex; gap:4px; align-items:center;">
                          <select data-stepper-id="${b.booking_id}" style="padding:4px 6px; font-size:.8rem; max-width:130px;">
                            ${nextOptions.map(opt => `
                              <option value="${opt}">${opt.replace(/_/g, ' ')}</option>
                            `).join('')}
                          </select>
                          <button type="button" class="btn ghost sm" data-update-stepper="${b.booking_id}">Update</button>
                        </div>
                      ` : ''}

                      ${isApproved && nextOptions.length === 0 ? `
                        <span class="badge badge-ready_for_pickup">Ready for Pickup</span>
                      ` : ''}

                      ${!isPending && !isApproved ? `
                        <span class="muted" style="font-size:.82rem;">No actions</span>
                      ` : ''}
                    </td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        </div>
      `;

      // Bind View detail modals
      container.querySelectorAll('[data-view-ref]').forEach(btn => {
        btn.onclick = () => {
          const ref = btn.dataset.viewRef;
          VSB.modal('<div class="loading"><span class="spinner"></span> Loading booking details…</div>', async (root, close) => {
            const detailRes = await VSB.api(`api/admin/bookings.php?ref=${encodeURIComponent(ref)}`);
            if (detailRes.ok && detailRes.data) {
              const b = detailRes.data;
              const p = b.payment;
              root.querySelector('.modal').innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:baseline; border-bottom:1px solid var(--line); padding-bottom:8px; margin-bottom:12px;">
                  <h3 style="margin:0;">Booking: ${VSB.escapeHtml(b.booking_ref)}</h3>
                  <button type="button" class="btn ghost sm" data-close>✕</button>
                </div>

                <div class="grid g2" style="font-size:.88rem; margin-bottom:14px;">
                  <div>
                    <b>Customer:</b> ${VSB.escapeHtml(b.customer?.full_name)}<br>
                    <span class="muted">${VSB.escapeHtml(b.customer?.email)} · ${VSB.escapeHtml(b.customer?.phone)}</span>
                  </div>
                  <div>
                    <b>Vehicle:</b> ${VSB.escapeHtml(b.vehicle?.reg_number)} (${VSB.escapeHtml(b.vehicle?.make)} ${VSB.escapeHtml(b.vehicle?.model)})<br>
                    <b>Service:</b> ${VSB.escapeHtml(b.service?.name)} (${VSB.formatMoney(b.amount)})
                  </div>
                </div>

                <div style="margin-bottom:14px;">
                  <b>Service Progress Stepper:</b>
                  ${VSB.trackerHTML(b.service_status, b.booking_status)}
                </div>

                ${p ? `
                  <div style="background:#f8fafc; border:1px solid var(--line); border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:.86rem;">
                    <b>Payment Record:</b> ${VSB.statusBadge(p.status)} · Ref: <b>${VSB.escapeHtml(p.transaction_ref)}</b> · Amount: ${VSB.formatMoney(p.amount)} · Paid on: ${VSB.formatDateTime(p.paid_at)}
                  </div>
                ` : '<p class="muted" style="font-size:.84rem;">No successful payment registered.</p>'}

                <b>Audit History:</b>
                <ul class="timeline" style="margin-top:10px;">
                  ${(b.history || []).map(h => `
                    <li class="timeline-item">
                      <b>${VSB.escapeHtml(h.status)}</b> · ${VSB.escapeHtml(h.note || '')}
                      <small>${VSB.formatDateTime(h.changed_at)}</small>
                    </li>
                  `).join('')}
                </ul>

                <div class="modal-actions">
                  <button type="button" class="btn ghost sm" data-close>Close</button>
                </div>
              `;
              root.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', close));
            }
          });
        };
      });

      // Bind Approve button
      container.querySelectorAll('[data-approve-id]').forEach(btn => {
        btn.onclick = async () => {
          const bid = parseInt(btn.dataset.approveId, 10);
          VSB.modal(`
            <h3>Approve Booking</h3>
            <p>Confirm and approve this booking appointment? This reserves the workshop bay.</p>
            <div class="field">
              <label for="approveNote">Approval Note (Optional)</label>
              <input id="approveNote" type="text" placeholder="e.g. Assigned to Bay 1, Tech Johnson">
            </div>
            <div class="modal-actions">
              <button type="button" class="btn ghost sm" data-close>Cancel</button>
              <button type="button" class="btn gold sm" id="confirmApproveBtn">Confirm Approval</button>
            </div>
          `, (root, close) => {
            document.getElementById('confirmApproveBtn').onclick = async () => {
              const note = document.getElementById('approveNote').value.trim();
              close();
              const aRes = await VSB.api('api/admin/bookings.php', {
                method: 'POST',
                body: { action: 'approve', booking_id: bid, note }
              });
              if (aRes.ok && aRes.success) {
                VSB.toast('Booking approved successfully', 'ok');
                loadBookings();
              } else {
                VSB.toast(aRes.message || 'Failed to approve booking', 'err');
              }
            };
          });
        };
      });

      // Bind Reject button (Triggering auto-refund)
      container.querySelectorAll('[data-reject-id]').forEach(btn => {
        btn.onclick = () => {
          const bid = parseInt(btn.dataset.rejectId, 10);
          VSB.modal(`
            <h3>Reject Booking &amp; Issue Refund</h3>
            <p class="error-text">Rejecting will automatically mark the customer's payment as <b>REFUNDED</b> and release the bay.</p>
            <div class="field">
              <label for="rejectNote">Reason for Rejection (Required)</label>
              <input id="rejectNote" type="text" placeholder="e.g. Bay maintenance, Technician unavailable" required>
            </div>
            <div class="modal-actions">
              <button type="button" class="btn ghost sm" data-close>Cancel</button>
              <button type="button" class="btn danger sm" id="confirmRejectBtn">Reject &amp; Refund</button>
            </div>
          `, (root, close) => {
            document.getElementById('confirmRejectBtn').onclick = async () => {
              const note = document.getElementById('rejectNote').value.trim();
              if (!note) {
                alert('Please enter a rejection reason for the audit trail.');
                return;
              }
              close();
              const rRes = await VSB.api('api/admin/bookings.php', {
                method: 'POST',
                body: { action: 'reject', booking_id: bid, note }
              });
              if (rRes.ok && rRes.success) {
                VSB.toast('Booking rejected and payment refunded', 'ok');
                loadBookings();
              } else {
                VSB.toast(rRes.message || 'Failed to reject booking', 'err');
              }
            };
          });
        };
      });

      // Bind Stepper Update button
      container.querySelectorAll('[data-update-stepper]').forEach(btn => {
        btn.onclick = async () => {
          const bid = parseInt(btn.dataset.updateStepper, 10);
          const sel = container.querySelector(`[data-stepper-id="${bid}"]`);
          const targetStatus = sel.value;

          const sRes = await VSB.api('api/admin/status.php', {
            method: 'POST',
            body: { booking_id: bid, service_status: targetStatus }
          });

          if (sRes.ok && sRes.success) {
            VSB.toast(`Service status updated to ${targetStatus.replace(/_/g, ' ')}`, 'ok');
            loadBookings();
          } else {
            VSB.toast(sRes.message || 'Cannot advance status backwards or skip steps', 'err');
          }
        };
      });

    } else {
      container.innerHTML = '<p class="error-text">Failed to retrieve workshop bookings.</p>';
    }
  }

  document.getElementById('applyFilterBtn').onclick = loadBookings;
  document.getElementById('resetFilterBtn').onclick = () => {
    qInput.value = '';
    statusSelect.value = '';
    dateFromInput.value = '';
    loadBookings();
  };

  loadBookings();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
