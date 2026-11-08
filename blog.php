<?php
/**
 * Premier University Library — Blog & Articles
 * Powered by PHP & MySQL
 */
require_once __DIR__ . '/config/db.php';
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Premier University Library — Blog</title>
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
.page-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--royal) 60%,#0d2147 100%);padding:80px 20px;text-align:center;color:#fff;}
.page-hero h1{font-family:'Playfair Display',serif;font-size:clamp(2rem,5vw,3.2rem);margin-bottom:12px;}
.page-hero p{color:rgba(255,255,255,0.7);max-width:600px;margin:0 auto;font-size:1rem;line-height:1.6;}
.blog-container{max-width:1100px;margin:50px auto;padding:0 20px;}
.blog-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:28px;}
.blog-card{background:#fff;border-radius:16px;border:1px solid var(--border);overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,0.04);transition:transform 0.2s;}
.blog-card:hover{transform:translateY(-4px);box-shadow:0 10px 28px rgba(0,0,0,0.08);}
.blog-img{height:200px;background:linear-gradient(135deg,var(--royal),var(--navy));display:flex;align-items:center;justify-content:center;color:var(--gold-light);font-size:3.5rem;}
.blog-content{padding:26px;}
.blog-meta{font-size:0.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;}
.blog-title{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:700;color:var(--navy);line-height:1.3;margin-bottom:10px;}
.blog-excerpt{font-size:0.88rem;color:var(--muted);line-height:1.7;}
.read-more{display:inline-block;color:var(--royal);font-weight:600;font-size:0.84rem;margin-top:16px;text-decoration:none;}
.read-more:hover{color:var(--gold);}
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
    <a href="product-details.php">Product Details</a>
    <a href="blog.php" class="active">Blog</a>
    <a href="faq.php">FAQ</a>
    <a href="contact.php">Contact</a>
    <?php if ($user): ?>
      <a href="<?= ($user['role']==='admin'||$user['role']==='librarian')?'admin.php':($user['role']==='teacher'?'teacher.php':'student.php') ?>" style="color:var(--gold-light);font-weight:600;">Dashboard</a>
    <?php else: ?>
      <a href="index.php" style="color:var(--gold-light);font-weight:600;">Login</a>
    <?php endif; ?>
  </div>
</nav>

<div class="page-hero">
  <h1>Library Blog &amp; Academic News</h1>
  <p>Articles, study strategies, research updates, and library announcements for the Premier University community.</p>
</div>

<div class="blog-container">
  <div class="blog-grid">
    <div class="blog-card">
      <div class="blog-img">💻</div>
      <div class="blog-content">
        <div class="blog-meta">Digital Library · March 2026</div>
        <h3 class="blog-title">Transitioning to Cloud-Native University Libraries</h3>
        <p class="blog-excerpt">How Premier University eliminated manual checkout queues by migrating all book records, author relations, and course materials to a relational database architecture.</p>
        <a href="#" class="read-more">Read Article →</a>
      </div>
    </div>

    <div class="blog-card">
      <div class="blog-img">📚</div>
      <div class="blog-content">
        <div class="blog-meta">Study Tips · February 2026</div>
        <h3 class="blog-title">Top 10 Fundamental Books for Computer Science Majors</h3>
        <p class="blog-excerpt">A curated roadmap of textbooks covering algorithms, operating systems, compiler design, and distributed networks available directly in our library stacks.</p>
        <a href="#" class="read-more">Read Article →</a>
      </div>
    </div>

    <div class="blog-card">
      <div class="blog-img">🔬</div>
      <div class="blog-content">
        <div class="blog-meta">Research &amp; Thesis · January 2026</div>
        <h3 class="blog-title">How to Access IEEE Xplore &amp; ACM Digital Library from Home</h3>
        <p class="blog-excerpt">A comprehensive guide for 4th-year students preparing capstone projects and research publications through university institutional credentials.</p>
        <a href="#" class="read-more">Read Article →</a>
      </div>
    </div>
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
