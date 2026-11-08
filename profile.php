<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth();
$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_profile') {
  require_csrf();
  $name=trim($_POST['full_name']??''); $email=trim($_POST['email']??'');
  if ($name && $email) {
    $db->prepare("UPDATE users SET Full_Name=?, Email=? WHERE User_ID=?")->execute([$name,$email,$user['id']]);
    $_SESSION['full_name']=$name; $_SESSION['email']=$email;
    set_flash('success','Profile updated.');
    header('Location: profile.php'); exit;
  }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='change_password') {
  require_csrf();
  $cur=$_POST['current_password']??''; $new=$_POST['new_password']??''; $conf=$_POST['confirm_password']??'';
  $st=$db->prepare("SELECT Password FROM users WHERE User_ID=?"); $st->execute([$user['id']]); $hash=$st->fetchColumn();
  $ok = password_verify($cur, $hash) || $cur === $hash;
  if (!$ok) set_flash('danger','Current password incorrect.');
  elseif (strlen($new)<6) set_flash('danger','Password too short.');
  elseif ($new!==$conf) set_flash('danger','Passwords do not match.');
  else {
    $db->prepare("UPDATE users SET Password=? WHERE User_ID=?")->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);
    set_flash('success','Password changed.');
  }
  header('Location: profile.php'); exit;
}
$u = getCurrentUser();
portal_header('My Profile', $u, 'profile');
?>
<div class="grid-2-col">
  <div class="panel">
    <div class="panel-head"><h4>Identity</h4></div>
    <div class="panel-body">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="update_profile">
        <div class="f-group"><label>Username</label><input value="<?= esc($u['username']) ?>" disabled></div>
        <div class="f-group"><label>Role</label><input value="<?= esc($u['role']) ?>" disabled></div>
        <div class="f-group"><label>Full Name</label><input name="full_name" value="<?= esc($u['name']) ?>" required></div>
        <div class="f-group"><label>Email</label><input type="email" name="email" value="<?= esc($u['email']) ?>" required></div>
        <button class="btn btn-primary mt-16" type="submit">Save Profile</button>
      </form>
    </div>
  </div>
  <div class="panel">
    <div class="panel-head"><h4>Change Password</h4></div>
    <div class="panel-body">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="change_password">
        <div class="f-group"><label>Current Password</label><input type="password" name="current_password" required></div>
        <div class="f-group"><label>New Password</label><input type="password" name="new_password" minlength="6" required></div>
        <div class="f-group"><label>Confirm</label><input type="password" name="confirm_password" required></div>
        <button class="btn btn-gold mt-16" type="submit">Update Password</button>
      </form>
    </div>
  </div>
</div>
<?php portal_footer(); ?>
