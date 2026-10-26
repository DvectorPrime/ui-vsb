<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('customer');

$filterRef = trim((string) ($_GET['ref'] ?? ''));

$pageTitle = 'Track My Bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Track My Bookings</h1>
    <p class="muted">Live progress tracking and audit history for all workshop appointments.</p>
  </div>
  <a class="btn gold" href="<?php echo BASE_URL; ?>customer/book.php">+ Book Another Service</a>
</div>

<div id="trackContainer">
  <div class="loading"><span class="spinner"></span> Loading your bookings…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('trackContainer');
  let autoRefreshTimer = null;

  async function loadBookings(isSilent = false) {
    if (!isSilent) {
      container.innerHTML = '<div class="loading"><span class="spinner"></span> Loading your bookings…</div>';
    }

    const res = await VSB.api('api/bookings.php');

    if (res.ok && Array.isArray(res.data) && res.data.length > 0) {
      container.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
          <span class="muted" style="font-size:.85rem;">Showing ${res.data.length} appointment record(s) · Auto-refreshes every 15s</span>
          <button type="button" class="btn ghost sm" id="manualRefreshBtn">↻ Refresh Now</button>
        </div>
        <div class="grid" style="gap: 16px;">
          ${res.data.map(b => {
            const hasPaid = Boolean(b.payment && b.payment.status === 'success');
            return `
              <div class="card" id="card-${VSB.escapeHtml(b.booking_ref)}">
                <div class="page-head" style="margin-bottom: 8px;">
                  <div>
                    <h3 style="margin:0;">${VSB.escapeHtml(b.service?.name || 'Service')} · ${VSB.escapeHtml(b.vehicle?.reg_number || '')}</h3>
                    <span class="muted" style="font-size:.85rem;">
                      Ref: <b>${VSB.escapeHtml(b.booking_ref)}</b> · ${VSB.formatDate(b.booking_date)} at ${VSB.escapeHtml(b.slot_time)}
                    </span>
                  </div>
                  <div>
                    ${VSB.statusBadge(b.booking_status)}
                    ${hasPaid ? VSB.statusBadge('success') : ''}
                  </div>
                </div>

                <!-- 5-Step Stepper -->
                ${VSB.trackerHTML(b.service_status, b.booking_status)}

                <div class="page-head" style="margin: 14px 0 0; padding-top: 10px; border-top: 1px solid var(--line);">
                  <span><b>Amount: ${VSB.formatMoney(b.amount)}</b></span>
                  <div class="actions">
                    <button type="button" class="btn ghost sm" data-audit-ref="${VSB.escapeHtml(b.booking_ref)}">
                      📜 View Audit Timeline
                    </button>
                    ${b.booking_status === 'pending_payment' ? `
                      <a class="btn gold sm" href="<?php echo BASE_URL; ?>customer/payment.php?booking_id=${b.booking_id}">Pay Now</a>
                    ` : ''}
                    ${hasPaid ? `
                      <a class="btn ghost sm" href="<?php echo BASE_URL; ?>customer/confirmation.php?ref=${encodeURIComponent(b.booking_ref)}">View Receipt</a>
                    ` : ''}
                  </div>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      `;

      document.getElementById('manualRefreshBtn').onclick = () => {
        loadBookings(false);
        VSB.toast('Bookings refreshed', 'ok');
      };

      // Bind audit timeline modals
      container.querySelectorAll('[data-audit-ref]').forEach(btn => {
        btn.onclick = async () => {
          const ref = btn.dataset.auditRef;
          VSB.modal('<div class="loading"><span class="spinner"></span> Loading audit history…</div>', async (root, close) => {
            const detailRes = await VSB.api(`api/bookings.php?ref=${encodeURIComponent(ref)}`);
            if (detailRes.ok && detailRes.data) {
              const item = detailRes.data;
              const history = item.history || [];
              root.querySelector('.modal').innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:baseline; border-bottom:1px solid var(--line); padding-bottom:10px; margin-bottom:14px;">
                  <h3 style="margin:0;">Audit Trail: ${VSB.escapeHtml(item.booking_ref)}</h3>
                  <button type="button" class="btn ghost sm" data-close>✕</button>
                </div>
                <p class="muted" style="margin:0 0 14px; font-size:.85rem;">
                  ${VSB.escapeHtml(item.service?.name)} for ${VSB.escapeHtml(item.vehicle?.reg_number)} (${VSB.escapeHtml(item.vehicle?.make)} ${VSB.escapeHtml(item.vehicle?.model)})
                </p>

                ${history.length > 0 ? `
                  <ul class="timeline">
                    ${history.map(h => `
                      <li class="timeline-item">
                        <b>${VSB.escapeHtml(h.status || 'Updated')}</b>
                        <div>${VSB.escapeHtml(h.note || 'Status updated')}</div>
                        <small>${VSB.formatDateTime(h.changed_at)}</small>
                      </li>
                    `).join('')}
                  </ul>
                ` : '<p class="muted">No history entries logged yet.</p>'}

                <div class="modal-actions">
                  <button type="button" class="btn ghost sm" data-close>Close</button>
                </div>
              `;
              root.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', close));
            } else {
              root.querySelector('.modal').innerHTML = `
                <p class="error-text">Failed to load booking timeline.</p>
                <div class="modal-actions"><button type="button" class="btn ghost sm" data-close>Close</button></div>
              `;
              root.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', close));
            }
          });
        };
      });

      // Highlight target ref if provided in query string
      const targetSlug = '<?php echo htmlspecialchars($filterRef, ENT_QUOTES, 'UTF-8'); ?>';
      if (targetSlug) {
        const targetCard = document.getElementById('card-' + targetSlug);
        if (targetCard) {
          targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
          targetCard.style.outline = '3px solid var(--gold)';
        }
      }
    } else {
      container.innerHTML = `
        <div class="card empty">
          <p>You have no workshop bookings yet.</p>
          <a class="btn gold sm mt" href="<?php echo BASE_URL; ?>customer/book.php">+ Book your first service</a>
        </div>
      `;
    }
  }

  // 15-second auto refresh
  autoRefreshTimer = setInterval(() => { loadBookings(true); }, 15000);

  loadBookings(false);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
