<?php
/**
 * Premier University Library — Product List / Book Catalog
 * Powered by PHP & MySQL
 */
require_once __DIR__ . '/config/db.php';
$db = getDB();
$user = getCurrentUser();

$category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');
$stock = trim($_GET['stock'] ?? ''); // available | out | all
$sort = trim($_GET['sort'] ?? 'newest'); // newest | title | available

$sql = "
  SELECT b.*, c.Category_Name, " . sqlNameConcat('a.First_Name', 'a.Last_Name') . " AS Author_Name
  FROM book b
  LEFT JOIN category c ON b.Category_ID = c.Category_ID
  LEFT JOIN writes w ON b.Book_ID = w.Book_ID
  LEFT JOIN author a ON w.Author_ID = a.Author_ID
  WHERE 1=1
";
$params = [];

// Multi-criteria: category AND search AND stock
if ($category !== '') {
    $sql .= " AND c.Category_Name = ?";
    $params[] = $category;
}
if ($search !== '') {
    $sql .= " AND (b.Title LIKE ? OR a.First_Name LIKE ? OR a.Last_Name LIKE ? OR b.ISBN LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($stock === 'available') {
    $sql .= " AND b.Available_Copies > 0";
} elseif ($stock === 'out') {
    $sql .= " AND b.Available_Copies <= 0";
}

