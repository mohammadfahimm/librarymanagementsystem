<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$form = [
  'library_name' => get_setting('library_name', 'Premier University Library'),
  'loan_duration_days' => get_setting('loan_duration_days', '14'),
  'fine_rate_per_day' => get_setting('fine_rate_per_day', '10'),
  'max_books_per_member' => get_setting('max_books_per_member', '5'),
  'contact_email' => get_setting('contact_email', 'library@puc.ac.bd'),
  'contact_phone' => get_setting('contact_phone', ''),
  'library_hours' => get_setting('library_hours', 'Mon–Fri: 8am–10pm'),
  'allow_reservations' => get_setting('allow_reservations', 'yes'),
];
$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  require_csrf();
  foreach (['library_name', 'loan_duration_days', 'fine_rate_per_day', 'max_books_per_member', 'contact_email', 'contact_phone', 'library_hours'] as $field) {
    if (isset($_POST[$field]) && !is_string($_POST[$field])) {
      $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is invalid.';
    }
  }
  foreach (['library_name', 'loan_duration_days', 'fine_rate_per_day', 'max_books_per_member', 'contact_email', 'contact_phone', 'library_hours'] as $field) {
    if (isset($_POST[$field]) && is_string($_POST[$field])) {
      $form[$field] = trim($_POST[$field]);
    }
  }
  $validator = validate_circulation_settings(
    $form['loan_duration_days'],
    $form['fine_rate_per_day'],
    $form['max_books_per_member']
  );
  $errors = array_merge($errors, $validator->errors());
  if (!$errors) {
  $pairs = [
    'library_name' => $form['library_name'],
    'loan_duration_days' => $form['loan_duration_days'],
    'fine_rate_per_day' => $form['fine_rate_per_day'],
    'max_books_per_member' => $form['max_books_per_member'],
    'allow_reservations' => isset($_POST['allow_reservations']) ? 'yes' : 'no',
    'contact_email' => $form['contact_email'],
    'contact_phone' => $form['contact_phone'],
    'library_hours' => $form['library_hours'],
  ];
  foreach ($pairs as $k=>$v) {
    try {
      $db->prepare("INSERT INTO settings (Setting_Key, Setting_Value) VALUES (?,?) ON DUPLICATE KEY UPDATE Setting_Value=VALUES(Setting_Value)")->execute([$k,$v]);
    } catch (Throwable $e) {
      $db->prepare("INSERT OR REPLACE INTO settings (Setting_Key, Setting_Value) VALUES (?,?)")->execute([$k,$v]);
    }
  }
  logActivity($user['id'],'Settings','Updated policies');
  set_flash('success','Settings saved.');
  header('Location: settings.php'); exit;
  }
}
portal_header('Library Settings', $user, 'settings');
?>
<div class="panel" style="max-width:700px">
  <div class="panel-head"><h4>Circulation Policies</h4></div>
  <div class="panel-body">
    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error" style="display:block"><?= esc($error) ?></div>
    <?php endforeach; ?>
    <form method="POST"><?= csrf_field() ?>
      <div class="form-grid">
        <div class="f-group"><label>Library Name</label><input name="library_name" value="<?= esc($form['library_name']) ?>"></div>
        <div class="f-group"><label>Loan Days</label><input type="number" name="loan_duration_days" min="1" max="180" value="<?= esc($form['loan_duration_days']) ?>" required></div>
        <div class="f-group"><label>Fine $/day</label><input type="number" step="0.5" min="0" name="fine_rate_per_day" value="<?= esc($form['fine_rate_per_day']) ?>" required></div>
        <div class="f-group"><label>Max Books</label><input type="number" name="max_books_per_member" min="1" max="50" value="<?= esc($form['max_books_per_member']) ?>" required></div>
        <div class="f-group"><label>Email</label><input type="email" name="contact_email" value="<?= esc($form['contact_email']) ?>"></div>
        <div class="f-group"><label>Phone</label><input name="contact_phone" value="<?= esc($form['contact_phone']) ?>"></div>
        <div class="f-group" style="grid-column:1/-1"><label>Hours</label><input name="library_hours" value="<?= esc($form['library_hours']) ?>"></div>
        <div class="f-group"><label><input type="checkbox" name="allow_reservations" value="yes" <?= $form['allow_reservations']==='yes'?'checked':'' ?>> Allow Reservations</label></div>
      </div>
      <button class="btn btn-primary mt-16" type="submit">Save Settings</button>
    </form>
  </div>
</div>
<?php portal_footer(); ?>
