<?php if (($activeNav ?? '') === 'home'): ?>
<footer class="oes-footer home-footer">
  <div class="container">
    <div class="row g-5 pb-4">
      <div class="col-lg-5">
        <a class="home-footer-brand" href="<?= e(base_url('index.php')) ?>">
          <span><i class="bi bi-mortarboard-fill"></i></span><?= e(APP_NAME) ?>
        </a>
        <p class="home-footer-description">A thoughtful, secure examination platform to help students build confidence, track their growth, and make every achievement count.</p>
        <a class="home-footer-email" href="mailto:aashish@marketdoctorsonline.com"><i class="bi bi-envelope"></i> aashish@marketdoctorsonline.com</a>
      </div>
      <div class="col-6 col-lg-2">
        <h2 class="home-footer-heading">Explore</h2>
        <ul class="home-footer-links">
          <li><a href="<?= e(base_url('exams.php')) ?>">Browse courses</a></li>
          <li><a href="<?= e(base_url('index.php#features')) ?>">Platform features</a></li>
          <li><a href="<?= e(base_url('index.php#how')) ?>">How it works</a></li>
          <li><a href="<?= e(base_url('index.php#faq')) ?>">FAQs</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h2 class="home-footer-heading">Platform</h2>
        <ul class="home-footer-links">
          <li><a href="<?= e(base_url('index.php#achievements')) ?>">Certificates</a></li>
          <li><a href="<?= e(base_url('login.php')) ?>">Student login</a></li>
          <li><a href="<?= e(base_url('admin/login.php')) ?>">Admin login</a></li>
          <li><a href="<?= e(base_url('feedback.php')) ?>">Contact / feedback</a></li>
        </ul>
      </div>
      <div class="col-lg-3">
        <div class="home-footer-note">
          <span><i class="bi bi-shield-check"></i></span>
          <strong>Built for a better learning experience.</strong>
          <small>Secure exams. Clear results. Recognized progress.</small>
        </div>
        <a class="home-footer-register" href="<?= e(base_url('register.php')) ?>">Create student account <i class="bi bi-arrow-up-right"></i></a>
      </div>
    </div>
    <div class="home-footer-bottom">
      <span>&copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?></strong>. All rights reserved.</span>
      <span>Learning, measured with purpose.</span>
    </div>
  </div>
</footer>
<?php else: ?>
<footer class="oes-footer mt-5 pt-5 pb-4 border-top">
  <div class="container">
    <div class="row g-4 mb-4">
      <div class="col-lg-5">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-mortarboard-fill" style="color:var(--oes-primary); font-size:1.4rem"></i>
          <span class="fw-bold fs-5"><?= e(APP_NAME) ?></span>
        </div>
        <p class="text-muted small mb-3">A modern, secure platform for college quizzes &amp; exams. Free to use, learn from, and extend for your own projects.</p>
        <a class="text-decoration-none small d-inline-flex align-items-center gap-1" href="mailto:aashish@marketdoctorsonline.com">
          <i class="bi bi-envelope-fill"></i> aashish@marketdoctorsonline.com
        </a>
      </div>

      <div class="col-6 col-lg-3">
        <h6 class="text-uppercase small fw-bold text-muted mb-3">Quick links</h6>
        <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
          <li><a class="text-decoration-none text-body-secondary" href="<?= e(base_url('index.php')) ?>">Home</a></li>
          <li><a class="text-decoration-none text-body-secondary" href="<?= e(base_url('exams.php')) ?>">Exams</a></li>
          <li><a class="text-decoration-none text-body-secondary" href="<?= e(base_url('leaderboard.php')) ?>">Leaderboard</a></li>
          <li><a class="text-decoration-none text-body-secondary" href="<?= e(base_url('feedback.php')) ?>">Feedback</a></li>
        </ul>
      </div>

      <div class="col-6 col-lg-4">
        <h6 class="text-uppercase small fw-bold text-muted mb-3">Connect with the developer</h6>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-sm btn-outline-secondary social-btn" href="https://in.linkedin.com/in/aashana1012" target="_blank" rel="noopener" title="LinkedIn">
            <i class="bi bi-linkedin"></i> LinkedIn
          </a>
          <a class="btn btn-sm btn-outline-secondary social-btn" href="https://github.com/aashishbharti04" target="_blank" rel="noopener" title="GitHub">
            <i class="bi bi-github"></i> GitHub
          </a>
          <a class="btn btn-sm btn-outline-secondary social-btn" href="https://www.youtube.com/@CodeWithAsur" target="_blank" rel="noopener" title="YouTube">
            <i class="bi bi-youtube"></i> YouTube
          </a>
          <a class="btn btn-sm btn-outline-secondary social-btn" href="https://www.instagram.com/asurwave1012" target="_blank" rel="noopener" title="Instagram">
            <i class="bi bi-instagram"></i> Instagram
          </a>
        </div>
        <div class="small text-muted mt-3">
          <i class="bi bi-code-slash"></i> Subscribe to <a class="text-decoration-none" href="https://www.youtube.com/@CodeWithAsur" target="_blank" rel="noopener">@CodeWithAsur</a> for more PHP / web-dev tutorials.
        </div>
      </div>
    </div>

    <hr class="my-3 opacity-25">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
      <div>
        &copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?></strong> &middot; Crafted by
        <a class="text-decoration-none fw-semibold" href="https://github.com/aashishbharti04" target="_blank" rel="noopener">Aashish Bharti</a>.
        All rights reserved.
      </div>
      <div>
        Licensed under <a class="text-decoration-none" href="https://github.com/aashishbharti04/online-exam-system/blob/main/LICENSE" target="_blank" rel="noopener">MIT</a>
        &middot;
        <a class="text-decoration-none" href="https://github.com/aashishbharti04/online-exam-system" target="_blank" rel="noopener"><i class="bi bi-github"></i> Source</a>
      </div>
    </div>
  </div>
</footer>
<?php endif; ?>
<?php if (!empty($isAdminShell) || !empty($isFacultyShell) || !empty($isStudentShell)): ?>
  </main>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= e(base_url('assets/js/app.js?v=20261108-password-policy')) ?>"></script>
</body>
</html>
