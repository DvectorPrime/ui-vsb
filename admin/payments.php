<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('admin');

$pageTitle = 'Payments & Financial Audit Log';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Payments &amp; Financial Audit Log</h1>
    <p class="muted">Audit trail of all successful, failed, and refunded workshop transactions.</p>
  </div>
</div>

<div class="card stat mb" style="max-width: 320px;">
  <b id="totalSuccessRev">—</b>
  <span>Total Successful Revenue Collected</span>
</div>

<!-- Filter Bar -->
<div class="card mb" style="padding: 14px 20px;">
  <form id="payFilterForm" class="grid g4" style="align-items: flex-end;" onsubmit="return false;">
    <div class="field" style="margin: 0;">
      <label for="payStatus">Payment Status</label>
      <select id="payStatus">
        <option value="">All Statuses</option>
        <option value="success">Success</option>
        <option value="failed">Failed Attempts</option>
        <option value="refunded">Refunded</option>
      </select>
    </div>

    <div class="field" style="margin: 0;">
      <label for="payDateFrom">From Date</label>
      <input id="payDateFrom" type="date">
    </div>

    <div class="field" style="margin: 0;">
      <label for="payDateTo">To Date</label>
      <input id="payDateTo" type="date">
    </div>

    <div style="display:flex; gap:8px;">
      <button type="button" class="btn gold" id="payFilterBtn" style="flex:1;">Filter</button>
      <button type="button" class="btn ghost" id="payResetBtn">Reset</button>
    </div>
  </form>
</div>

<div id="paymentsTableContainer">
  <div class="loading"><span class="spinner"></span> Loading payment logs…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('paymentsTableContainer');
  const totalRevEl = document.getElementById('totalSuccessRev');
  const statusSel = document.getElementById('payStatus');
  const dateFrom = document.getElementById('payDateFrom');
  const dateTo = document.getElementById('payDateTo');

  async function loadPayments() {
    container.innerHTML = '<div class="loading"><span class="spinner"></span> Loading payment logs…</div>';

    const params = {};
    if (statusSel.value) params.status = statusSel.value;
    if (dateFrom.value) params.date_from = dateFrom.value;
    if (dateTo.value) params.date_to = dateTo.value;

    const res = await VSB.api('api/admin/payments.php', { query: params });

    if (res.ok && res.data) {
      totalRevEl.textContent = VSB.formatMoney(res.data.total_success ?? 0);
      const list = res.data.payments || [];

      if (list.length === 0) {
        container.innerHTML = '<div class="card empty"><p>No payment records match the specified criteria.</p></div>';
        return;
      }

      container.innerHTML = `
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Transaction Ref</th>
                <th>Booking Ref</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Card Last 4</th>
                <th>Status</th>
                <th>Failure Reason</th>
                <th>Processed At</th>
              </tr>
            </thead>
            <tbody>
              ${list.map(p => `
                <tr>
                  <td><b>${VSB.escapeHtml(p.transaction_ref)}</b></td>
                  <td>
                    <a href="<?php echo BASE_URL; ?>admin/bookings.php?q=${encodeURIComponent(p.booking_ref)}">
                      ${VSB.escapeHtml(p.booking_ref)}
                    </a>
                  </td>
                  <td>${VSB.escapeHtml(p.customer_name)}</td>
                  <td><b>${VSB.formatMoney(p.amount)}</b></td>
                  <td>•••• ${VSB.escapeHtml(p.card_last4 || '—')}</td>
                  <td>${VSB.statusBadge(p.status)}</td>
                  <td class="muted">${VSB.escapeHtml(p.failure_reason || '—')}</td>
                  <td>${VSB.formatDateTime(p.paid_at)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;
    } else {
      container.innerHTML = '<p class="error-text">Failed to load payment transactions.</p>';
    }
  }

  document.getElementById('payFilterBtn').onclick = loadPayments;
  document.getElementById('payResetBtn').onclick = () => {
    statusSel.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    loadPayments();
  };

  loadPayments();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
