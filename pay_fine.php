<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$fine_id = (int)($_GET['fine_id'] ?? 0);
$action = $_GET['action'] ?? 'pay';
if ($fine_id <= 0) { set_flash('danger','Invalid fine'); header('Location: fines.php'); exit; }
try {
    $st = $db->prepare("SELECT f.*, b.Title AS Book_Title FROM fine f JOIN borrow br ON f.Borrow_ID=br.Borrow_ID JOIN book b ON br.Book_ID=b.Book_ID WHERE f.Fine_ID=?");
    $st->execute([$fine_id]);
    $fine = $st->fetch();
    if (!$fine) { set_flash('danger','Fine not found'); header('Location: fines.php'); exit; }
    if (($fine['Payment_Status']??'') === 'Paid') { set_flash('info','Already paid'); header('Location: fines.php'); exit; }
    if ($action === 'waive') {
        $db->prepare("UPDATE fine SET Payment_Status='Paid', Paid_Date=?, Reason=CONCAT(COALESCE(Reason,''), ' [Waived]') WHERE Fine_ID=?")
           ->execute([date('Y-m-d'), $fine_id]);
        logActivity($user['id'], 'Fines', "Waived fine #$fine_id");
        set_flash('success', 'Fine waived.');
    } else {
        $db->prepare("UPDATE fine SET Payment_Status='Paid', Paid_Date=? WHERE Fine_ID=?")->execute([date('Y-m-d'), $fine_id]);
        logActivity($user['id'], 'Fines', "Paid fine #$fine_id");
        set_flash('success', 'Payment recorded.');
    }
} catch (Throwable $e) {
    set_flash('danger', $e->getMessage());
}
header('Location: fines.php');
exit;
