<?php
require_once __DIR__ . '/includes/bootstrap.php';
Auth::redirectIfLoggedIn();

$pageTitle = 'Log in';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card auth">
  <h2>Log in</h2>
  <p class="muted">Access your vehicle service dashboard.</p>

  <div id="loginAlert" class="alert alert-error" style="display: none;"></div>

  <form id="loginForm" novalidate>
    <div class="field">
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" required autocomplete="username" placeholder="name@ui.edu.ng">
    </div>
    
    <div class="field">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••">
    </div>

    <button class="btn gold block" id="loginBtn" type="submit">Log in</button>
  </form>

  <p class="muted mt" style="text-align: center;">
    No account yet? <a href="<?php echo BASE_URL; ?>register.php">Register here</a>
  </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('loginForm');
  const alertBox = document.getElementById('loginAlert');
  const btn = document.getElementById('loginBtn');
  const emailInput = document.getElementById('email');
  const pwInput = document.getElementById('password');

  form.onsubmit = async function (e) {
    e.preventDefault();
    alertBox.style.display = 'none';
    alertBox.textContent = '';

    const email = emailInput.value.trim();
    const password = pwInput.value;

    if (!email || !password) {
      alertBox.textContent = 'Please enter both email and password.';
      alertBox.style.display = 'block';
      return;
    }

    VSB.setLoading(btn, true, 'Logging in…');

    const res = await VSB.api('api/auth/login.php', {
      method: 'POST',
      body: { email, password }
    });

    VSB.setLoading(btn, false);

    if (res.ok && res.success) {
      VSB.toast('Welcome back, ' + (res.data?.user?.full_name || 'User'), 'ok');
      const target = res.data?.redirect ? VSB.url(res.data.redirect) : VSB.url('customer/dashboard.php');
      window.location.href = target;
    } else {
      alertBox.textContent = res.message || 'Invalid email or password';
      alertBox.style.display = 'block';
    }
  };
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
