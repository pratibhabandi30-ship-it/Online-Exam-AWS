<?php
require_once __DIR__ . '/includes/auth.php';

$current = current_user();
if ($current) {
    redirect($current['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
}

$pdo = db();
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM quizzes WHERE is_published = 1) AS available_courses,
        (SELECT COUNT(*) FROM quizzes q
         WHERE q.is_published = 1
           AND EXISTS (SELECT 1 FROM questions WHERE quiz_id = q.id)) AS published_exams,
        (SELECT COUNT(*) FROM users WHERE role = 'student') AS registered_students,
        (SELECT COUNT(*) FROM certificates WHERE status = 'issued') AS certificates_awarded
")->fetch();

$courses = $pdo->query("
    SELECT q.id, q.title, q.tag, q.intro, q.difficulty,
           (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count
    FROM quizzes q
    WHERE q.is_published = 1
      AND EXISTS (SELECT 1 FROM questions WHERE quiz_id = q.id)
    ORDER BY q.created_at DESC, q.id DESC
    LIMIT 6
")->fetchAll();

$pageTitle = 'Home';
$activeNav = 'home';
include __DIR__ . '/includes/header.php';
?>

<main class="home-page-content">
  <section class="home-hero">
    <div class="container home-hero-container">
      <div class="row align-items-center g-5">
        <div class="col-lg-6" data-reveal>
          <div class="home-eyebrow"><span class="home-eyebrow-dot"></span> Next Generation Online Examination Platform</div>
          <h1 class="home-hero-title">Your Exams.<br>Your <span class="home-gradient-text">Progress.</span><br>Your Success.</h1>
          <p class="home-hero-copy">A smarter place to learn, take secure online exams, track your growth, and celebrate every achievement.</p>
          <div class="d-flex flex-wrap gap-3 mt-4">
            <a class="btn btn-primary btn-lg home-primary-cta" href="<?= e(base_url('register.php')) ?>">
              Get Started <i class="bi bi-arrow-up-right ms-1"></i>
            </a>
            <a class="btn btn-outline-primary btn-lg home-secondary-cta" href="<?= e(base_url('exams.php')) ?>">
              Explore Courses <i class="bi bi-arrow-down-right ms-1"></i>
            </a>
          </div>
          <div class="home-trust-row" aria-label="Platform benefits">
            <span><i class="bi bi-shield-lock-fill"></i> Secure exams</span>
            <span><i class="bi bi-lightning-charge-fill"></i> Instant results</span>
            <span><i class="bi bi-award-fill"></i> Digital certificates</span>
          </div>
        </div>

        <div class="col-lg-6" data-reveal>
          <div class="home-visual" aria-label="Illustration of the student learning and achievement dashboard">
            <div class="home-visual-orbit home-visual-orbit-one"></div>
            <div class="home-visual-orbit home-visual-orbit-two"></div>
            <div class="home-dashboard">
              <div class="home-dashboard-top">
                <div class="home-window-controls" aria-hidden="true"><i></i><i></i><i></i></div>
                <span>STUDENT OVERVIEW</span>
                <i class="bi bi-three-dots"></i>
              </div>
              <div class="home-dashboard-body">
                <div class="home-dashboard-greeting">
                  <div>
                    <span class="home-ui-kicker">YOUR LEARNING SPACE</span>
                    <h2>Keep moving forward</h2>
                  </div>
                  <div class="home-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></div>
                </div>

                <div class="home-dashboard-grid">
                  <div class="home-progress-panel">
                    <div class="home-panel-heading"><span>Learning progress</span><i class="bi bi-arrow-up-right"></i></div>
                    <div class="home-chart" aria-hidden="true">
                      <div class="home-chart-grid"></div>
                      <svg viewBox="0 0 360 112" role="presentation" preserveAspectRatio="none">
                        <defs>
                          <linearGradient id="homeChartFill" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#6c63ff" stop-opacity=".24"/>
                            <stop offset="100%" stop-color="#6c63ff" stop-opacity="0"/>
                          </linearGradient>
                        </defs>
                        <path d="M0 94 C28 90 38 72 66 78 S105 88 130 60 S171 72 197 49 S238 62 260 34 S300 48 322 22 S344 24 360 10 L360 112 L0 112 Z" fill="url(#homeChartFill)"/>
                        <path d="M0 94 C28 90 38 72 66 78 S105 88 130 60 S171 72 197 49 S238 62 260 34 S300 48 322 22 S344 24 360 10" fill="none" stroke="#675cf3" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linecap="round"/>
                        <circle cx="322" cy="22" r="5" fill="#fff" stroke="#675cf3" stroke-width="3" vector-effect="non-scaling-stroke"/>
                      </svg>
                    </div>
                    <div class="home-chart-labels"><span>Learn</span><span>Practice</span><span>Achieve</span><span>Grow</span></div>
                  </div>
                  <div class="home-achievement-panel">
                    <div class="home-panel-heading"><span>Achievements</span><i class="bi bi-stars"></i></div>
                    <div class="home-achievement-seal"><i class="bi bi-award-fill"></i></div>
                    <strong>Every milestone<br>counts</strong>
                    <span>Celebrate your progress</span>
                  </div>
                </div>

                <div class="home-certificate-preview">
                  <div class="home-certificate-mark"><i class="bi bi-patch-check-fill"></i></div>
                  <div class="home-certificate-text">
                    <span class="home-ui-kicker">A MOMENT WORTH KEEPING</span>
                    <strong>Certificate of Achievement</strong>
                    <span>Earned through your dedication</span>
                  </div>
                  <i class="bi bi-arrow-up-right home-certificate-arrow"></i>
                </div>
              </div>
            </div>
            <div class="home-float-card home-float-secure"><span><i class="bi bi-shield-check"></i></span><div><strong>Secure by design</strong><small>Every step protected</small></div></div>
            <div class="home-float-card home-float-award"><span><i class="bi bi-trophy-fill"></i></span><div><strong>Keep achieving</strong><small>Your effort, recognized</small></div></div>
            <div class="home-visual-glow"></div>
          </div>
        </div>
      </div>
      <a class="home-scroll-cue" href="#platform-stats"><span></span> Scroll to explore</a>
    </div>
  </section>

  <section class="home-stats-section" id="platform-stats" aria-label="Platform statistics">
    <div class="container">
      <div class="home-stats-grid">
        <?php
        $statCards = [
            ['available_courses', 'Available courses', 'bi-collection', 'home-stat-indigo'],
            ['published_exams', 'Published exams', 'bi-journal-check', 'home-stat-blue'],
            ['registered_students', 'Registered students', 'bi-people', 'home-stat-violet'],
            ['certificates_awarded', 'Certificates awarded', 'bi-award', 'home-stat-amber'],
        ];
        foreach ($statCards as [$key, $label, $icon, $color]):
        ?>
          <article class="home-stat-card" data-reveal>
            <span class="home-stat-icon <?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></span>
            <div class="home-stat-value" data-count="<?= (int) $stats[$key] ?>"><?= number_format((int) $stats[$key]) ?></div>
            <div class="home-stat-label"><?= e($label) ?></div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="home-section home-features-section" id="features">
    <div class="container">
      <div class="home-section-heading text-center" data-reveal>
        <span class="home-section-kicker">A better exam experience</span>
        <h2>Everything you need to <span class="home-gradient-text">move forward.</span></h2>
        <p>Purpose-built tools make assessment simpler for students and administrators alike.</p>
      </div>
      <div class="row g-4">
        <?php
        $features = [
            ['bi-shield-lock', 'Secure Online Examinations', 'Protected student accounts and a reliable exam experience from start to submission.', 'home-feature-indigo'],
            ['bi-lightning-charge', 'Instant Result Generation', 'Get your score and a clear breakdown as soon as your examination is submitted.', 'home-feature-blue'],
            ['bi-graph-up-arrow', 'Performance Analytics', 'Follow score trends and understand your progress with helpful visual insights.', 'home-feature-violet'],
            ['bi-award', 'Digital Achievement Certificates', 'Earn a verifiable certificate when your result reaches an achievement level.', 'home-feature-amber'],
            ['bi-trophy', 'Leaderboards and Rankings', 'See how your performance grows and celebrate learning milestones along the way.', 'home-feature-cyan'],
            ['bi-sliders', 'Easy Exam Management', 'Administrators can create courses, manage questions, and review exam attempts.', 'home-feature-rose'],
        ];
        foreach ($features as [$icon, $title, $description, $color]):
        ?>
          <div class="col-md-6 col-lg-4" data-reveal>
            <article class="home-feature-card h-100">
              <span class="home-feature-icon <?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></span>
              <h3><?= e($title) ?></h3>
              <p><?= e($description) ?></p>
              <span class="home-feature-arrow" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="home-section home-courses-section" id="courses">
    <div class="container">
      <div class="home-section-heading home-section-heading-row" data-reveal>
        <div>
          <span class="home-section-kicker">Learn something new</span>
          <h2>Find your next <span class="home-gradient-text">challenge.</span></h2>
          <p>Explore published courses and choose a subject that sparks your curiosity.</p>
        </div>
        <a class="btn btn-outline-primary home-view-all" href="<?= e(base_url('exams.php')) ?>">View all courses <i class="bi bi-arrow-right ms-1"></i></a>
      </div>

      <?php if ($courses): ?>
        <div class="row g-4">
          <?php foreach ($courses as $course): ?>
            <div class="col-md-6 col-lg-4" data-reveal>
              <article class="home-course-card h-100">
                <div class="home-course-top">
                  <span class="home-course-category"><i class="bi bi-bookmark-fill"></i> <?= e($course['tag'] ?: 'General') ?></span>
                  <span class="home-course-icon"><i class="bi bi-journal-code"></i></span>
                </div>
                <h3><?= e($course['title']) ?></h3>
                <p><?= e($course['intro'] ?: 'Build your knowledge with this published course.') ?></p>
                <div class="home-course-meta">
                  <span><i class="bi bi-list-check"></i> <?= (int) $course['question_count'] ?> questions</span>
                  <span class="home-course-difficulty"><?= e($course['difficulty']) ?></span>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="home-course-empty" data-reveal>
          <i class="bi bi-journal-bookmark"></i>
          <p class="mb-0">Published courses will appear here. Check back soon.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="home-section home-steps-section" id="how">
    <div class="container">
      <div class="home-section-heading text-center" data-reveal>
        <span class="home-section-kicker">Simple from the start</span>
        <h2>Four steps to your <span class="home-gradient-text">next win.</span></h2>
        <p>Your learning journey is easy to begin and rewarding to complete.</p>
      </div>
      <div class="home-steps-grid">
        <?php
        $steps = [
            ['bi-person-plus', '01', 'Create your account', 'Set up your student account and make the platform yours.'],
            ['bi-search', '02', 'Browse available exams', 'Discover published courses by subject and choose what to learn.'],
            ['bi-pencil-square', '03', 'Attend and submit', 'Complete your timed examination and submit your answers securely.'],
            ['bi-graph-up-arrow', '04', 'See your progress', 'Review instant results and earn a certificate when eligible.'],
        ];
        foreach ($steps as $index => [$icon, $number, $title, $description]):
        ?>
          <article class="home-step-card" data-reveal>
            <div class="home-step-top"><span class="home-step-icon"><i class="bi <?= e($icon) ?>"></i></span><span class="home-step-number"><?= e($number) ?></span></div>
            <h3><?= e($title) ?></h3>
            <p><?= e($description) ?></p>
            <?php if ($index < 3): ?><span class="home-step-connector" aria-hidden="true"><i class="bi bi-arrow-right"></i></span><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="home-section home-achievements-section" id="achievements">
    <div class="container">
      <div class="row align-items-center g-5">
        <div class="col-lg-5" data-reveal>
          <span class="home-section-kicker">Your hard work, recognized</span>
          <h2 class="home-achievements-title">Every achievement has a <span class="home-gradient-text">mark of its own.</span></h2>
          <p class="home-achievements-copy">Reach the required score in a completed exam to earn a digital certificate. Each level celebrates the progress you have made.</p>
          <div class="home-no-award"><i class="bi bi-info-circle"></i><span>Scores below 35% do not receive a certificate.</span></div>
        </div>
        <div class="col-lg-7" data-reveal>
          <div class="home-award-list">
            <article class="home-award-card home-award-silver">
              <span class="home-award-medal"><i class="bi bi-award-fill"></i></span>
              <span class="home-award-name"><strong>Silver</strong><small>Achievement Certificate</small></span>
              <span class="home-award-range">35%–49.99%</span>
              <i class="bi bi-arrow-up-right home-award-arrow"></i>
            </article>
            <article class="home-award-card home-award-gold">
              <span class="home-award-medal"><i class="bi bi-award-fill"></i></span>
              <span class="home-award-name"><strong>Gold</strong><small>Achievement Certificate</small></span>
              <span class="home-award-range">50%–79.99%</span>
              <i class="bi bi-arrow-up-right home-award-arrow"></i>
            </article>
            <article class="home-award-card home-award-platinum">
              <span class="home-award-medal"><i class="bi bi-stars"></i></span>
              <span class="home-award-name"><strong>Platinum</strong><small>Achievement Certificate</small></span>
              <span class="home-award-range">80%–100%</span>
              <i class="bi bi-arrow-up-right home-award-arrow"></i>
            </article>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="home-section home-faq-section" id="faq">
    <div class="container">
      <div class="row g-5">
        <div class="col-lg-4" data-reveal>
          <span class="home-section-kicker">A few quick answers</span>
          <h2>Good to know <span class="home-gradient-text">before you begin.</span></h2>
          <p>Need more help? Our platform is designed to keep the exam experience clear at every step.</p>
          <a class="home-faq-contact" href="mailto:aashish@marketdoctorsonline.com">Contact the team <i class="bi bi-arrow-up-right"></i></a>
        </div>
        <div class="col-lg-8" data-reveal>
          <div class="accordion home-faq-accordion" id="homeFaq">
            <?php
            $faqs = [
                ['How can I register?', 'Choose Get Started or Create Student Account, fill in the registration form, and sign in with your new account.'],
                ['How can I attend an exam?', 'Sign in as a student, open Exams, choose a published course, and submit your answers before the timer ends.'],
                ['Are results generated automatically?', 'Yes. Submitted answers are checked against the saved answer key and your result is calculated automatically.'],
                ['How can I download certificates?', 'Eligible completed exams appear under My Certificates. Open a certificate to view or download its PDF.'],
                ['Can I retake an examination?', 'You can start an available published exam again. Each completed submission is saved as its own attempt.'],
            ];
            foreach ($faqs as $index => [$question, $answer]):
                $faqId = 'homeFaq' . ($index + 1);
            ?>
              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($faqId) ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="<?= e($faqId) ?>">
                    <span class="home-faq-number"><?= sprintf('%02d', $index + 1) ?></span><?= e($question) ?>
                  </button>
                </h3>
                <div id="<?= e($faqId) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#homeFaq">
                  <div class="accordion-body"><?= e($answer) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="home-cta-section">
    <div class="container">
      <div class="home-cta-card" data-reveal>
        <div class="home-cta-orb home-cta-orb-one"></div>
        <div class="home-cta-orb home-cta-orb-two"></div>
        <div class="home-cta-content">
          <span class="home-cta-kicker"><i class="bi bi-stars"></i> Your next step starts here</span>
          <h2>Ready to Test Your Knowledge?</h2>
          <p>Start your learning and examination journey today.</p>
          <div class="d-flex flex-wrap justify-content-center gap-3">
            <a class="btn btn-light btn-lg home-cta-primary" href="<?= e(base_url('register.php')) ?>">Create Student Account <i class="bi bi-arrow-up-right ms-1"></i></a>
            <a class="btn btn-outline-light btn-lg home-cta-secondary" href="<?= e(base_url('login.php')) ?>">Sign In <i class="bi bi-box-arrow-in-right ms-1"></i></a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
