<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$logs = [];
try {
  $logs = $db->query("SELECT a.*, u.Username, u.Full_Name FROM activity_logs a LEFT JOIN users u ON a.User_ID=u.User_ID ORDER BY a.Created_At DESC LIMIT 100")->fetchAll();
} catch (Throwable $e) {}
portal_header('Activity Log', $user, 'activity');
?>
<div class="panel">
  <div class="panel-head"><h4>System Audit Trail</h4></div>
  <div class="panel-body p-0">
    <table>
      <thead><tr><th>Time</th><th>User</th><th>Module</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
      <?php if (!$logs): ?><tr><td colspan="5"><div class="empty-state"><p>No logs.</p></div></td></tr>
      <?php else: foreach ($logs as $l): ?>
        <tr>
          <td><small><?= esc($l['Created_At']??'') ?></small></td>
          <td><?= esc($l['Full_Name']??$l['Username']??'System') ?></td>
          <td><span class="badge badge-blue"><?= esc($l['Target_Module']??$l['Action_Type']??'') ?></span></td>
          <td><?= esc($l['Action_Description']??$l['Details']??'') ?></td>
          <td><small><?= esc($l['IP_Address']??'—') ?></small></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php portal_footer(); ?>
