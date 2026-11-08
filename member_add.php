<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$errors = []; $fn=''; $ln=''; $em=''; $ph=''; $sec=''; $batch='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $fields = ['first_name', 'last_name', 'email', 'phone', 'section', 'batch'];
  foreach ($fields as $field) {
    if (isset($_POST[$field]) && !is_string($_POST[$field])) {
      $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is invalid.';
    }
  }
  $fn=trim(is_string($_POST['first_name']??null)?$_POST['first_name']:'');
  $ln=trim(is_string($_POST['last_name']??null)?$_POST['last_name']:'');
  $em=trim(is_string($_POST['email']??null)?$_POST['email']:'');
  $ph=trim(is_string($_POST['phone']??null)?$_POST['phone']:'');
  $sec=trim(is_string($_POST['section']??null)?$_POST['section']:'');
  $batch=trim(is_string($_POST['batch']??null)?$_POST['batch']:'');
  $validator = new Validator();
  $validator->required($fn, 'First name')->length($fn, 'First name', 1, 50)
    ->required($ln, 'Last name')->length($ln, 'Last name', 1, 50)
    ->email($em, 'Email')
    ->phone($ph, true)
    ->length($sec, 'Section', 0, 20)
    ->length($batch, 'Batch', 0, 20);
  $errors = array_merge($errors, $validator->errors());
  if (!$errors) {
    try {
      $db->prepare("INSERT INTO member (First_Name,Last_Name,Email,Phone,Membership_Date,Status,Section,Batch) VALUES (?,?,?,?,?,'Active',?,?)")
         ->execute([$fn,$ln,$em,$ph?:null,date('Y-m-d'),$sec?:null,$batch?:null]);
      logActivity($user['id'],'Members',"Added $fn $ln");
      set_flash('success',"Member $fn $ln registered.");
      header('Location: members.php'); exit;
    } catch (Throwable $e) { $errors[]=$e->getMessage(); }
  }
}
portal_header('Add Member', $user, 'members');
?>
<div class="panel" style="max-width:560px">
  <div class="panel-head"><h4>Register New Member</h4><a href="members.php" class="btn btn-sm">Back</a></div>
  <div class="panel-body">
    <?php foreach ($errors as $e) echo '<div class="alert alert-error" style="display:block">'.esc($e).'</div>'; ?>
    <form method="POST">
      <div class="form-grid">
        <div class="f-group"><label>First Name *</label><input name="first_name" maxlength="50" value="<?= esc($fn) ?>" required></div>
        <div class="f-group"><label>Last Name *</label><input name="last_name" maxlength="50" value="<?= esc($ln) ?>" required></div>
        <div class="f-group"><label>Email *</label><input type="email" name="email" maxlength="100" value="<?= esc($em) ?>" required></div>
        <div class="f-group"><label>Phone</label><input name="phone" maxlength="20" value="<?= esc($ph) ?>"></div>
        <div class="f-group"><label>Section</label><input name="section" maxlength="20" value="<?= esc($sec) ?>"></div>
        <div class="f-group"><label>Batch</label><input name="batch" maxlength="20" value="<?= esc($batch) ?>"></div>
      </div>
      <button class="btn btn-primary mt-16" type="submit">Register Member</button>
    </form>
  </div>
</div>
<?php portal_footer(); ?>
