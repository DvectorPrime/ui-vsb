/**
 * assets/js/app.js
 * Core frontend foundation for UI-VSB.
 * Provides the global VSB namespace with Fetch client, UI components,
 * formatters, DOM helpers, and client-side validators.
 */
(function (window, document) {
  'use strict';

  const VSB = {};

  // Resolve full application URLs using window.APP_BASE
  VSB.url = function (path) {
    const base = window.APP_BASE || '/ui-vsb/';
    return base.replace(/\/+$/, '') + '/' + String(path || '').replace(/^\/+/, '');
  };

  /**
   * Unified API Fetch client for the UI-VSB backend.
   * Sends same-origin credentials (PHP session cookies) and parses envelopes.
   */
  VSB.api = async function (path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    let url = VSB.url(path);

    if (options.query && typeof options.query === 'object') {
      const qs = new URLSearchParams(options.query).toString();
      if (qs) url += (url.includes('?') ? '&' : '?') + qs;
    }

    const headers = {
      'Accept': 'application/json',
      ...(options.headers || {})
    };

    let bodyData;
    if (options.body !== undefined && options.body !== null) {
      if (options.body instanceof FormData) {
        bodyData = options.body;
      } else {
        headers['Content-Type'] = 'application/json';
        bodyData = JSON.stringify(options.body);
      }
    }

    try {
      const res = await fetch(url, {
        method,
        headers,
        body: bodyData,
        credentials: 'same-origin'
      });

      const json = await res.json().catch(() => ({}));

      // Session expired or unauthenticated -> redirect to login page
      if (res.status === 401 && !path.includes('api/auth/login.php')) {
        window.location.href = VSB.url('login.php');
        return { ok: false, status: 401, message: 'Please log in' };
      }

      return {
        ok: res.ok,
        status: res.status,
        success: Boolean(json.success),
        message: json.message || (res.ok ? 'OK' : 'Request failed'),
        data: json.data !== undefined ? json.data : null,
        errors: json.errors || null,
        raw: json
      };
    } catch (err) {
      return {
        ok: false,
        status: 0,
        success: false,
        message: 'Network error. Please check your connection.',
        data: null,
        errors: null
      };
    }
  };

  /**
   * Toast notification system
   */
  VSB.toast = function (message, type = '') {
    let container = document.getElementById('toasts');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toasts';
      container.className = 'toasts';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast ' + (type ? String(type).trim() : '');
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity .3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  };

  /**
   * Modal dialog helper
   */
  VSB.modal = function (html, onMount) {
    let root = document.getElementById('modalRoot');
    if (!root) {
      root = document.createElement('div');
      root.id = 'modalRoot';
      document.body.appendChild(root);
    }

    root.innerHTML = `
      <div class="overlay" id="modalOverlay">
        <div class="modal" role="dialog" aria-modal="true">
          ${html}
        </div>
      </div>
    `;

    const close = () => { root.innerHTML = ''; };

    const overlay = document.getElementById('modalOverlay');
    if (overlay) {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
      });
    }

    root.querySelectorAll('[data-close]').forEach((btn) => {
      btn.addEventListener('click', close);
    });

    const escHandler = (e) => {
      if (e.key === 'Escape') {
        close();
        document.removeEventListener('keydown', escHandler);
      }
    };
    document.addEventListener('keydown', escHandler);

    if (typeof onMount === 'function') {
      onMount(root, close);
    }

    return close;
  };

  /**
   * Confirmation dialog promise
   */
  VSB.confirm = function (message, okLabel = 'Confirm') {
    return new Promise((resolve) => {
      VSB.modal(`
        <h3>Please confirm</h3>
        <p>${VSB.escapeHtml(message)}</p>
        <div class="modal-actions">
          <button type="button" class="btn ghost" data-close>Cancel</button>
          <button type="button" class="btn danger" id="confirmOk">${VSB.escapeHtml(okLabel)}</button>
        </div>
      `, (root, close) => {
        const okBtn = document.getElementById('confirmOk');
        if (okBtn) {
          okBtn.onclick = () => { close(); resolve(true); };
        }
        root.querySelectorAll('[data-close]').forEach((b) => {
          b.onclick = () => { close(); resolve(false); };
        });
      });
    });
  };

  /**
   * String escaping helper
   */
  VSB.escapeHtml = function (val) {
    if (val === null || val === undefined) return '';
    return String(val)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  /**
   * Formatters
   */
  VSB.formatMoney = function (amount) {
    const n = Number(amount) || 0;
    return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  VSB.formatDate = function (dateStr) {
    if (!dateStr) return '—';
    try {
      const d = new Date(dateStr + (dateStr.includes('T') ? '' : 'T00:00:00'));
      return d.toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric'
      });
    } catch (e) {
      return dateStr;
    }
  };

  VSB.formatDateTime = function (isoStr) {
    if (!isoStr) return '—';
    try {
      const d = new Date(isoStr.replace(' ', 'T'));
      return d.toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (e) {
      return isoStr;
    }
  };

  /**
   * Status badges generator
   */
  VSB.statusBadge = function (status) {
    const s = String(status || '').toLowerCase().trim();
    const labels = {
      'pending_payment': 'Pending Payment',
      'pending_approval': 'Pending Approval',
      'approved': 'Approved',
      'rejected': 'Rejected',
      'cancelled': 'Cancelled',
      'not_started': 'Not Started',
      'received': 'Received',
      'in_progress': 'In Progress',
      'completed': 'Completed',
      'ready_for_pickup': 'Ready for Pickup',
      'success': 'Success',
      'failed': 'Failed',
      'refunded': 'Refunded'
    };
    const label = labels[s] || status;
    return `<span class="badge badge-${VSB.escapeHtml(s)}">${VSB.escapeHtml(label)}</span>`;
  };

  /**
   * 5-Step service progress tracker
   */
  VSB.trackerHTML = function (serviceStatus, bookingStatus) {
    if (bookingStatus === 'rejected') {
      return '<div class="alert alert-error" style="margin:10px 0;">This booking was rejected by the workshop. Any payment made has been refunded.</div>';
    }
    if (bookingStatus === 'cancelled') {
      return '<div class="alert alert-error" style="margin:10px 0;">This booking hold expired because payment was not completed within 30 minutes.</div>';
    }
    if (bookingStatus === 'pending_payment') {
      return '<div class="alert alert-info" style="margin:10px 0;">Awaiting customer payment. Please complete payment to reserve your workshop bay.</div>';
    }
    if (bookingStatus === 'pending_approval') {
      return '<div class="alert alert-info" style="margin:10px 0;">Payment received. Awaiting workshop admin confirmation and bay assignment.</div>';
    }

    const steps = [
      { key: 'not_started', label: 'Not started' },
      { key: 'received', label: 'Received' },
      { key: 'in_progress', label: 'In progress' },
      { key: 'completed', label: 'Completed' },
      { key: 'ready_for_pickup', label: 'Ready for pickup' }
    ];

    const order = { 'not_started': 0, 'received': 1, 'in_progress': 2, 'completed': 3, 'ready_for_pickup': 4 };
    const curIdx = order[serviceStatus] !== undefined ? order[serviceStatus] : 0;

    let html = '<div class="tracker stepper">';
    steps.forEach((st, idx) => {
      let cls = 't stepper-step';
      if (idx < curIdx || (curIdx === 4 && idx <= 4)) {
        cls += ' done';
      } else if (idx === curIdx) {
        cls += ' now active';
      }
      html += `<div class="${cls}">${VSB.escapeHtml(st.label)}</div>`;
    });
    html += '</div>';

    return html;
  };

  /**
   * Form loading helper
   */
  VSB.setLoading = function (button, isLoading, loadingText = 'Processing…') {
    if (!button) return;
    if (isLoading) {
      button.dataset.origText = button.innerHTML;
      button.disabled = true;
      button.innerHTML = `<span class="spinner"></span> ${VSB.escapeHtml(loadingText)}`;
    } else {
      button.disabled = false;
      if (button.dataset.origText) {
        button.innerHTML = button.dataset.origText;
      }
    }
  };

  /**
   * Field error display helper
   */
  VSB.showFieldErrors = function (form, errors) {
    if (!form || !errors) return;
    VSB.clearErrors(form);

    for (const [field, msg] of Object.entries(errors)) {
      const input = form.querySelector(`[name="${field}"], #${field}`);
      if (input) {
        input.classList.add('is-invalid');
        const errSpan = document.createElement('div');
        errSpan.className = 'field-error error-text';
        errSpan.textContent = msg;
        input.parentElement.appendChild(errSpan);
      }
    }
  };

  VSB.clearErrors = function (form) {
    if (!form) return;
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error, .error-text:not(#formAlert)').forEach(el => el.remove());
    const alert = form.querySelector('#formAlert');
    if (alert) {
      alert.hidden = true;
      alert.textContent = '';
    }
  };

  /**
   * Client-side validators
   */
  VSB.validate = {
    email: (email) => {
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return 'Enter a valid email address';
      return '';
    },
    phone: (phone) => {
      if (!phone || !/^\+?\d{10,15}$/.test(phone)) return 'Phone must be 10 to 15 digits';
      return '';
    },
    password: (pw) => {
      if (!pw || pw.length < 8) return 'Password must be at least 8 characters';
      if (!/[A-Za-z]/.test(pw) || !/\d/.test(pw)) return 'Password must contain at least one letter and one number';
      return '';
    },
    luhn: (number) => {
      const clean = String(number).replace(/\s+|-/g, '');
      if (!/^\d{16}$/.test(clean)) return 'Card number must be 16 digits';
      let sum = 0;
      const len = clean.length;
      const parity = len % 2;
      for (let i = 0; i < len; i++) {
        let digit = parseInt(clean.charAt(i), 10);
        if (i % 2 === parity) {
          digit *= 2;
          if (digit > 9) digit -= 9;
        }
        sum += digit;
      }
      if (sum % 10 !== 0) return 'Invalid card number (checksum failed)';
      return '';
    },
    expiry: (exp) => {
      if (!exp || !/^(0[1-9]|1[0-2])\/\d{2}$/.test(exp)) return 'Expiry must be in MM/YY format';
      const parts = exp.split('/');
      const month = parseInt(parts[0], 10);
      const year = 2000 + parseInt(parts[1], 10);
      const now = new Date();
      const expDate = new Date(year, month, 0, 23, 59, 59);
      if (expDate < now) return 'Card has expired';
      return '';
    },
    cvv: (cvv) => {
      if (!cvv || !/^\d{3}$/.test(cvv)) return 'CVV must be 3 digits';
      return '';
    }
  };

  /**
   * Logout helper
   */
  VSB.logout = async function () {
    try {
      await VSB.api('api/auth/logout.php', { method: 'POST' });
    } catch (e) {
      // ignore
    }
    window.location.href = VSB.url('login.php');
  };

  /**
   * Initialize Global Mobile Navigation & Accessibility Listeners
   */
  document.addEventListener('DOMContentLoaded', function () {
    const menuBtn = document.getElementById('menuBtn');
    const nav = document.getElementById('nav');

    if (menuBtn && nav) {
      menuBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = nav.classList.toggle('open');
        menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        menuBtn.textContent = isOpen ? '✕' : '☰';
      });

      // Close mobile drawer when clicking anywhere outside
      document.addEventListener('click', function (e) {
        if (nav.classList.contains('open') && !nav.contains(e.target) && e.target !== menuBtn) {
          nav.classList.remove('open');
          menuBtn.setAttribute('aria-expanded', 'false');
          menuBtn.textContent = '☰';
        }
      });

      // Close mobile drawer when clicking a navigation link or button
      nav.querySelectorAll('a, button').forEach(el => {
        el.addEventListener('click', function () {
          if (window.innerWidth <= 820 && nav.classList.contains('open')) {
            nav.classList.remove('open');
            menuBtn.setAttribute('aria-expanded', 'false');
            menuBtn.textContent = '☰';
          }
        });
      });
    }

    // ESC key closes active modals
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        const overlay = document.querySelector('.overlay');
        if (overlay) overlay.remove();
      }
    });
  });

  // Expose to window
  window.VSB = VSB;

})(window, document);
