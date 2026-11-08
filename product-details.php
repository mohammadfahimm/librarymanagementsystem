<?php
/**
 * Premier University Library — Product Details / Book Details
 * Powered by PHP & MySQL
 */
require_once __DIR__ . '/config/db.php';
$db = getDB();
$user = getCurrentUser();

$book_id = (int)($_GET['id'] ?? 1);

// Fetch book details
$stmt = $db->prepare("
  SELECT b.*, c.Category_Name, " . sqlNameConcat('a.First_Name', 'a.Last_Name') . " AS Author_Name
  FROM book b
  LEFT JOIN category c ON b.Category_ID = c.Category_ID
  LEFT JOIN writes w ON b.Book_ID = w.Book_ID
  LEFT JOIN author a ON w.Author_ID = a.Author_ID
  WHERE b.Book_ID = ?
");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    // If book not found, fallback to first available
    $book = $db->query("
      SELECT b.*, c.Category_Name, " . sqlNameConcat('a.First_Name', 'a.Last_Name') . " AS Author_Name
      FROM book b
      LEFT JOIN category c ON b.Category_ID = c.Category_ID
      LEFT JOIN writes w ON b.Book_ID = w.Book_ID
      LEFT JOIN author a ON w.Author_ID = a.Author_ID
      LIMIT 1
    ")->fetch();
}

$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reserve') {
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    // Resolve Member_ID (create if needed)
    $member_id = $user['member_id'];
    if (!$member_id) {
        $email = $user['email'] ?: ($user['username'] . '@puc.ac.bd');
        $mStmt = $db->prepare("SELECT Member_ID FROM member WHERE Email = ?");
        $mStmt->execute([$email]);
        $found = $mStmt->fetch();
        if ($found) {
            $member_id = (int)$found['Member_ID'];
        } else {
            $parts = explode(' ', $user['name'], 2);
            $db->prepare("INSERT INTO member (First_Name, Last_Name, Email, Membership_Date, Status) VALUES (?, ?, ?, ?, 'Active')")
               ->execute([$parts[0], $parts[1] ?? 'Student', $email, date('Y-m-d')]);
            $member_id = (int)$db->lastInsertId();
        }
        $_SESSION['member_id'] = $member_id;
        if (!empty($user['id'])) {
            try {
                $db->prepare("UPDATE users SET Member_ID = ? WHERE User_ID = ? AND (Member_ID IS NULL OR Member_ID = 0)")
                   ->execute([$member_id, $user['id']]);
            } catch (\Throwable $e) {}
        }
    }
    if ($book['Available_Copies'] > 0 && $member_id) {
        $borrowDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+14 days'));
        $db->prepare("INSERT INTO borrow (Member_ID, Book_ID, Borrow_Date, Due_Date) VALUES (?, ?, ?, ?)")
           ->execute([$member_id, $book['Book_ID'], $borrowDate, $dueDate]);
        $db->prepare("UPDATE book SET Available_Copies = Available_Copies - 1 WHERE Book_ID = ?")->execute([$book['Book_ID']]);
        $book['Available_Copies']--;
        $alert = "Book successfully reserved and checked out to your account! Due date is in 14 days.";
    } else {
        $alert = "Sorry, this book is currently out of stock.";
    }
}

