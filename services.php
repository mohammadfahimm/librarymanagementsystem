<?php
/**
 * Premier University Library — Services
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
<title>Premier University Library — Services</title>
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
.services-container{max-width:1100px;margin:50px auto;padding:0 20px;}
.services-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:28px;}
.s-card{background:#fff;border-radius:16px;padding:32px;border:1px solid var(--border);box-shadow:0 4px 20px rgba(0,0,0,0.04);transition:transform 0.2s;}
.s-card:hover{transform:translateY(-4px);box-shadow:0 10px 28px rgba(0,0,0,0.08);}
.s-icon{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:20px;}
.s-card h3{font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--navy);margin-bottom:10px;}
.s-card p{color:var(--muted);font-size:0.9rem;line-height:1.7;}
.s-card ul{margin-top:14px;padding-left:20px;color:#475569;font-size:0.86rem;line-height:1.8;}
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
    <a href="services.php" class="active">Services</a>
    <a href="product-list.php">Product List</a>
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
  <h1>Library Services</h1>
  <p>Explore the full range of academic, borrowing, digital, and research support services available at Premier University.</p>
</div>

<div class="services-container">
  <div class="services-grid">
    <div class="s-card">
      <div class="s-icon">📚</div>
      <h3>Book Borrowing &amp; Circulation</h3>
      <p>Borrow up to 3 textbooks at a time for 14 calendar days with quick barcode verification and online status checking.</p>
      <ul>
        <li>Real-time inventory lookup</li>
        <li>Automated renewal requests</li>
        <li>Overdue reminders via email</li>
      </ul>
    </div>
    <div class="s-card">
      <div class="s-icon">💻</div>
      <h3>Digital Repository &amp; E-Books</h3>
      <p>Instant access to lecture slides, course notes, past exam papers, and downloadable academic PDFs.</p>
      <ul>
        <li>Organized by course code &amp; semester</li>
        <li>Faculty uploads &amp; verified materials</li>
        <li>24/7 high-speed cloud access</li>
      </ul>
    </div>
    <div class="s-card">
      <div class="s-icon">🔬</div>
      <h3>Research Reference Support</h3>
      <p>Assistance for undergraduate and postgraduate students conducting research and thesis preparation.</p>
      <ul>
        <li>Citation assistance (IEEE, APA)</li>
        <li>Access to academic journals &amp; IEEE Xplore</li>
        <li>Plagiarism check guidelines</li>
      </ul>
    </div>
    <div class="s-card">
      <div class="s-icon">🏢</div>
      <h3>Quiet Study Zones</h3>
      <p>Dedicated air-conditioned reading halls and collaborative group discussion rooms for students.</p>
      <ul>
        <li>High-speed university Wi-Fi</li>
        <li>Charging stations at every desk</li>
        <li>Reserved faculty reading lounge</li>
      </ul>
    </div>
    <div class="s-card">
      <div class="s-icon">📢</div>
      <h3>Notice Board &amp; Announcements</h3>
      <p>Official communication channel for library hours, holiday schedules, book drives, and academic exhibitions.</p>
      <ul>
        <li>Instant broadcast to student portals</li>
        <li>Live campus notifications</li>
      </ul>
    </div>
    <div class="s-card">
      <div class="s-icon">💡</div>
      <h3>Book Acquisition Requests</h3>
      <p>Students and teachers can recommend new titles or textbooks to be purchased by the library acquisition committee.</p>
      <ul>
        <li>Submit request via feedback portal</li>
        <li>Review and notification by admin</li>
      </ul>
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
