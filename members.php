<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$search = trim($_GET['search'] ?? '');
$sql = "SELECT m.*,
  (SELECT COUNT(*) FROM borrow b WHERE b.Member_ID=m.Member_ID AND b.Return_Date IS NULL) AS active_borrows,
  (SELECT COUNT(*) FROM borrow b WHERE b.Member_ID=m.Member_ID) AS total_borrows
  FROM member m WHERE 1=1";
$params = [];
if ($search !== '') {
  $sql .= " AND (m.First_Name LIKE ? OR m.Last_Name LIKE ? OR m.Email LIKE ? OR m.Phone LIKE ?)";
  $params = ["%$search%","%$search%","%$search%","%$search%"];
}
$sql .= " ORDER BY m.Member_ID DESC";
$st = $db->prepare($sql); $st->execute($params); $members = $st->fetchAll();
portal_header('Library Members', $user, 'members');
?>
<div class="panel">
  <div class="panel-head"><h4>Members (<?= count($members) ?>)</h4>
    <div style="display:flex;gap:8px;">
      <form method="GET" class="search-bar m-0"><input type="text" name="search" value="<?= esc($search) ?>" placeholder="Search name, email…"><button class="btn btn-sm btn-primary" type="submit">Filter</button></form>
      <a class="btn btn-primary btn-sm" href="member_add.php">+ Add Member</a>
    </div>
  </div>
  <div class="panel-body p-0">
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Contact</th><th>Section/Batch</th><th>Active</th><th>Total</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$members): ?><tr><td colspan="7"><div class="empty-state"><p>No members.</p></div></td></tr>
      <?php else: foreach ($members as $m): ?>
        <tr>
          <td>#<?= (int)$m['Member_ID'] ?></td>
          <td><strong><?= esc($m['First_Name'].' '.$m['Last_Name']) ?></strong></td>
          <td><?= esc($m['Email']) ?><br><small><?= esc($m['Phone']??'') ?></small></td>
          <td><?= esc(($m['Section']??'—').' / '.($m['Batch']??'—')) ?></td>
          <td><?= (int)$m['active_borrows'] ?></td>
          <td><?= (int)$m['total_borrows'] ?></td>
          <td>
            <a class="btn btn-sm btn-gold" href="issue_book.php?member_id=<?= (int)$m['Member_ID'] ?>">Issue</a>
            <a class="btn btn-sm" href="member_edit.php?id=<?= (int)$m['Member_ID'] ?>">Edit</a>
            <a class="btn btn-sm btn-danger" href="member_delete.php?id=<?= (int)$m['Member_ID'] ?>" onclick="return confirm('Delete member?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php portal_footer(); ?>