// Fetch related books
$related = [];
if ($book) {
    $rStmt = $db->prepare("SELECT * FROM book WHERE Category_ID = ? AND Book_ID != ? LIMIT 3");
    $rStmt->execute([$book['Category_ID'], $book['Book_ID']]);
    $related = $rStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($book['Title'] ?? 'Book Details') ?> — Premier University Library</title>
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
.details-wrap{max-width:1100px;margin:40px auto;padding:0 20px;}
.breadcrumb{margin-bottom:20px;font-size:0.82rem;color:var(--muted);}
.breadcrumb a{color:var(--royal);text-decoration:none;}
.book-showcase{background:#fff;border-radius:16px;border:1px solid var(--border);padding:40px;box-shadow:0 4px 24px rgba(0,0,0,0.05);display:grid;grid-template-columns:320px 1fr;gap:40px;}
.book-cover-large{background:linear-gradient(135deg,var(--navy),var(--royal));border-radius:12px;height:420px;display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;color:#fff;padding:20px;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,0.15);}
.book-cover-large .big-icon{font-size:5rem;margin-bottom:20px;}
.book-info-col h1{font-family:'Playfair Display',serif;font-size:2.2rem;color:var(--navy);font-weight:900;line-height:1.2;margin-bottom:10px;}
.author-line{font-size:1rem;color:var(--muted);margin-bottom:20px;}
.author-line strong{color:var(--royal);}
.badge-row{display:flex;gap:10px;align-items:center;margin-bottom:24px;}
.specs-table{width:100%;margin-bottom:24px;border-collapse:collapse;}
.specs-table td{padding:10px 0;border-bottom:1px solid var(--border);font-size:0.88rem;}
.specs-table td:first-child{color:var(--muted);width:140px;}
.specs-table td:last-child{color:var(--navy);font-weight:600;}
.action-box{background:#f8f9fb;border-radius:12px;padding:20px;border:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.related-section{margin-top:50px;}
.related-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-top:20px;}
@media(max-width:800px){
  .book-showcase{grid-template-columns:1fr;}
  .book-cover-large{height:280px;}
}
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
    <a href="product-list.php">Product List</a>
    <a href="product-details.php" class="active">Product Details</a>
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

<div class="details-wrap">
  <div class="breadcrumb">
    <a href="home.php">Home</a> &gt; <a href="product-list.php">Book Catalog</a> &gt; <span><?= esc($book['Title'] ?? 'Book') ?></span>
  </div>

  <?php if ($alert): ?>
    <div class="alert alert-success show" style="margin-bottom:20px;"><?= esc($alert) ?></div>
  <?php endif; ?>

  <div class="book-showcase">
    <div class="book-cover-large" style="overflow:hidden;padding:0;">
      <?php $cover = trim((string)($book['Cover_Image'] ?? '')); ?>
      <?php if ($cover !== ''): ?>
        <img src="<?= esc($cover) ?>" alt="<?= esc($book['Title']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:12px;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div style="display:none;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:20px;color:#fff;">
          <div class="big-icon">📖</div>
          <h3 style="font-family:'Playfair Display',serif;font-size:1.1rem;margin-bottom:6px;"><?= esc($book['Title']) ?></h3>
          <p style="font-size:0.8rem;color:var(--gold-light);"><?= esc($book['Author_Name'] ?: 'Academic Library') ?></p>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:20px;color:#fff;">
          <div class="big-icon">📖</div>
          <h3 style="font-family:'Playfair Display',serif;font-size:1.1rem;margin-bottom:6px;"><?= esc($book['Title']) ?></h3>
          <p style="font-size:0.8rem;color:var(--gold-light);"><?= esc($book['Author_Name'] ?: 'Academic Library') ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div class="book-info-col">
      <h1><?= esc($book['Title']) ?></h1>
      <p class="author-line">Written by <strong><?= esc($book['Author_Name'] ?: 'Academic Faculty') ?></strong></p>

      <div class="badge-row">
        <span class="badge badge-blue" style="font-size:0.85rem;"><?= esc($book['Category_Name'] ?: 'General') ?></span>
        <span class="badge <?= $book['Available_Copies'] > 0 ? 'badge-green' : 'badge-red' ?>" style="font-size:0.85rem;">
          <?= $book['Available_Copies'] > 0 ? 'In Stock (' . $book['Available_Copies'] . ' available)' : 'Currently Unavailable' ?>
        </span>
      </div>

      <table class="specs-table">
        <tr><td>ISBN</td><td><?= esc($book['ISBN'] ?: 'N/A') ?></td></tr>
        <tr><td>Category</td><td><?= esc($book['Category_Name'] ?: 'General') ?></td></tr>
        <tr><td>Total Stock</td><td><?= $book['Quantity'] ?> copies</td></tr>
        <tr><td>Shelf Location</td><td>Building 2 · Floor 3 · Section <?= esc($book['Category_Name'] ?: 'A') ?></td></tr>
        <tr><td>Loan Period</td><td>14 Days (Renewable online)</td></tr>
      </table>

      <div class="action-box">
        <div>
          <div style="font-size:0.9rem;font-weight:700;color:var(--navy);">Need this book?</div>
          <div style="font-size:0.78rem;color:var(--muted);">Registered students &amp; faculty can checkout copies immediately.</div>
        </div>
        <div style="display:flex;gap:10px;">
          <?php if (!empty($book['File_Path'])): ?>
            <a href="<?= esc($book['File_Path']) ?>" download class="btn btn-sm" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">⬇ Download PDF</a>
          <?php endif; ?>

          <?php if ($user && $book['Available_Copies'] > 0): ?>
            <form method="POST">
              <input type="hidden" name="action" value="reserve">
              <button type="submit" class="btn btn-primary btn-sm">Borrow / Reserve Now</button>
            </form>
          <?php elseif (!$user): ?>
            <a href="index.php?login=1" class="btn btn-primary btn-sm">Login to Borrow</a>
          <?php else: ?>
            <button class="btn btn-sm" disabled style="background:#e2e8f0;color:#94a3b8;">Out of Stock</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($related)): ?>
    <div class="related-section">
      <h3 style="font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--navy);">Related Books in <?= esc($book['Category_Name']) ?></h3>
      <div class="related-grid">
        <?php foreach ($related as $rb): ?>
          <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid var(--border);">
            <h4 style="font-family:'Playfair Display',serif;font-size:0.95rem;color:var(--navy);"><?= esc($rb['Title']) ?></h4>
            <p style="font-size:0.75rem;color:var(--muted);margin:4px 0 10px;">ISBN: <?= esc($rb['ISBN']) ?></p>
            <a href="product-details.php?id=<?= $rb['Book_ID'] ?>" class="btn btn-sm btn-gold">View Details →</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
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
