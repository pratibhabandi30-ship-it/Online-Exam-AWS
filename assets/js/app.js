// Online Exam System – global JS
(function () {
  'use strict';

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
})();
