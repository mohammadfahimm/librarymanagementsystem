<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$loan_days = (int)get_setting('loan_duration_days', '14');
$max_books = (int)get_setting('max_books_per_member', '5');
$pre_m = (int)($_GET['member_id']??0);
$pre_b = (int)($_GET['book_id']??0);
$members = $db->query("SELECT Member_ID, First_Name, Last_Name, Email FROM member ORDER BY First_Name")->fetchAll();
$books = $db->query("SELECT Book_ID, Title, ISBN, Available_Copies FROM book WHERE Available_Copies > 0 ORDER BY Title")->fetchAll();
$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  require_csrf();
  $mid=(int)($_POST['member_id']??0); $bid=(int)($_POST['book_id']??0);
  $bdate=$_POST['borrow_date']??date('Y-m-d');
  $ddate=$_POST['due_date']??date('Y-m-d', strtotime("+$loan_days days"));
  if ($mid<=0||$bid<=0) $errors[]='Select member and book.';
  $cnt=$db->prepare("SELECT COUNT(*) FROM borrow WHERE Member_ID=? AND Return_Date IS NULL"); $cnt->execute([$mid]);
  if ((int)$cnt->fetchColumn() >= $max_books) $errors[]="Member reached max of $max_books active loans.";
  $av=$db->prepare("SELECT Available_Copies FROM book WHERE Book_ID=?"); $av->execute([$bid]);
  if ((int)$av->fetchColumn() < 1) $errors[]='Book not available.';
  if (!$errors) {
    $db->prepare("INSERT INTO borrow (Member_ID,Book_ID,Borrow_Date,Due_Date) VALUES (?,?,?,?)")->execute([$mid,$bid,$bdate,$ddate]);
    $db->prepare("UPDATE book SET Available_Copies = Available_Copies - 1 WHERE Book_ID=?")->execute([$bid]);
    logActivity($user['id'],'Circulation',"Issued book #$bid to member #$mid");
    set_flash('success','Book issued. Due '.formatDate($ddate));
    header('Location: return_book.php'); exit;
  }
}
portal_header('Issue Book', $user, 'issue');
?>
<div class="panel" style="max-width:600px">
  <div class="panel-head"><h4>Issue Circulation Loan</h4></div>
  <div class="panel-body">
    <?php foreach ($errors as $e) echo '<div class="alert alert-error" style="display:block">'.esc($e).'</div>'; ?>
    <form method="POST"><?= csrf_field() ?>
      <div class="f-group"><label>Member *</label>
        <select name="member_id" required><option value="">Choose…</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= (int)$m['Member_ID'] ?>" <?= $pre_m==$m['Member_ID']?'selected':'' ?>><?= esc($m['First_Name'].' '.$m['Last_Name'].' ('.$m['Email'].')') ?></option>
        <?php endforeach; ?>
        </select>
      </div>
      <div class="f-group"><label>Book *</label>
        <select name="book_id" required><option value="">Choose…</option>
        <?php foreach ($books as $b): ?>
          <option value="<?= (int)$b['Book_ID'] ?>" <?= $pre_b==$b['Book_ID']?'selected':'' ?>><?= esc($b['Title'].' — '.$b['Available_Copies'].' avail.') ?></option>
        <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grid">
        <div class="f-group"><label>Borrow Date</label><input type="date" name="borrow_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="f-group"><label>Due Date</label><input type="date" name="due_date" value="<?= date('Y-m-d', strtotime("+$loan_days days")) ?>"></div>
      </div>
      <button class="btn btn-primary mt-16" type="submit">Issue Loan</button>
    </form>
  </div>
</div>
<?php portal_footer(); ?>
