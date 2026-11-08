<?php
/**
 * Premier University Library — About Us
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
<title>Premier University Library — About Us</title>
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
.page-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--royal) 60%,#0d2147 100%);padding:80px 20px;text-align:center;color:#fff;position:relative;}
.page-hero h1{font-family:'Playfair Display',serif;font-size:clamp(2rem,5vw,3.2rem);margin-bottom:12px;}
.page-hero p{color:rgba(255,255,255,0.7);max-width:600px;margin:0 auto;font-size:1rem;line-height:1.6;}
.about-container{max-width:1100px;margin:50px auto;padding:0 20px;}
.mission-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:28px;margin-bottom:60px;}
.mission-card{background:#fff;border-radius:16px;padding:32px;border:1px solid var(--border);box-shadow:0 4px 20px rgba(0,0,0,0.04);}
.mission-card h3{font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--navy);margin-bottom:12px;}
.mission-card p{color:var(--muted);font-size:0.9rem;line-height:1.7;}
.team-section{margin-top:60px;}
.team-grid{
  display:grid;
  grid-template-columns:repeat(6,minmax(0,1fr));
  gap:24px 20px;
  margin-top:30px;
  max-width:900px;
  margin-left:auto;
  margin-right:auto;
  justify-items:stretch;
}
/* Row 1 — 3 equal cards */
.team-grid .team-card:nth-child(1){grid-column:1 / 3;}
.team-grid .team-card:nth-child(2){grid-column:3 / 5;}
.team-grid .team-card:nth-child(3){grid-column:5 / 7;}
/* Row 2 — 2 cards centered under the first row */
.team-grid .team-card:nth-child(4){grid-column:2 / 4;}
.team-grid .team-card:nth-child(5){grid-column:4 / 6;}
@media(max-width:800px){
  .team-grid{grid-template-columns:repeat(2,1fr);max-width:100%;}
  .team-grid .team-card:nth-child(1),
  .team-grid .team-card:nth-child(2),
  .team-grid .team-card:nth-child(3),
  .team-grid .team-card:nth-child(4),
  .team-grid .team-card:nth-child(5){grid-column:auto;}
}
@media(max-width:480px){
  .team-grid{grid-template-columns:1fr;}
}
.team-photo.placeholder{display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--navy),var(--royal));color:var(--gold-light);font-weight:700;font-size:1.4rem;border:3px solid var(--gold);}
.team-card{padding:18px 12px;}
.team-name{font-size:0.95rem;}
.team-role{font-size:0.72rem;line-height:1.3;}
.team-bio{font-size:0.75rem;}
.team-id{font-size:0.68rem;}
.team-card{background:#fff;border-radius:16px;padding:26px;text-align:center;border:1px solid var(--border);transition:transform 0.2s;}
.team-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,0.08);}
.team-photo{width:90px;height:90px;border-radius:50%;margin:0 auto 16px;object-fit:cover;border:3px solid var(--gold);}
.team-photo-lower{object-position:center top;}
.team-name{font-family:'Playfair Display',serif;font-weight:700;font-size:1.1rem;color:var(--navy);}
.team-role{color:var(--royal);font-size:0.8rem;font-weight:600;margin:4px 0 10px;}
.team-id{display:inline-block;background:#f0f4ff;color:var(--royal);font-size:0.75rem;padding:2px 10px;border-radius:12px;font-family:'DM Mono',monospace;margin-bottom:12px;}
.team-bio{font-size:0.82rem;color:var(--muted);line-height:1.6;}

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
    <a href="about.php" class="active">About</a>
    <a href="services.php">Services</a>
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
  <h1>About the Library &amp; Team</h1>
  <p>Empowering the students, teachers, and researchers of Premier University with modern digital knowledge management.</p>
</div>

<div class="about-container">
  <div class="mission-grid">
    <div class="mission-card">
      <div style="font-size:2rem;margin-bottom:12px;">🎯</div>
      <h3>Our Mission</h3>
      <p>To provide frictionless access to quality educational resources, books, and lecture materials for every enrolled student and faculty member of Premier University.</p>
    </div>
    <div class="mission-card">
      <div style="font-size:2rem;margin-bottom:12px;">🔭</div>
      <h3>Our Vision</h3>
      <p>A digitally connected university ecosystem where finding research papers, checking out textbooks, and sharing course modules takes only seconds.</p>
    </div>
    <div class="mission-card">
      <div style="font-size:2rem;margin-bottom:12px;">⚖️</div>
      <h3>Academic Excellence</h3>
      <p>Ensuring transparent tracking of university library assets with automated overdue management, clear borrowing histories, and role-based access control.</p>
    </div>
  </div>

  <div class="team-section">
    <div style="text-align:center;">
      <h2 style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--navy);">Developed by Loop Infinity</h2>
      <p style="color:var(--muted);font-size:0.9rem;margin-top:6px;">Department of Computer Science &amp; Engineering · Premier University</p>
    </div>

    <div class="team-grid">
      <div class="team-card">
        <img src="images/fahim.png" alt="Mohammad Fahim" class="team-photo">
        <div class="team-name">Mohammad Fahim</div>
        <div class="team-role">Project Lead / Database Architect</div>
        <div class="team-id">ID: 0222420005101077</div>
        <div class="team-bio">ER design, PK/FK relationships, normalization, team coordination, and compiling the final documentation.</div>
      </div>
      <div class="team-card">
        <img src="images/Onik.jpeg" alt="Joyanta Chowdhury Onik" class="team-photo">
        <div class="team-name">Joyanta Chowdhury Onik</div>
        <div class="team-role">Backend / Database Developer (SQL)</div>
        <div class="team-id">ID: 0222420005101054</div>
        <div class="team-bio">SQL DDL/DML, library_management_db.sql, views, indexes, procedures, and query optimization.</div>
      </div>
      <div class="team-card">
        <img src="images/tarek.jpeg" alt="Mohammad Tarek Aziz" class="team-photo team-photo-lower">
        <div class="team-name">Mohammad Tarek Aziz</div>
        <div class="team-role">Data Analyst / Tester</div>
        <div class="team-id">ID: 0222420005101073</div>
        <div class="team-bio">Sample data seeding, test cases, login/borrow/upload checks, and data integrity validation.</div>
      </div>
      <div class="team-card">
        <img src="images/Jitu.jpg" alt="Jitu Barua" class="team-photo team-photo-lower">
        <div class="team-name">Jitu Barua</div>
        <div class="team-role">System Integration Developer</div>
        <div class="team-id">ID: 0222420005101047</div>
        <div class="team-bio">Module integration, deployment support, cross-portal connectivity, and environment configuration.</div>
      </div>
      <div class="team-card">
        <img src="images/sumiya.jpg" alt="Sumaya Binte Islam Orin" class="team-photo team-photo-lower">
        <div class="team-name">Sumaya Binte Islam Orin</div>
        <div class="team-role">Documentation &amp; Presentation Lead</div>
        <div class="team-id">ID: 0222320005101078</div>
        <div class="team-bio">Project report, ER diagram documentation, PowerPoint slides, and final presentation delivery.</div>
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
