<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin','librarian']);
$db = getDB();
$id=(int)($_GET['id']??0);
$st=$db->prepare("SELECT Title FROM book WHERE Book_ID=?"); $st->execute([$id]); $t=$st->fetchColumn();
if(!$t){ set_flash('danger','Not found'); header('Location: books.php'); exit; }
$c=$db->prepare("SELECT COUNT(*) FROM borrow WHERE Book_ID=? AND Return_Date IS NULL"); $c->execute([$id]);
if((int)$c->fetchColumn()>0){ set_flash('danger','Cannot delete: book is on loan.'); header('Location: books.php'); exit; }
$db->prepare("DELETE FROM book WHERE Book_ID=?")->execute([$id]);
logActivity($user['id'],'Books',"Deleted $t");
set_flash('success',"Deleted '$t'.");
header('Location: books.php'); exit;
