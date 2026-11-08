<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['student','teacher','contributor','admin']);
$db = getDB();
$mid = $user['member_id'] ?? null;
$loans = [];
if ($mid) {
  $st = $db->prepare("SELECT br.*, b.Title, b.ISBN, f.Amount AS Fine_Amount, f.Payment_Status
    FROM borrow br JOIN book b ON br.Book_ID=b.Book_ID
    LEFT JOIN fine f ON f.Borrow_ID=br.Borrow_ID
    WHERE br.Member_ID=? ORDER BY br.Borrow_Date DESC");
  $st->execute([$mid]); $loans = $st->fetchAll();
} elseif (is_staff()) {
  $loans = $db->query("SELECT br.*, b.Title, b.ISBN, ".sqlNameConcat('m.First_Name','m.Last_Name')." AS Member_Name, f.Amount AS Fine_Amount, f.Payment_Status
    FROM borrow br JOIN book b ON br.Book_ID=b.Book_ID JOIN member m ON br.Member_ID=m.Member_ID
    LEFT JOIN fine f ON f.Borrow_ID=br.Borrow_ID ORDER BY br.Borrow_Date DESC LIMIT 100")->fetchAll();
}
portal_header('My Borrowed Books', $user, 'loans');
?>
<div class="panel">
  <div class="panel-head"><h4>Borrowing History</h4><a href="catalog.php" class="btn btn-sm btn-primary">Browse Catalog</a></div>
  <div class="panel-body p-0">
    <table>
      <thead><tr><th>Book</th><?php if(is_staff()&&!$mid):?><th>Member</th><?php endif; ?><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$loans): ?><tr><td colspan="6"><div class="empty-state"><p>No loans found.</p></div></td></tr>
      <?php else: foreach ($loans as $l):
        $ret = !empty($l['Return_Date']); $late = !$ret && isOverdue($l['Due_Date']); ?>
        <tr>
          <td><strong><?= esc($l['Title']) ?></strong></td>
          <?php if(is_staff()&&!$mid): ?><td><?= esc($l['Member_Name']??'') ?></td><?php endif; ?>
          <td><?= formatDate($l['Borrow_Date']) ?></td>
          <td><?= formatDate($l['Due_Date']) ?></td>
          <td><?= $ret ? formatDate($l['Return_Date']) : '—' ?></td>
          <td>
            <?= $ret?'<span class="badge badge-green">Returned</span>':($late?'<span class="badge badge-red">Overdue</span>':'<span class="badge badge-gold">Active</span>') ?>
            <?php if (!empty($l['Fine_Amount']) && (float)$l['Fine_Amount']>0): ?>
              <span class="badge badge-red">$<?= number_format((float)$l['Fine_Amount'],2) ?> <?= esc($l['Payment_Status']??'') ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php portal_footer(); ?>
