<footer class="oes-footer mt-5 py-4 border-top">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="text-muted small">
      &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Built for college projects &amp; learning.
    </div>
    <div class="small">
      <a class="text-decoration-none text-muted" href="https://github.com/aashishbharti04/online-exam-system" target="_blank" rel="noopener">
        <i class="bi bi-github"></i> Source on GitHub
      </a>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
