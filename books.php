<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin','librarian']);
$db = getDB();
$search=trim($_GET['search']??''); $cat=trim($_GET['category']??'');
$sql="SELECT b.*, c.Category_Name, ".sqlNameConcat('a.First_Name','a.Last_Name')." AS Author_Name FROM book b
 LEFT JOIN category c ON b.Category_ID=c.Category_ID LEFT JOIN writes w ON b.Book_ID=w.Book_ID LEFT JOIN author a ON w.Author_ID=a.Author_ID WHERE 1=1";
$params=[];
if($search!==''){ $sql.=" AND (b.Title LIKE ? OR b.ISBN LIKE ?)"; $params=["%$search%","%$search%"]; }
if($cat!==''){ $sql.=" AND c.Category_Name=?"; $params[]=$cat; }
$sql.=" ORDER BY b.Book_ID DESC";
$st=$db->prepare($sql); $st->execute($params); $books=$st->fetchAll();
$cats=$db->query("SELECT Category_Name FROM category")->fetchAll(PDO::FETCH_COLUMN);
portal_header('Book Inventory', $user, 'books');
?>
<div class="panel">
  <div class="panel-head"><h4>Books (<?= count($books) ?>)</h4>
    <div style="display:flex;gap:8px">
      <form method="GET" style="display:flex;gap:6px">
        <input name="search" value="<?= esc($search) ?>" placeholder="Search…">
        <select name="category" onchange="this.form.submit()"><option value="">All</option>
          <?php foreach($cats as $c):?><option <?= $cat===$c?'selected':'' ?>><?= esc($c) ?></option><?php endforeach;?>
        </select>
        <button class="btn btn-sm btn-primary">Go</button>
      </form>
      <a class="btn btn-sm btn-primary" href="book_add.php">+ Add Book</a>
    </div>
  </div>
  <div class="panel-body p-0">
    <table>
      <thead><tr><th>#</th><th>Title</th><th>Author</th><th>Category</th><th>Qty</th><th>Avail</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($books as $b): ?>
        <tr>
          <td><?= (int)$b['Book_ID'] ?></td>
          <td><strong><?= esc($b['Title']) ?></strong><br><small><?= esc($b['ISBN']??'') ?></small></td>
          <td><?= esc($b['Author_Name']?:'—') ?></td>
          <td><?= esc($b['Category_Name']??'') ?></td>
          <td><?= (int)($b['Quantity']??0) ?></td>
          <td><span class="badge <?= (int)$b['Available_Copies']>0?'badge-green':'badge-red' ?>"><?= (int)$b['Available_Copies'] ?></span></td>
          <td>
            <a class="btn btn-sm btn-gold" href="issue_book.php?book_id=<?= (int)$b['Book_ID'] ?>">Issue</a>
            <a class="btn btn-sm" href="book_edit.php?id=<?= (int)$b['Book_ID'] ?>">Edit</a>
            <a class="btn btn-sm btn-danger" href="book_delete.php?id=<?= (int)$b['Book_ID'] ?>" onclick="return confirm('Delete?')">Del</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php portal_footer(); ?>
