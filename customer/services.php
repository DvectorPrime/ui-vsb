<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Workshop Services';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Workshop Service Catalogue</h1>
    <p class="muted">Fixed-price routine maintenance, servicing and repair packages.</p>
  </div>
</div>

<div class="grid g3" id="servicesGrid">
  <div class="loading"><span class="spinner"></span> Loading services catalogue…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const grid = document.getElementById('servicesGrid');
  const isCustomer = <?php echo ($userRole === 'customer') ? 'true' : 'false'; ?>;
  const isGuest = <?php echo ($userRole === 'guest') ? 'true' : 'false'; ?>;

  try {
    const res = await VSB.api('api/services.php');
    if (res.ok && Array.isArray(res.data) && res.data.length > 0) {
      grid.innerHTML = res.data.map(s => {
        let actionBtn = '';
        if (isCustomer) {
          actionBtn = `<a class="btn sm block" href="<?php echo BASE_URL; ?>customer/book.php?service_id=${s.service_id}">Book this service</a>`;
        } else if (isGuest) {
          actionBtn = `<a class="btn ghost sm block" href="<?php echo BASE_URL; ?>login.php">Log in to book</a>`;
        } else {
          actionBtn = `<span class="badge badge-approved">Active Service</span>`;
        }

        return `
          <div class="card service-card">
            <div>
              <h3>${VSB.escapeHtml(s.name)}</h3>
              <p class="muted">${VSB.escapeHtml(s.description || 'Professional maintenance service')}</p>
            </div>
            <div>
              <div class="price">${VSB.formatMoney(s.price)}</div>
              <small class="muted">Estimated duration: ${s.duration_mins} mins</small>
              <div class="mt">
                ${actionBtn}
              </div>
            </div>
          </div>
        `;
      }).join('');
    } else {
      grid.innerHTML = '<div class="card empty"><p>No active workshop services found.</p></div>';
    }
  } catch (err) {
    grid.innerHTML = '<div class="card empty"><p class="error-text">Failed to load workshop services.</p></div>';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
