<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['student','teacher','contributor','admin','librarian']);
$db = getDB();
$mid = $user['member_id'] ?? null;

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cancel') {
  require_csrf();
  $rid = (int)($_POST['reservation_id']??0);
  if (is_staff()) {
    $db->prepare("UPDATE reservations SET Status='cancelled' WHERE Reservation_ID=?")->execute([$rid]);
  } else {
    $db->prepare("UPDATE reservations SET Status='cancelled' WHERE Reservation_ID=? AND Member_ID=?")->execute([$rid,$mid]);
  }
  set_flash('success','Hold cancelled.');
  header('Location: my_reservations.php'); exit;
}

$reservations = [];
try {
  if ($mid) {
    $st = $db->prepare("SELECT r.*, b.Title, b.ISBN, c.Category_Name FROM reservations r JOIN book b ON r.Book_ID=b.Book_ID LEFT JOIN category c ON b.Category_ID=c.Category_ID WHERE r.Member_ID=? ORDER BY r.Reservation_Date DESC");
    $st->execute([$mid]); $reservations = $st->fetchAll();
  } elseif (is_staff()) {
    $reservations = $db->query("SELECT r.*, b.Title, b.ISBN, c.Category_Name, ".sqlNameConcat('m.First_Name','m.Last_Name')." AS Member_Name
      FROM reservations r JOIN book b ON r.Book_ID=b.Book_ID LEFT JOIN member m ON r.Member_ID=m.Member_ID LEFT JOIN category c ON b.Category_ID=c.Category_ID
      ORDER BY r.Reservation_Date DESC LIMIT 100")->fetchAll();
  }
} catch (Throwable $e) {}
portal_header('My Reservations', $user, 'reserves');
?>
<div class="panel">
  <div class="panel-head"><h4>Book Holds</h4><a href="catalog.php" class="btn btn-sm btn-primary">Browse to Hold</a></div>
  <div class="panel-body p-0">
    <table>
      <thead><tr><th>Book</th><?php if(is_staff()&&!$mid):?><th>Member</th><?php endif;?><th>Category</th><th>Requested</th><th>Expiry</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$reservations): ?><tr><td colspan="7"><div class="empty-state"><p>No holds.</p></div></td></tr>
      <?php else: foreach ($reservations as $r): ?>
        <tr>
          <td><strong><?= esc($r['Title']) ?></strong></td>
          <?php if(is_staff()&&!$mid): ?><td><?= esc($r['Member_Name']??'') ?></td><?php endif; ?>
          <td><?= esc($r['Category_Name']??'—') ?></td>
          <td><?= formatDate($r['Reservation_Date']??'') ?></td>
          <td><?= formatDate($r['Expiry_Date']??'') ?></td>
          <td><span class="badge badge-gold"><?= esc($r['Status']??'pending') ?></span></td>
          <td><?php if (($r['Status']??'')==='pending'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Cancel hold?')"><?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="reservation_id" value="<?= (int)$r['Reservation_ID'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Cancel</button>
            </form>
          <?php else: ?>—<?php endif; ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php portal_footer(); ?>
