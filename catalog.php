<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$user = getCurrentUser();

$search = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

$sql = "SELECT b.*, c.Category_Name, " . sqlNameConcat('a.First_Name', 'a.Last_Name') . " AS Author_Name
        FROM book b
        LEFT JOIN category c ON b.Category_ID = c.Category_ID
        LEFT JOIN writes w ON b.Book_ID = w.Book_ID
        LEFT JOIN author a ON w.Author_ID = a.Author_ID
        WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (b.Title LIKE ? OR b.ISBN LIKE ? OR a.First_Name LIKE ? OR a.Last_Name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($category !== '') {
    $sql .= " AND c.Category_Name = ?";
    $params[] = $category;
}
$sql .= " ORDER BY b.Title ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();
$cats = $db->query("SELECT Category_Name FROM category ORDER BY Category_Name")->fetchAll(PDO::FETCH_COLUMN);

// Reserve hold
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reserve') {
    require_csrf();
    if (!$user) { header('Location: index.php'); exit; }
    $book_id = (int)($_POST['book_id'] ?? 0);
    $mid = $user['member_id'] ?? null;
    if (!$mid && !empty($user['email'])) {
        $st = $db->prepare("SELECT Member_ID FROM member WHERE Email = ?");
        $st->execute([$user['email']]);
        $mid = $st->fetchColumn() ?: null;
    }
    if ($book_id && $mid) {
        try {
            $exp = date('Y-m-d', strtotime('+7 days'));
            $db->prepare("INSERT INTO reservations (Book_ID, Member_ID, Expiry_Date, Status) VALUES (?, ?, ?, 'pending')")
               ->execute([$book_id, $mid, $exp]);
            set_flash('success', 'Hold placed successfully for 7 days.');
        } catch (Throwable $e) {
            set_flash('danger', 'Could not place hold.');
        }
    } else {
        set_flash('danger', 'Member account required to reserve.');
    }
    header('Location: catalog.php');
    exit;
}

if ($user) {
    portal_header('Book Catalog', $user, 'catalog');
} else {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Catalog — PUC Library</title><link rel="stylesheet" href="style.css"></head><body style="background:#f5f6fa;padding:20px;">';
}
?>
<div class="panel">
  <div class="panel-head"><h4>📚 Library Catalog</h4>
    <form method="GET" class="search-bar m-0" style="display:flex;gap:8px;">
      <input type="text" name="q" value="<?= esc($search) ?>" placeholder="Search title, author, ISBN…">
      <select name="category" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= esc($c) ?>" <?= $category===$c?'selected':'' ?>><?= esc($c) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm" type="submit">Search</button>
    </form>
  </div>
  <div class="panel-body p-0">
    <table>
      <thead><tr><th>#</th><th>Title</th><th>Author</th><th>Category</th><th>ISBN</th><th>Available</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$books): ?>
        <tr><td colspan="7"><div class="empty-state"><p>No books found.</p></div></td></tr>
      <?php else: foreach ($books as $b):
        $avail = (int)($b['Available_Copies'] ?? 0); ?>
        <tr>
          <td><?= (int)$b['Book_ID'] ?></td>
          <td><strong><?= esc($b['Title']) ?></strong></td>
          <td><?= esc($b['Author_Name'] ?: '—') ?></td>
          <td><?= esc($b['Category_Name'] ?? '—') ?></td>
          <td><?= esc($b['ISBN'] ?? '—') ?></td>
          <td><span class="badge <?= $avail>0?'badge-green':'badge-red' ?>"><?= $avail>0 ? $avail.' copies' : 'Out of stock' ?></span></td>
          <td>
            <?php if ($user && $avail < 1): ?>
              <form method="POST" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="action" value="reserve">
                <input type="hidden" name="book_id" value="<?= (int)$b['Book_ID'] ?>">
                <button class="btn btn-sm btn-gold" type="submit">Reserve</button>
              </form>
            <?php elseif ($user): ?>
              <span class="badge badge-blue">Available</span>
            <?php else: ?>
              <a class="btn btn-sm" href="index.php">Login to borrow</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php if ($user) portal_footer(); else echo '</body></html>'; ?>
