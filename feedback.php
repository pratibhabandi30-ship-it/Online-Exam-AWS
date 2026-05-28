<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me      = current_user();
$errors  = [];
$ok      = false;

$old = [
    'name'    => $me['name']  ?? '',
    'email'   => $me['email'] ?? '',
    'subject' => '',
    'message' => '',
    'rating'  => '5',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['name']    = clean_string($_POST['name']    ?? '', 80);
    $old['email']   = strtolower(clean_string($_POST['email'] ?? '', 120));
    $old['subject'] = clean_string($_POST['subject'] ?? '', 200);
    $old['message'] = clean_string($_POST['message'] ?? '', 4000);
    $old['rating']  = (string) (int) ($_POST['rating'] ?? 5);
    $rating = max(1, min(5, (int) $old['rating']));

    if (mb_strlen($old['name'])    < 2)         $errors[] = 'Name is required.';
    if (!validate_email($old['email']))         $errors[] = 'Valid email is required.';
    if (mb_strlen($old['subject']) < 3)         $errors[] = 'Subject is too short.';
    if (mb_strlen($old['message']) < 10)        $errors[] = 'Please write a longer message.';

    if (!$errors) {
        $stmt = db()->prepare("INSERT INTO feedback (user_id,name,email,subject,message,rating) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$me['id'] ?? null, $old['name'], $old['email'], $old['subject'], $old['message'], $rating]);
        $ok = true;
        $old['subject'] = $old['message'] = '';
    }
}

$pageTitle = 'Feedback';
$activeNav = 'feedback';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
      <div class="card p-4 p-md-5">
        <h3 class="mb-1">Send us feedback</h3>
        <p class="text-muted">Found a bug or have an idea? Drop us a note.</p>

        <?php if ($ok): ?>
          <div class="alert alert-success"><i class="bi bi-check-circle"></i> Thanks! Your feedback has been recorded.</div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name</label>
              <input class="form-control" name="name" required value="<?= e($old['name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input class="form-control" type="email" name="email" required value="<?= e($old['email']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Subject</label>
              <input class="form-control" name="subject" required value="<?= e($old['subject']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Message</label>
              <textarea class="form-control" name="message" rows="5" data-autogrow required><?= e($old['message']) ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Rating</label>
              <select class="form-select" name="rating">
                <?php for ($i=5;$i>=1;$i--): ?>
                  <option value="<?= $i ?>" <?= $old['rating']==(string)$i?'selected':'' ?>><?= str_repeat('★',$i) . str_repeat('☆',5-$i) ?> (<?= $i ?>)</option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
          <button class="btn btn-primary mt-4 w-100"><i class="bi bi-send"></i> Send feedback</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
