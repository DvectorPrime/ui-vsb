<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('admin');

$pageTitle = 'Registered Customers Overview';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Registered Customers Overview</h1>
    <p class="muted">Customer accounts, registered vehicles, and appointment histories.</p>
  </div>
</div>

<div class="card mb" style="padding: 14px 20px;">
  <div class="field" style="margin: 0; max-width: 480px;">
    <label for="custSearch">Search Customers</label>
    <input id="custSearch" type="search" placeholder="Search by customer name, email, or phone…">
  </div>
</div>

<div id="customersTableContainer">
  <div class="loading"><span class="spinner"></span> Loading customer accounts…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('customersTableContainer');
  const searchInput = document.getElementById('custSearch');
  let searchTimer = null;

  async function loadCustomers(query = '') {
    container.innerHTML = '<div class="loading"><span class="spinner"></span> Loading customers…</div>';

    const params = {};
    if (query) params.q = query;

    const res = await VSB.api('api/admin/customers.php', { query: params });

    if (res.ok && Array.isArray(res.data)) {
      if (res.data.length === 0) {
        container.innerHTML = '<div class="card empty"><p>No customer records found matching your search.</p></div>';
        return;
      }

      container.innerHTML = `
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Customer Name</th>
                <th>Contact Info</th>
                <th>Vehicles</th>
                <th>Bookings Count</th>
                <th>Registered On</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              ${res.data.map(c => {
                const vList = c.vehicles || [];
                return `
                  <tr>
                    <td><b>${VSB.escapeHtml(c.full_name)}</b></td>
                    <td>
                      ${VSB.escapeHtml(c.email)}<br>
                      <small class="muted">${VSB.escapeHtml(c.phone)}</small>
                    </td>
                    <td>
                      <span class="badge badge-approved">${c.vehicle_count} vehicle(s)</span>
                      ${vList.length > 0 ? `
                        <div class="muted" style="font-size:.82rem; margin-top:4px;">
                          ${vList.map(v => `${VSB.escapeHtml(v.reg_number)} (${VSB.escapeHtml(v.make)} ${VSB.escapeHtml(v.model)})`).join(', ')}
                        </div>
                      ` : ''}
                    </td>
                    <td><b>${c.booking_count}</b></td>
                    <td>${VSB.formatDate(c.created_at)}</td>
                    <td>
                      <a class="btn ghost sm" href="<?php echo BASE_URL; ?>admin/bookings.php?q=${encodeURIComponent(c.email)}">
                        View Bookings
                      </a>
                    </td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        </div>
      `;
    } else {
      container.innerHTML = '<p class="error-text">Failed to retrieve customer accounts.</p>';
    }
  }

  // 300ms debounced search
  searchInput.oninput = (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      loadCustomers(e.target.value.trim());
    }, 300);
  };

  loadCustomers();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
