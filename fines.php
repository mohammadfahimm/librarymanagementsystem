<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();

$filter = $_GET['filter'] ?? '';
$sql = "SELECT f.*, b.Title AS Book_Title, br.Due_Date, br.Return_Date,
        " . sqlNameConcat('m.First_Name', 'm.Last_Name') . " AS Member_Name, m.Email
        FROM fine f
        JOIN borrow br ON f.Borrow_ID = br.Borrow_ID
        JOIN book b ON br.Book_ID = b.Book_ID
        JOIN member m ON br.Member_ID = m.Member_ID WHERE 1=1";
$params = [];
if ($filter === 'Unpaid' || $filter === 'Paid') {
    $sql .= " AND f.Payment_Status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY (f.Payment_Status = 'Unpaid') DESC, f.Fine_ID DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$fines = $stmt->fetchAll();
$unpaid = 0; $paid = 0;
$fineCount = 0;
$reportError = '';
try {
    $unpaid = (float)$db->query("SELECT COALESCE(SUM(Amount),0) FROM fine WHERE Payment_Status='Unpaid'")->fetchColumn();
    $paid = (float)$db->query("SELECT COALESCE(SUM(Amount),0) FROM fine WHERE Payment_Status='Paid'")->fetchColumn();
    $fineCount = (int)$db->query("SELECT COUNT(*) FROM fine")->fetchColumn();
} catch (Throwable $e) {
    error_log('Fine report summary failed: ' . $e->getMessage());
    $reportError = 'Fine summary is temporarily unavailable.';
}

portal_header('Fines & Penalties', $user, 'fines');
?>
<?php if ($reportError !== ''): ?>
  <div class="alert alert-error" style="display:block"><?= esc($reportError) ?></div>
<?php endif; ?>
<div class="stat-grid">
  <div class="stat-card" style="border-color:#c0392b"><div class="s-icon">⚠️</div><div><div class="s-val">$<?= number_format($unpaid,2) ?></div><div class="s-lbl">Unpaid</div></div></div>
  <div class="stat-card" style="border-color:#27ae60"><div class="s-icon">✅</div><div><div class="s-val">$<?= number_format($paid,2) ?></div><div class="s-lbl">Collected</div></div></div>
  <div class="stat-card"><div class="s-icon">💰</div><div><div class="s-val"><?= $fineCount ?></div><div class="s-lbl">Fine Records</div></div></div>
</div>
<div class="panel">
  <div class="panel-head"><h4>Fine Records</h4>
    <form method="GET"><select name="filter" onchange="this.form.submit()">
      <option value="">All</option>
      <option value="Unpaid" <?= $filter==='Unpaid'?'selected':'' ?>>Unpaid</option>
      <option value="Paid" <?= $filter==='Paid'?'selected':'' ?>>Paid</option>
    </select></form>
  </div>
  <div class="panel-body p-0">
    <table>
      <thead><tr><th>ID</th><th>Member</th><th>Book</th><th>Reason</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$fines): ?>
        <tr><td colspan="7"><div class="empty-state"><p>No fines recorded.</p></div></td></tr>
      <?php else: foreach ($fines as $f): $u = ($f['Payment_Status']??'')==='Unpaid'; ?>
        <tr>
          <td>#<?= (int)$f['Fine_ID'] ?></td>
          <td><?= esc($f['Member_Name']) ?><br><small><?= esc($f['Email']??'') ?></small></td>
          <td><?= esc($f['Book_Title']) ?></td>
          <td><?= esc($f['Reason']??'—') ?></td>
          <td><strong>$<?= number_format((float)$f['Amount'],2) ?></strong></td>
          <td><?= $u?'<span class="badge badge-red">Unpaid</span>':'<span class="badge badge-green">Paid</span>' ?></td>
          <td><?php if ($u && (float)$f['Amount']>0): ?>
            <a class="btn btn-sm btn-success" href="pay_fine.php?fine_id=<?= (int)$f['Fine_ID'] ?>&action=pay" onclick="return confirm('Confirm payment?')">Pay</a>
            <a class="btn btn-sm btn-gold" href="pay_fine.php?fine_id=<?= (int)$f['Fine_ID'] ?>&action=waive" onclick="return confirm('Waive fine?')">Waive</a>
          <?php else: ?>—<?php endif; ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php portal_footer(); ?>
