<?php
/**
 * includes/header.php
 * Common page header for all UI-VSB pages.
 * Handles user session detection, role-aware navigation, and client asset loading.
 */
require_once __DIR__ . '/bootstrap.php';

$currentUser = Auth::currentUser();
$userRole    = $currentUser['role'] ?? 'guest';
$userName    = $currentUser['full_name'] ?? '';
$pageTitle   = isset($pageTitle) ? $pageTitle . ' · UI Vehicle Service' : 'UI Vehicle Service Booking · University of Ibadan';

// Active page detection
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
function isNavActive(string $slug): string {
    global $scriptPath;
    return strpos($scriptPath, $slug) !== false ? 'active' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
  <script>
    window.APP_BASE = '<?php echo BASE_URL; ?>';
  </script>
  <script src="<?php echo BASE_URL; ?>assets/js/config.js" defer></script>
  <script src="<?php echo BASE_URL; ?>assets/js/app.js" defer></script>
  <?php if (!empty($pageScripts) && is_array($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
      <script src="<?php echo BASE_URL . ltrim($script, '/'); ?>" defer></script>
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="<?php echo BASE_URL; ?>index.php">
      <span class="brand-mark" aria-hidden="true">UI</span>
      <span class="brand-text">
        <strong>Vehicle Service</strong>
        <small>Maintenance Directorate · University of Ibadan</small>
      </span>
    </a>
    <button class="menu-btn" id="menuBtn" aria-label="Toggle navigation menu" aria-expanded="false">☰</button>
    
    <nav id="nav" class="nav">
      <?php if ($userRole === 'guest'): ?>
        <a href="<?php echo BASE_URL; ?>index.php" class="<?php echo isNavActive('index.php'); ?>">Home</a>
        <a href="<?php echo BASE_URL; ?>customer/services.php" class="<?php echo isNavActive('services.php'); ?>">Services</a>
        <a href="<?php echo BASE_URL; ?>login.php" class="<?php echo isNavActive('login.php'); ?>">Log in</a>
        <a href="<?php echo BASE_URL; ?>register.php" class="btn gold sm <?php echo isNavActive('register.php'); ?>">Register</a>
      <?php elseif ($userRole === 'admin'): ?>
        <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="<?php echo isNavActive('admin/dashboard.php'); ?>">Dashboard</a>
        <a href="<?php echo BASE_URL; ?>admin/services.php" class="<?php echo isNavActive('admin/services.php'); ?>">Services</a>
        <a href="<?php echo BASE_URL; ?>admin/bookings.php" class="<?php echo isNavActive('admin/bookings.php'); ?>">Bookings</a>
        <a href="<?php echo BASE_URL; ?>admin/customers.php" class="<?php echo isNavActive('admin/customers.php'); ?>">Customers</a>
        <a href="<?php echo BASE_URL; ?>admin/payments.php" class="<?php echo isNavActive('admin/payments.php'); ?>">Payments</a>
        <a href="<?php echo BASE_URL; ?>admin/records.php" class="<?php echo isNavActive('admin/records.php'); ?>">Records</a>
        <button type="button" onclick="VSB.logout()">Log out (<?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>)</button>
      <?php else: /* customer */ ?>
        <a href="<?php echo BASE_URL; ?>customer/dashboard.php" class="<?php echo isNavActive('customer/dashboard.php'); ?>">Dashboard</a>
        <a href="<?php echo BASE_URL; ?>customer/vehicles.php" class="<?php echo isNavActive('customer/vehicles.php'); ?>">My Vehicles</a>
        <a href="<?php echo BASE_URL; ?>customer/services.php" class="<?php echo isNavActive('customer/services.php'); ?>">Services</a>
        <a href="<?php echo BASE_URL; ?>customer/book.php" class="<?php echo isNavActive('customer/book.php'); ?>">Book Service</a>
        <a href="<?php echo BASE_URL; ?>customer/track.php" class="<?php echo isNavActive('customer/track.php'); ?>">My Bookings</a>
        <button type="button" onclick="VSB.logout()">Log out (<?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>)</button>
      <?php endif; ?>
    </nav>
  </header>

  <main class="container">
