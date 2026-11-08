<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$id = (int)($_GET['id']??0);
$st = $db->prepare("SELECT * FROM member WHERE Member_ID=?"); $st->execute([$id]); $m = $st->fetch();
if (!$m) { set_flash('danger','Not found'); header('Location: members.php'); exit; }
$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  foreach (['first_name', 'last_name', 'email', 'phone', 'section', 'batch'] as $field) {
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
      $db->prepare("UPDATE member SET First_Name=?,Last_Name=?,Email=?,Phone=?,Section=?,Batch=? WHERE Member_ID=?")
         ->execute([$fn,$ln,$em,$ph?:null,$sec?:null,$batch?:null,$id]);
      set_flash('success','Member updated.'); header('Location: members.php'); exit;
    } catch (PDOException $e) {
      if ($e->getCode() === '23000' || $e->getCode() === '19') {
        $errors[] = 'Email address is already assigned to another member.';
      } else {
        error_log('Member update failed: ' . $e->getMessage());
        $errors[] = 'Member could not be updated. Please try again.';
      }
    }
  }
  $m = array_merge($m, ['First_Name'=>$fn,'Last_Name'=>$ln,'Email'=>$em,'Phone'=>$ph,'Section'=>$sec,'Batch'=>$batch]);
}
portal_header('Edit Member', $user, 'members');
?>
<div class="panel" style="max-width:560px">
  <div class="panel-head"><h4>Edit Member #<?= $id ?></h4><a href="members.php" class="btn btn-sm">Back</a></div>
  <div class="panel-body">
    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error" style="display:block"><?= esc($error) ?></div>
    <?php endforeach; ?>
    <form method="POST">
      <div class="form-grid">
        <div class="f-group"><label>First Name</label><input name="first_name" maxlength="50" value="<?= esc($m['First_Name']) ?>" required></div>
        <div class="f-group"><label>Last Name</label><input name="last_name" maxlength="50" value="<?= esc($m['Last_Name']) ?>" required></div>
        <div class="f-group"><label>Email</label><input type="email" name="email" maxlength="100" value="<?= esc($m['Email']) ?>" required></div>
        <div class="f-group"><label>Phone</label><input name="phone" maxlength="20" value="<?= esc($m['Phone']??'') ?>"></div>
        <div class="f-group"><label>Section</label><input name="section" maxlength="20" value="<?= esc($m['Section']??'') ?>"></div>
        <div class="f-group"><label>Batch</label><input name="batch" maxlength="20" value="<?= esc($m['Batch']??'') ?>"></div>
      </div>
      <button class="btn btn-primary mt-16" type="submit">Save</button>
    </form>
  </div>
</div>
<?php portal_footer(); ?>
