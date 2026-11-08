<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$id = (int)($_GET['id']??0);
$st = $db->prepare("SELECT First_Name,Last_Name FROM member WHERE Member_ID=?"); $st->execute([$id]); $m=$st->fetch();
if (!$m) { set_flash('danger','Not found'); header('Location: members.php'); exit; }
$active = (int)$db->prepare("SELECT COUNT(*) FROM borrow WHERE Member_ID=? AND Return_Date IS NULL")->execute([$id]) ?: 0;
$c = $db->prepare("SELECT COUNT(*) FROM borrow WHERE Member_ID=? AND Return_Date IS NULL"); $c->execute([$id]);
if ((int)$c->fetchColumn() > 0) {
  set_flash('danger', 'Cannot delete: member has active loans.');
  header('Location: members.php'); exit;
}
$db->prepare("DELETE FROM member WHERE Member_ID=?")->execute([$id]);
logActivity($user['id'],'Members','Deleted member #'.$id);
set_flash('success', "Member {$m['First_Name']} {$m['Last_Name']} removed.");
header('Location: members.php'); exit;
