/**
 * Online Banking – Global JavaScript
 */

/* ── Accordion ─────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {

  // Accordion toggle
  document.querySelectorAll('.accordion-header').forEach(header => {
    header.addEventListener('click', () => {
      const item = header.closest('.accordion-item');
      item.classList.toggle('open');
    });
  });

  // OTP input auto-advance
  const otpInputs = document.querySelectorAll('.otp-input');
  otpInputs.forEach((input, idx) => {
    input.addEventListener('input', () => {
      input.value = input.value.replace(/\D/g, '').slice(0, 1);
      if (input.value && idx < otpInputs.length - 1) {
        otpInputs[idx + 1].focus();
      }
      // Combine all OTP digits into hidden field
      const hidden = document.getElementById('otp_code');
      if (hidden) hidden.value = [...otpInputs].map(i => i.value).join('');
    });

    input.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !input.value && idx > 0) {
        otpInputs[idx - 1].focus();
      }
    });
  });

  // Auto-dismiss flash alerts
  document.querySelectorAll('.alert[data-autohide]').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });

  // Confirm before delete
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm || 'Are you sure?')) {
        e.preventDefault();
      }
    });
  });

  // Toggle password visibility
  document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.target);
      if (!target) return;
      const isText = target.type === 'text';
      target.type = isText ? 'password' : 'text';
      btn.querySelector('i').className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
  });

  // Mark active nav button based on current path
  const path = window.location.pathname;
  document.querySelectorAll('.nav-btn[data-path]').forEach(btn => {
    if (path.startsWith(btn.dataset.path)) btn.classList.add('active');
  });

  // Transaction row expand (Sprint-2)
  document.querySelectorAll('.txn-row-toggle').forEach(row => {
    row.addEventListener('click', () => {
      const id = row.dataset.id;
      const detail = document.getElementById('txn-detail-' + id);
      if (detail) detail.classList.toggle('hidden');
    });
  });

});

/* ── Currency formatter (client-side) ─────────────── */
function formatINR(amount) {
  const sign = amount < 0 ? '−₹' : '₹';
  return sign + Math.abs(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

/* ── Show/hide element by ID ─────────────────────── */
function toggleEl(id) {
  const el = document.getElementById(id);
  if (el) el.classList.toggle('hidden');
}
