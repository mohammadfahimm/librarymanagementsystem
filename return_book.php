<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$fine_rate = (float)get_setting('fine_rate_per_day', '10.00');
$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD']==='POST') {
  require_csrf();
  $borrow_id = (int)($_POST['borrow_id']??0);
  $return_date = $_POST['return_date'] ?? date('Y-m-d');
  $st = $db->prepare("SELECT br.*, b.Title FROM borrow br JOIN book b ON br.Book_ID=b.Book_ID WHERE br.Borrow_ID=?");
  $st->execute([$borrow_id]); $loan = $st->fetch();
  if ($loan && empty($loan['Return_Date'])) {
    $db->prepare("UPDATE borrow SET Return_Date=? WHERE Borrow_ID=?")->execute([$return_date, $borrow_id]);
    $db->prepare("UPDATE book SET Available_Copies = Available_Copies + 1 WHERE Book_ID=?")->execute([$loan['Book_ID']]);
    $info = calculate_fine($loan['Due_Date'], $return_date, $fine_rate);
    if ($info['is_overdue']) {
      try {
        $db->prepare("INSERT INTO fine (Borrow_ID, Amount, Reason, Payment_Status) VALUES (?,?,?,'Unpaid')")
           ->execute([$borrow_id, $info['fine_amount'], 'Returned '.$info['days_overdue'].' day(s) late']);
      } catch (Throwable $e) {
        $db->prepare("UPDATE fine SET Amount=?, Reason=?, Payment_Status='Unpaid' WHERE Borrow_ID=?")
           ->execute([$info['fine_amount'], 'Returned '.$info['days_overdue'].' day(s) late', $borrow_id]);
      }
      set_flash('warning', "'{$loan['Title']}' returned late. Fine \$".number_format($info['fine_amount'],2));
    } else {
      set_flash('success', "'{$loan['Title']}' returned on time.");
    }
    logActivity($user['id'],'Circulation',"Returned borrow #$borrow_id");
  }
  header('Location: return_book.php'); exit;
}

$sql = "SELECT br.*, b.Title AS Book_Title, b.ISBN, ".sqlNameConcat('m.First_Name','m.Last_Name')." AS Member_Name, m.Email
        FROM borrow br JOIN book b ON br.Book_ID=b.Book_ID JOIN member m ON br.Member_ID=m.Member_ID
        WHERE br.Return_Date IS NULL";
$params=[];
if ($search!=='') {
  $sql .= " AND (b.Title LIKE ? OR m.First_Name LIKE ? OR m.Last_Name LIKE ? OR m.Email LIKE ?)";
  $params=["%$search%","%$search%","%$search%","%$search%"];
}
$sql .= " ORDER BY br.Due_Date ASC";
$st=$db->prepare($sql); $st->execute($params); $loans=$st->fetchAll();
portal_header('Return Book', $user, 'return');
?>
<div class="panel">
  <div class="panel-head"><h4>Active Loans (<?= count($loans) ?>)</h4>
    <form method="GET"><input type="text" name="search" value="<?= esc($search) ?>" placeholder="Search…"><button class="btn btn-sm btn-primary">Search</button></form>
  </div>
  <div class="panel-body p-0">
    <table>
      <thead><tr><th>Book</th><th>Member</th><th>Borrowed</th><th>Due</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$loans): ?><tr><td colspan="6"><div class="empty-state"><p>No active loans.</p></div></td></tr>
      <?php else: foreach ($loans as $l):
        $late = isOverdue($l['Due_Date']);
        $days = $late ? (int)ceil((time()-strtotime($l['Due_Date']))/86400) : 0;
        $proj = $late ? round($days*$fine_rate,2) : 0; ?>
        <tr>
          <td><strong><?= esc($l['Book_Title']) ?></strong></td>
          <td><?= esc($l['Member_Name']) ?></td>
          <td><?= formatDate($l['Borrow_Date']) ?></td>
          <td><?= formatDate($l['Due_Date']) ?></td>
          <td><?= $late ? '<span class="badge badge-red">'.$days.'d overdue ($'.$proj.')</span>' : '<span class="badge badge-green">On time</span>' ?></td>
          <td>
            <form method="POST" onsubmit="return confirm('Confirm return?')"><?= csrf_field() ?>
              <input type="hidden" name="borrow_id" value="<?= (int)$l['Borrow_ID'] ?>">
              <input type="hidden" name="return_date" value="<?= date('Y-m-d') ?>">
              <button class="btn btn-sm <?= $late?'btn-danger':'btn-success' ?>" type="submit">Check In</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php portal_footer(); ?>