switch ($sort) {
    case 'title':
        $sql .= " ORDER BY b.Title ASC";
        break;
    case 'available':
        $sql .= " ORDER BY b.Available_Copies DESC, b.Title ASC";
        break;
    default:
        $sql .= " ORDER BY b.Book_ID DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get list of categories for filter chips
$cats = $db->query("SELECT Category_Name, COUNT(b.Book_ID) as count FROM category c LEFT JOIN book b ON c.Category_ID = b.Category_ID GROUP BY c.Category_ID")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Premier University Library — Book Catalog</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
.nav-wrap{background:rgba(10,22,40,0.95);backdrop-filter:blur(10px);border-bottom:1px solid rgba(201,168,76,0.15);padding:0 40px;height:70px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.nav-wrap .brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
.nav-wrap .brand-text h1{font-family:'Playfair Display',serif;color:#fff;font-size:1rem;font-weight:700;}
.nav-wrap .brand-text p{color:rgba(255,255,255,0.4);font-size:0.62rem;text-transform:uppercase;letter-spacing:1.5px;}
.nav-links-row{display:flex;align-items:center;gap:6px;}
.nav-links-row a{color:rgba(255,255,255,0.65);text-decoration:none;font-size:0.82rem;font-weight:500;padding:6px 12px;border-radius:6px;transition:all 0.2s;}
.nav-links-row a:hover{color:var(--gold-light);background:rgba(255,255,255,0.07);}
.nav-links-row a.active{color:var(--gold);}
.page-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--royal) 60%,#0d2147 100%);padding:60px 20px;text-align:center;color:#fff;}
.page-hero h1{font-family:'Playfair Display',serif;font-size:clamp(1.8rem,4vw,2.8rem);margin-bottom:10px;}
.page-hero p{color:rgba(255,255,255,0.7);max-width:560px;margin:0 auto;font-size:0.95rem;}
.catalog-container{max-width:1160px;margin:40px auto;padding:0 20px;}

/* Search & Filters */
.filter-bar{display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;background:#fff;padding:16px 20px;border-radius:12px;border:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:28px;}
.search-input-box{display:flex;align-items:center;gap:10px;flex:1;min-width:260px;}
.search-input-box input{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.9rem;}
.cat-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;}
.cat-chip{padding:6px 14px;background:#fff;border:1px solid var(--border);border-radius:20px;font-size:0.82rem;font-weight:500;color:var(--text);text-decoration:none;transition:all 0.2s;}
.cat-chip:hover,.cat-chip.active{background:var(--navy);color:var(--gold-light);border-color:var(--navy);}

/* Book Grid */
.book-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:24px;}
.book-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.04);transition:all 0.25s;display:flex;flex-direction:column;}
.book-card:hover{transform:translateY(-4px);box-shadow:0 10px 25px rgba(0,0,0,0.08);}
.book-cover-wrap{height:180px;background:linear-gradient(135deg,var(--royal),var(--navy));display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;}
.book-cover-wrap img{width:100%;height:100%;object-fit:cover;display:block;position:relative;z-index:1;}
.book-cover-placeholder{font-size:3.5rem;}
.book-cat-tag{position:absolute;top:12px;left:12px;background:rgba(10,22,40,0.85);color:var(--gold-light);font-size:0.7rem;font-weight:700;padding:3px 10px;border-radius:12px;backdrop-filter:blur(4px);border:1px solid rgba(201,168,76,0.3);}
.book-details{padding:18px;display:flex;flex-direction:column;flex:1;}
.book-title{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700;color:var(--navy);line-height:1.3;margin-bottom:6px;}
.book-author{font-size:0.82rem;color:var(--muted);margin-bottom:12px;}
.book-meta-row{display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:12px;border-top:1px solid var(--border);font-size:0.78rem;}
.btn-detail{display:block;text-align:center;background:linear-gradient(135deg,var(--royal),var(--navy));color:#fff;padding:9px;border-radius:8px;text-decoration:none;font-size:0.82rem;font-weight:600;margin-top:12px;transition:background 0.2s;}
.btn-detail:hover{background:var(--navy);}
</style>
</head>
<body>
<nav class="nav-wrap">
  <a href="home.php" class="brand">
    <img src="images/puc_logo.png" alt="Logo" style="width:36px;height:36px;object-fit:contain;">
    <div class="brand-text">
      <h1>Premier University</h1>
      <p>Library Management</p>
    </div>
  </a>
  <div class="nav-links-row">
    <a href="home.php">Home</a>
    <a href="about.php">About</a>
    <a href="services.php">Services</a>
    <a href="product-list.php" class="active">Product List</a>
    <a href="product-details.php">Product Details</a>
    <a href="blog.php">Blog</a>
    <a href="faq.php">FAQ</a>
    <a href="contact.php">Contact</a>
    <?php if ($user): ?>
      <a href="<?= ($user['role']==='admin'||$user['role']==='librarian')?'admin.php':($user['role']==='teacher'?'teacher.php':'student.php') ?>" style="color:var(--gold-light);font-weight:600;">Dashboard</a>
    <?php else: ?>
      <a href="index.php?login=1" style="color:var(--gold-light);font-weight:600;">Login</a>
    <?php endif; ?>
  </div>
</nav>

<div class="page-hero">
  <h1>Library Book Catalog</h1>
  <p>Search and explore textbook collections, reference volumes, and research publications.</p>
</div>

<div class="catalog-container">
  <!-- Search form -->
  <form method="GET" action="product-list.php" class="filter-bar" style="flex-wrap:wrap;gap:10px;">
    <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= esc($category) ?>"><?php endif; ?>
    <div class="search-input-box">
      <span>🔍</span>
      <input type="text" name="search" placeholder="Search by title, author name, or ISBN…" value="<?= esc($search) ?>" maxlength="100">
    </div>
    <select name="stock" style="padding:8px 12px;border-radius:8px;border:1px solid var(--border);">
      <option value="" <?= $stock===''?'selected':'' ?>>All stock</option>
      <option value="available" <?= $stock==='available'?'selected':'' ?>>Available only</option>
      <option value="out" <?= $stock==='out'?'selected':'' ?>>Out of stock</option>
    </select>
    <select name="sort" style="padding:8px 12px;border-radius:8px;border:1px solid var(--border);">
      <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest first</option>
      <option value="title" <?= $sort==='title'?'selected':'' ?>>Title A–Z</option>
      <option value="available" <?= $sort==='available'?'selected':'' ?>>Most available</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
  </form>

  <!-- Category chips -->
  <div class="cat-chips">
    <a href="product-list.php?<?= http_build_query(array_filter(['search'=>$search,'stock'=>$stock,'sort'=>$sort])) ?>" class="cat-chip <?= $category==='' ? 'active' : '' ?>">All Books</a>
    <?php foreach ($cats as $c): ?>
      <a href="product-list.php?<?= http_build_query(array_filter(['category'=>$c['Category_Name'],'search'=>$search,'stock'=>$stock,'sort'=>$sort])) ?>" class="cat-chip <?= $category === $c['Category_Name'] ? 'active' : '' ?>">
        <?= esc($c['Category_Name']) ?> (<?= (int)$c['count'] ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Book Grid -->
  <div class="book-grid">
    <?php if (!empty($books)): ?>
      <?php foreach ($books as $b): ?>
        <div class="book-card">
          <div class="book-cover-wrap">
            <span class="book-cat-tag"><?= esc($b['Category_Name'] ?: 'General') ?></span>
            <?php
              $cover = trim((string)($b['Cover_Image'] ?? ''));
              $coverOk = $cover !== '' && (
                strpos($cover, 'http://') === 0 || strpos($cover, 'https://') === 0 ||
                file_exists(__DIR__ . '/' . ltrim(str_replace('\\', '/', $cover), '/'))
              );
            ?>
            <?php if ($cover !== ''): ?>
              <img src="<?= esc($cover) ?>" alt="<?= esc($b['Title']) ?>" onerror="this.onerror=null;this.style.display='none';var p=this.parentNode.querySelector('.book-cover-placeholder');if(p)p.style.display='flex';">
              <div class="book-cover-placeholder" style="display:none;position:absolute;inset:0;align-items:center;justify-content:center;">📖</div>
            <?php else: ?>
              <div class="book-cover-placeholder">📖</div>
            <?php endif; ?>
          </div>
          <div class="book-details">
            <h3 class="book-title"><?= esc($b['Title']) ?></h3>
            <p class="book-author">By <?= esc($b['Author_Name'] ?: 'Academic Faculty') ?></p>
            <div class="book-meta-row">
              <span>ISBN: <?= esc($b['ISBN'] ?: '—') ?></span>
              <span class="badge <?= $b['Available_Copies'] > 0 ? 'badge-green' : 'badge-red' ?>">
                <?= $b['Available_Copies'] > 0 ? $b['Available_Copies'] . ' Available' : 'Out of Stock' ?>
              </span>
            </div>
            <a href="product-details.php?id=<?= $b['Book_ID'] ?>" class="btn-detail">View Details &amp; Reserve →</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="grid-column:1/-1;text-align:center;padding:60px 20px;background:#fff;border-radius:12px;border:1px solid var(--border);">
        <div style="font-size:3rem;margin-bottom:12px;">🔍</div>
        <h3 style="font-family:'Playfair Display',serif;color:var(--navy);">No books found</h3>
        <p style="color:var(--muted);margin-top:6px;">Try adjusting your search criteria or category filter.</p>
        <a href="product-list.php" class="btn btn-gold btn-sm mt-16">Clear Filters</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<footer style="background:var(--navy);color:rgba(255,255,255,0.4);text-align:center;padding:30px;font-size:0.8rem;margin-top:80px;">
  &copy; 2026 Premier University IT &nbsp;|&nbsp; Designed &amp; Developed by <strong>Loop Infinity</strong>
</footer>

<script src="theme.js"></script>

<?php
if (file_exists(__DIR__ . '/config/popup.php')) {
    require_once __DIR__ . '/config/popup.php';
    if (function_exists('render_team_popup')) { render_team_popup(); }
}
?>
</body>
</html>
