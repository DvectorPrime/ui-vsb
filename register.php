<?php
require_once __DIR__ . '/includes/bootstrap.php';
Auth::redirectIfLoggedIn();

$pageTitle = 'Register Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card auth" style="max-width: 500px;">
  <h2>Create Account</h2>
  <p class="muted">Register to schedule workshop appointments and track vehicle repairs.</p>

  <div id="registerAlert" class="alert alert-error" style="display: none;"></div>

  <form id="registerForm" novalidate>
    <div class="field">
      <label for="full_name">Full Name</label>
      <input id="full_name" name="full_name" type="text" required placeholder="e.g. Adeyemi Johnson">
    </div>

    <div class="row">
      <div class="field">
        <label for="email">University Email</label>
        <input id="email" name="email" type="email" required placeholder="name@ui.edu.ng">
      </div>
      <div class="field">
        <label for="phone">Phone Number</label>
        <input id="phone" name="phone" type="tel" required placeholder="08012345678">
      </div>
    </div>

    <div class="row">
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required placeholder="At least 8 chars">
        <div class="hint">Min. 8 characters with letters & numbers</div>
      </div>
      <div class="field">
        <label for="confirm_password">Confirm Password</label>
        <input id="confirm_password" name="confirm_password" type="password" required placeholder="Repeat password">
      </div>
    </div>

    <button class="btn gold block" id="registerBtn" type="submit" style="margin-top: 8px;">Create Account</button>
  </form>

  <p class="muted mt" style="text-align: center;">
    Already registered? <a href="<?php echo BASE_URL; ?>login.php">Log in here</a>
  </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('registerForm');
  const alertBox = document.getElementById('registerAlert');
  const btn = document.getElementById('registerBtn');

  form.onsubmit = async function (e) {
    e.preventDefault();
    alertBox.style.display = 'none';
    VSB.clearErrors(form);

    const payload = {
      full_name: document.getElementById('full_name').value.trim(),
      email: document.getElementById('email').value.trim(),
      phone: document.getElementById('phone').value.trim(),
      password: document.getElementById('password').value,
      confirm_password: document.getElementById('confirm_password').value
    };

    // Client-side validation checks
    const errors = {};
    if (!payload.full_name || payload.full_name.length < 2) {
      errors.full_name = 'Full name must be at least 2 characters';
    }
    const emailErr = VSB.validate.email(payload.email);
    if (emailErr) errors.email = emailErr;

    const phoneErr = VSB.validate.phone(payload.phone);
    if (phoneErr) errors.phone = phoneErr;

    const pwErr = VSB.validate.password(payload.password);
    if (pwErr) errors.password = pwErr;

    if (payload.password !== payload.confirm_password) {
      errors.confirm_password = 'Passwords do not match';
    }

    if (Object.keys(errors).length > 0) {
      VSB.showFieldErrors(form, errors);
      return;
    }

    VSB.setLoading(btn, true, 'Creating account…');

    const res = await VSB.api('api/auth/register.php', {
      method: 'POST',
      body: payload
    });

    VSB.setLoading(btn, false);

    if (res.ok && res.success) {
      VSB.toast('Account created! Welcome to UI Vehicle Service.', 'ok');
      window.location.href = VSB.url('customer/dashboard.php');
    } else {
      if (res.errors) {
        VSB.showFieldErrors(form, res.errors);
      }
      alertBox.textContent = res.message || 'Registration failed. Please check the form.';
      alertBox.style.display = 'block';
    }
  };
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
