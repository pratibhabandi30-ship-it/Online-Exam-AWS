// Online Exam System – global JS
(function () {
  'use strict';

  // ---------- Password validation and visibility ----------
  const passwordPolicyInputs = Array.from(document.querySelectorAll('[data-password-policy]'));
  const passwordConfirmInputs = Array.from(document.querySelectorAll('[data-password-confirm]'));
  const passwordPolicyMessage = 'Use at least 8 characters, including a number and one of @ # $ % & * !.';
  const updatePasswordValidity = () => {
    passwordPolicyInputs.forEach(input => {
      const value = input.value;
      const valid = Array.from(value).length >= 8
        && /[0-9]/.test(value)
        && /[@#$%&*!]/.test(value);
      input.setCustomValidity(value && !valid ? passwordPolicyMessage : '');
    });
    passwordConfirmInputs.forEach(input => {
      const target = document.querySelector(input.dataset.passwordConfirm);
      input.setCustomValidity(input.value && target && input.value !== target.value
        ? 'The passwords do not match.'
        : '');
    });
  };
  passwordPolicyInputs.concat(passwordConfirmInputs).forEach(input => {
    input.addEventListener('input', updatePasswordValidity);
    const form = input.closest('form');
    form?.addEventListener('submit', updatePasswordValidity);
  });
  updatePasswordValidity();

  document.querySelectorAll('input[type="password"]').forEach(input => {
    const group = document.createElement('div');
    group.className = 'input-group';
    input.parentNode.insertBefore(group, input);
    group.appendChild(input);
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-outline-secondary';
    toggle.setAttribute('aria-label', 'Show password');
    toggle.setAttribute('aria-pressed', 'false');
    toggle.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
    toggle.addEventListener('click', () => {
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
      toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
      toggle.innerHTML = visible
        ? '<i class="bi bi-eye-slash" aria-hidden="true"></i>'
        : '<i class="bi bi-eye" aria-hidden="true"></i>';
      input.focus();
    });
    group.appendChild(toggle);
  });

  // ---------- Theme toggle ----------
  const savedTheme = localStorage.getItem('oes-theme') || 'light';
  document.documentElement.setAttribute('data-bs-theme', savedTheme);
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    const updateIcon = () => {
      const t = document.documentElement.getAttribute('data-bs-theme');
      themeBtn.innerHTML = t === 'dark'
        ? '<i class="bi bi-sun"></i>'
        : '<i class="bi bi-moon-stars"></i>';
    };
    updateIcon();
    themeBtn.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-bs-theme');
      const next = cur === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', next);
      localStorage.setItem('oes-theme', next);
      updateIcon();
    });
  }

  // ---------- HOD sidebar ----------
  const adminSidebar = document.getElementById('adminSidebar');
  if (adminSidebar) {
    const body = document.body;
    const preferenceKey = body.classList.contains('student-shell-page')
      ? 'oes-student-sidebar'
      : (body.classList.contains('faculty-shell-page') ? 'oes-faculty-sidebar' : 'oes-admin-sidebar');
    const sidebarToggle = document.querySelector('.admin-menu-toggle');
    const backdrop = document.querySelector('.admin-sidebar-backdrop');
    const searchToggle = document.querySelector('.admin-search-toggle');
    const searchForm = document.querySelector('.admin-search');
    const mobileSidebar = () => window.matchMedia(
      body.classList.contains('student-shell-page') ? '(max-width: 767.98px)' : '(max-width: 991.98px)'
    ).matches;
    const compactStudentSidebar = () => body.classList.contains('student-shell-page')
      && window.matchMedia('(min-width: 768px) and (max-width: 991.98px)').matches;
    const sidebarPreference = localStorage.getItem(preferenceKey);
    if ((sidebarPreference === 'collapsed' || (compactStudentSidebar() && sidebarPreference !== 'expanded')) && !mobileSidebar()) {
      body.classList.add('admin-sidebar-collapsed');
    }
    sidebarToggle?.addEventListener('click', () => {
      if (mobileSidebar()) {
        body.classList.toggle('admin-sidebar-open');
      } else {
        body.classList.toggle('admin-sidebar-collapsed');
        localStorage.setItem(preferenceKey, body.classList.contains('admin-sidebar-collapsed') ? 'collapsed' : 'expanded');
      }
    });
    backdrop?.addEventListener('click', () => body.classList.remove('admin-sidebar-open'));
    adminSidebar.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => body.classList.remove('admin-sidebar-open'));
    });
    searchToggle?.addEventListener('click', () => {
      searchForm?.classList.toggle('is-open');
      if (searchForm?.classList.contains('is-open')) searchForm.querySelector('input')?.focus();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        body.classList.remove('admin-sidebar-open');
        searchForm?.classList.remove('is-open');
      }
    });
    window.addEventListener('resize', () => {
      if (!mobileSidebar()) body.classList.remove('admin-sidebar-open');
      if (body.classList.contains('student-shell-page')) {
        const preference = localStorage.getItem(preferenceKey);
        if (compactStudentSidebar() && preference !== 'expanded') {
          body.classList.add('admin-sidebar-collapsed');
        } else if (!compactStudentSidebar() && preference === null) {
          body.classList.remove('admin-sidebar-collapsed');
        }
      }
    });
  }

  // ---------- Landing page motion and actual-count statistics ----------
  const homePage = document.querySelector('.home-page');
  if (homePage) {
    const revealItems = document.querySelectorAll('[data-reveal]');
    const counters = document.querySelectorAll('[data-count]');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const formatCount = value => new Intl.NumberFormat().format(value);

    if ('IntersectionObserver' in window && !prefersReducedMotion) {
      homePage.classList.add('motion-enabled');
      const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.14 });
      revealItems.forEach(item => revealObserver.observe(item));

      const countObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          const element = entry.target;
          const target = Number.parseInt(element.dataset.count, 10);
          if (!Number.isFinite(target) || target <= 0) {
            element.textContent = '0';
            countObserver.unobserve(element);
            return;
          }
          const start = performance.now();
          const duration = 950;
          const animate = now => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = formatCount(Math.round(target * eased));
            if (progress < 1) window.requestAnimationFrame(animate);
          };
          window.requestAnimationFrame(animate);
          countObserver.unobserve(element);
        });
      }, { threshold: 0.45 });
      counters.forEach(counter => countObserver.observe(counter));
    }

    document.querySelectorAll('.home-page-content a[href^="#"]').forEach(link => {
      link.addEventListener('click', event => {
        const target = document.querySelector(link.getAttribute('href'));
        if (target) {
          event.preventDefault();
          target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
          history.replaceState(null, '', link.getAttribute('href'));
        }
      });
    });
  }

  // ---------- Exam timer ----------
  const timerEl = document.getElementById('examTimer');
  if (timerEl) {
    const form = document.getElementById('examForm');
    let remaining = parseInt(timerEl.dataset.seconds, 10) || 0;
    const display = timerEl.querySelector('[data-display]');
    const tick = () => {
      const m = String(Math.floor(remaining / 60)).padStart(2, '0');
      const s = String(remaining % 60).padStart(2, '0');
      if (display) display.textContent = `${m}:${s}`;
      if (remaining <= 60) timerEl.classList.add('danger');
      else if (remaining <= 180) timerEl.classList.add('warning');
      if (remaining <= 0) {
        clearInterval(intId);
        if (form) {
          const flag = document.createElement('input');
          flag.type = 'hidden'; flag.name = 'auto_submit'; flag.value = '1';
          form.appendChild(flag);
          form.submit();
        }
      }
      remaining--;
    };
    tick();
    const intId = setInterval(tick, 1000);

    // Warn on leave
    window.addEventListener('beforeunload', (e) => {
      if (form && !form.dataset.submitting) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
    if (form) {
      form.addEventListener('submit', () => { form.dataset.submitting = '1'; });
    }
  }

  // ---------- Question navigation (exam) ----------
  document.querySelectorAll('[data-jump-question]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById('q-' + btn.dataset.jumpQuestion);
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  // ---------- Confirm destructive actions ----------
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });

  // ---------- Score ring animation ----------
  document.querySelectorAll('.score-ring').forEach(ring => {
    const target = parseFloat(ring.dataset.value) || 0;
    let cur = 0;
    const step = Math.max(1, target / 30);
    const id = setInterval(() => {
      cur += step;
      if (cur >= target) { cur = target; clearInterval(id); }
      ring.style.setProperty('--val', cur);
    }, 25);
  });

  // ---------- Auto-grow textareas ----------
  document.querySelectorAll('textarea[data-autogrow]').forEach(t => {
    const grow = () => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; };
    t.addEventListener('input', grow); grow();
  });

  // ---------- Course thumbnail preview ----------
  const thumbnailInput = document.getElementById('courseThumbnail');
  const thumbnailPreview = document.getElementById('courseThumbnailPreview');
  if (thumbnailInput && thumbnailPreview) {
    let previewUrl = null;
    thumbnailInput.addEventListener('change', () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      const file = thumbnailInput.files && thumbnailInput.files[0];
      if (file) {
        previewUrl = URL.createObjectURL(file);
        thumbnailPreview.src = previewUrl;
      }
    });
    window.addEventListener('beforeunload', () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
    }, { once: true });
  }
})();
