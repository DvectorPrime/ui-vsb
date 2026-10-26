<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <h1>Book your vehicle service online.</h1>
  <p>The University of Ibadan Maintenance Directorate workshop — register your vehicle, pick a service and time, pay securely, and track progress without calling or visiting.</p>
  <div class="mt actions">
    <?php if ($userRole === 'guest'): ?>
      <a class="btn gold" href="<?php echo BASE_URL; ?>register.php">Create account</a>
      <a class="btn ghost" href="<?php echo BASE_URL; ?>login.php">Log in</a>
    <?php elseif ($userRole === 'admin'): ?>
      <a class="btn gold" href="<?php echo BASE_URL; ?>admin/dashboard.php">Open admin dashboard</a>
    <?php else: ?>
      <a class="btn gold" href="<?php echo BASE_URL; ?>customer/book.php">Book a service</a>
      <a class="btn ghost" href="<?php echo BASE_URL; ?>customer/dashboard.php">My dashboard</a>
    <?php endif; ?>
  </div>
</section>

<div class="steps">
  <div class="step">
    <b>Register</b>
    <p class="muted">Create an account and register your vehicle details.</p>
  </div>
  <div class="step">
    <b>Choose</b>
    <p class="muted">Pick a service and check real-time workshop bay availability.</p>
  </div>
  <div class="step">
    <b>Pay</b>
    <p class="muted">Complete your booking with our simulated payment gateway.</p>
  </div>
  <div class="step">
    <b>Track</b>
    <p class="muted">Follow real-time workshop service progress with our 5-step tracker.</p>
  </div>
</div>

<div class="page-head mt" style="margin-top: 36px;">
  <div>
    <h2>Featured Workshop Services</h2>
    <p class="muted">Professional maintenance by certified university technicians.</p>
  </div>
  <a class="btn ghost sm" href="<?php echo BASE_URL; ?>customer/services.php">View all services →</a>
</div>

<div class="grid g3" id="featuredServices">
  <div class="loading"><span class="spinner"></span> Loading services…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const container = document.getElementById('featuredServices');
  try {
    const res = await VSB.api('api/services.php');
    if (res.ok && Array.isArray(res.data) && res.data.length > 0) {
      container.innerHTML = res.data.slice(0, 3).map(s => `
        <div class="card service-card">
          <div>
            <h3>${VSB.escapeHtml(s.name)}</h3>
            <p class="muted">${VSB.escapeHtml(s.description || 'Professional workshop service')}</p>
          </div>
          <div>
            <div class="price">${VSB.formatMoney(s.price)}</div>
            <small class="muted">Estimated duration: ${s.duration_mins} mins</small>
            <div class="mt">
              <a class="btn sm block" href="<?php echo BASE_URL; ?>customer/book.php?service_id=${s.service_id}">Book this service</a>
            </div>
          </div>
        </div>
      `).join('');
    } else {
      container.innerHTML = '<div class="card empty"><p>No services currently listed.</p></div>';
    }
  } catch (err) {
    container.innerHTML = '<div class="card empty"><p class="error-text">Failed to load services catalogue.</p></div>';
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
