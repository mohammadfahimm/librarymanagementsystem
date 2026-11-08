<?php
/**
 * Premier University Library — Home
 * Powered by PHP & MySQL
 */
require_once __DIR__ . '/config/db.php';
$db = getDB();

// Fetch live statistics from MySQL / SQLite database
$totalBooks = (int)$db->query("SELECT COUNT(*) FROM book")->fetchColumn();
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalFiles = (int)$db->query("SELECT COUNT(*) FROM digital_files")->fetchColumn();
$totalNotices = (int)$db->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

// Fetch live notices
$noticesStmt = $db->query("SELECT * FROM notifications ORDER BY Notification_ID DESC LIMIT 5");
$notices = $noticesStmt->fetchAll();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Premier University Library — Home</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --navy:#0a1628;
  --royal:#1a3a6b;
  --gold:#c9a84c;
  --gold-light:#f0d080;
  --cream:#faf7f0;
  --white:#ffffff;
  --red:#c0392b;
  --green:#27ae60;
  --text:#1a1a2e;
  --muted:#6b7280;
  --border:#e2e8f0;
}
html{scroll-behavior:smooth;}
body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--text);overflow-x:hidden;}

/* ====== NAVBAR ====== */
.navbar{position:fixed;top:0;left:0;right:0;z-index:1000;background:rgba(10,22,40,0.92);backdrop-filter:blur(12px);border-bottom:1px solid rgba(201,168,76,0.15);padding:0 40px;height:70px;display:flex;align-items:center;justify-content:space-between;transition:all 0.3s;}
.navbar.scrolled{background:rgba(10,22,40,0.98);box-shadow:0 4px 20px rgba(0,0,0,0.3);}
.nav-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
.nav-brand-icon{width:40px;height:40px;background:linear-gradient(135deg,var(--gold),var(--gold-light));border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 2px 12px rgba(201,168,76,0.4);overflow:hidden;}
.nav-brand-icon img{width:40px;height:40px;object-fit:contain;}
.nav-brand-text h1{font-family:'Playfair Display',serif;color:#fff;font-size:1rem;font-weight:700;line-height:1.1;}
.nav-brand-text p{color:rgba(255,255,255,0.4);font-size:0.62rem;text-transform:uppercase;letter-spacing:1.5px;}
.nav-links{display:flex;align-items:center;gap:4px;}
.nav-links a{color:rgba(255,255,255,0.65);text-decoration:none;font-size:0.82rem;font-weight:500;padding:6px 12px;border-radius:6px;transition:all 0.2s;}
.nav-links a:hover{color:var(--gold-light);background:rgba(255,255,255,0.07);}
.nav-links a.active{color:var(--gold);}
.nav-cta{display:flex;gap:8px;}
.btn-outline{padding:7px 18px;border:1px solid rgba(201,168,76,0.5);color:var(--gold-light);background:transparent;border-radius:7px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all 0.2s;}
.btn-outline:hover{background:rgba(201,168,76,0.12);}
.btn-fill{padding:7px 18px;background:linear-gradient(135deg,var(--gold),#b8932a);color:var(--navy);border:none;border-radius:7px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all 0.2s;}
.btn-fill:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(201,168,76,0.4);}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:4px;}
.hamburger span{width:24px;height:2px;background:var(--gold-light);border-radius:2px;transition:all 0.3s;}
.mobile-nav{display:none;position:fixed;top:70px;left:0;right:0;background:var(--navy);z-index:999;padding:20px;flex-direction:column;gap:4px;border-bottom:1px solid rgba(255,255,255,0.1);}
.mobile-nav.open{display:flex;}
.mobile-nav a{color:rgba(255,255,255,0.75);text-decoration:none;padding:10px 14px;border-radius:8px;font-size:0.9rem;transition:background 0.2s;}
.mobile-nav a:hover{background:rgba(255,255,255,0.07);color:var(--gold-light);}

/* ====== HERO ====== */
.hero{position:relative;min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden;}
.hero-bg{position:absolute;inset:0;background:linear-gradient(135deg,var(--navy) 0%,var(--royal) 55%,#0d2147 100%);}
.hero-bg::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a84c' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");}
.hero-orb{position:absolute;border-radius:50%;filter:blur(80px);animation:orbFloat 8s ease-in-out infinite;}
.hero-orb:nth-child(1){width:500px;height:500px;background:rgba(201,168,76,0.08);top:-100px;right:-100px;}
.hero-orb:nth-child(2){width:350px;height:350px;background:rgba(26,58,107,0.3);bottom:-80px;left:-80px;animation-delay:-4s;}
.hero-orb:nth-child(3){width:200px;height:200px;background:rgba(201,168,76,0.06);top:40%;left:10%;animation-delay:-2s;}
@keyframes orbFloat{0%,100%{transform:translateY(0) scale(1);}50%{transform:translateY(-30px) scale(1.05);}}
.hero-content{position:relative;z-index:2;text-align:center;padding:0 20px;max-width:860px;animation:heroFadeUp 1s ease 0.2s both;}
@keyframes heroFadeUp{from{opacity:0;transform:translateY(40px);}to{opacity:1;transform:translateY(0);}}
.hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(201,168,76,0.12);border:1px solid rgba(201,168,76,0.3);color:var(--gold-light);font-size:0.72rem;letter-spacing:2px;text-transform:uppercase;padding:6px 16px;border-radius:20px;margin-bottom:24px;}
.hero-badge::before{content:'✦';color:var(--gold);font-size:0.8rem;}
.hero h1{font-family:'Playfair Display',serif;color:#fff;font-size:clamp(2.4rem,6vw,4.2rem);font-weight:900;line-height:1.1;margin-bottom:20px;}
.hero h1 em{color:var(--gold);font-style:italic;}
.hero-sub{color:rgba(255,255,255,0.6);font-size:1.05rem;line-height:1.7;max-width:560px;margin:0 auto 36px;}
.hero-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;}
.hero-btn-primary{padding:14px 32px;background:linear-gradient(135deg,var(--gold),#b8932a);color:var(--navy);border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all 0.25s;box-shadow:0 4px 20px rgba(201,168,76,0.4);}
.hero-btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(201,168,76,0.5);}
.hero-btn-ghost{padding:14px 32px;background:rgba(255,255,255,0.07);color:#fff;border:1px solid rgba(255,255,255,0.2);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all 0.25s;backdrop-filter:blur(4px);}
.hero-btn-ghost:hover{background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.35);}
.hero-stats{display:flex;gap:40px;justify-content:center;margin-top:60px;flex-wrap:wrap;}
.hero-stat{text-align:center;}
.hero-stat .val{font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:900;color:var(--gold);}
.hero-stat .lbl{font-size:0.75rem;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:1px;margin-top:2px;}
.scroll-down{position:absolute;bottom:30px;left:50%;transform:translateX(-50%);z-index:2;display:flex;flex-direction:column;align-items:center;gap:6px;color:rgba(255,255,255,0.4);font-size:0.7rem;letter-spacing:1px;text-transform:uppercase;animation:scrollBounce 2s ease-in-out infinite;}
.scroll-down .arrow{width:24px;height:24px;border-right:2px solid rgba(255,255,255,0.3);border-bottom:2px solid rgba(255,255,255,0.3);transform:rotate(45deg);}
@keyframes scrollBounce{0%,100%{transform:translateX(-50%) translateY(0);}50%{transform:translateX(-50%) translateY(8px);}}

/* ====== FEATURES ====== */
.section{padding:90px 40px;}
.section-title{text-align:center;margin-bottom:60px;}
.section-title .eyebrow{font-size:0.72rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:10px;}
.section-title h2{font-family:'Playfair Display',serif;font-size:clamp(1.8rem,4vw,2.8rem);color:var(--navy);font-weight:900;line-height:1.2;margin-bottom:14px;}
.section-title p{color:var(--muted);font-size:0.95rem;max-width:560px;margin:0 auto;line-height:1.7;}
.features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;max-width:1100px;margin:0 auto;}
.feature-card{background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 14px rgba(0,0,0,0.06);border:1px solid var(--border);transition:all 0.3s;position:relative;overflow:hidden;}
.feature-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--royal),var(--gold));}
.feature-card:hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(0,0,0,0.12);}
.feature-icon{width:52px;height:52px;background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:18px;}
.feature-card h3{font-family:'Playfair Display',serif;color:var(--navy);font-size:1.1rem;font-weight:700;margin-bottom:10px;}
.feature-card p{color:var(--muted);font-size:0.87rem;line-height:1.65;}


/* ====== CAMPUS GALLERY ====== */
.gallery-section{background:#fff;padding:80px 40px;}
.gallery-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;max-width:1100px;margin:0 auto;}
.gallery-item{border-radius:14px;overflow:hidden;height:220px;position:relative;box-shadow:0 4px 16px rgba(0,0,0,0.08);}
.gallery-item img{width:100%;height:100%;object-fit:cover;transition:transform 0.4s;}
.gallery-item:hover img{transform:scale(1.06);}
.gallery-item.wide{grid-column:span 2;height:280px;}
.gallery-caption{position:absolute;bottom:0;left:0;right:0;padding:14px 16px;background:linear-gradient(transparent,rgba(10,22,40,0.85));color:#fff;font-size:0.82rem;font-weight:600;}
@media(max-width:900px){
  .gallery-grid{grid-template-columns:1fr 1fr;}
  .gallery-item.wide{grid-column:span 2;height:220px;}
}
@media(max-width:600px){
  .gallery-grid{grid-template-columns:1fr;}
  .gallery-item.wide{grid-column:span 1;}
}

/* ====== STATS BAND ====== */
.stats-band{background:linear-gradient(135deg,var(--navy),var(--royal));padding:70px 40px;}
.stats-band-inner{max-width:1000px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:30px;text-align:center;}
.sband-item .val{font-family:'Playfair Display',serif;font-size:2.8rem;font-weight:900;color:var(--gold);}
.sband-item .lbl{color:rgba(255,255,255,0.55);font-size:0.8rem;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;}

/* ====== NOTICES ====== */
.xml-section{background:#f8f9fb;border-top:1px solid var(--border);}
.xml-notice-list{max-width:800px;margin:0 auto;}
.xml-item{background:#fff;border-radius:10px;padding:16px 20px;margin-bottom:12px;border-left:3px solid var(--gold);box-shadow:0 1px 6px rgba(0,0,0,0.05);}
.xml-item .x-title{font-weight:600;color:var(--navy);font-size:0.9rem;}
.xml-item .x-meta{font-size:0.75rem;color:var(--muted);margin-top:3px;}

/* ====== TESTIMONIALS ====== */
.testimonials-section{background:#fff;}
.testimonials-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;max-width:1000px;margin:0 auto;}
.testimonial-card{background:#f8f9fb;border-radius:16px;padding:28px;border:1px solid var(--border);}
.testimonial-quote{font-size:2rem;color:var(--gold);line-height:1;margin-bottom:12px;}
.testimonial-card p{font-size:0.88rem;color:#374151;line-height:1.7;font-style:italic;}
.testimonial-author{display:flex;align-items:center;gap:12px;margin-top:18px;padding-top:16px;border-top:1px solid var(--border);}
.t-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--royal),var(--navy));display:flex;align-items:center;justify-content:center;color:var(--gold-light);font-weight:700;font-size:0.9rem;}
.t-name{font-size:0.85rem;font-weight:600;color:var(--navy);}
.t-role{font-size:0.72rem;color:var(--muted);}

/* ====== CTA SECTION ====== */
.cta-section{background:linear-gradient(135deg,var(--navy),var(--royal));padding:90px 40px;text-align:center;}
.cta-section h2{font-family:'Playfair Display',serif;color:#fff;font-size:2.2rem;font-weight:900;margin-bottom:16px;}
.cta-section p{color:rgba(255,255,255,0.6);font-size:0.95rem;max-width:480px;margin:0 auto 36px;}
.cta-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;}

/* ====== FOOTER ====== */
.footer{background:var(--navy);border-top:1px solid rgba(255,255,255,0.06);padding:50px 40px 28px;}
.footer-inner{max-width:1100px;margin:0 auto;}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:40px;}
.footer-brand h3{font-family:'Playfair Display',serif;color:#fff;font-size:1.1rem;margin-bottom:10px;}
.footer-brand p{color:rgba(255,255,255,0.4);font-size:0.82rem;line-height:1.7;}
.footer-col h4{color:var(--gold-light);font-size:0.75rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin-bottom:14px;}
.footer-col a{display:block;color:rgba(255,255,255,0.45);font-size:0.82rem;text-decoration:none;margin-bottom:8px;transition:color 0.2s;}
.footer-col a:hover{color:var(--gold-light);}
.footer-bottom{border-top:1px solid rgba(255,255,255,0.07);padding-top:22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.footer-bottom p{color:rgba(255,255,255,0.3);font-size:0.75rem;}
.footer-bottom a{color:var(--gold);text-decoration:none;}

@media(max-width:900px){
  .navbar{padding:0 20px;}
  .nav-links{display:none;}
  .nav-cta{display:none;}
  .hamburger{display:flex;}
  .hero h1{font-size:2.2rem;}
  .footer-grid{grid-template-columns:1fr 1fr;}
  .slider-wrap{width:100%;height:380px;}
  .section{padding:60px 20px;}
  .stats-band{padding:50px 20px;}
}
@media(max-width:600px){
  .footer-grid{grid-template-columns:1fr;}
  .hero-stats{gap:24px;}
  .slide-title{font-size:1.3rem;}
  .slide-content{padding:30px 20px;}
}
</style>
</head>
<body>

<!-- ====== NAVBAR ====== -->
<nav class="navbar" id="navbar">
  <a href="home.php" class="nav-brand">
    <div class="nav-brand-icon">
      <img src="images/puc_logo.png" alt="Premier University Logo">
    </div>
    <div class="nav-brand-text">
      <h1>Premier University</h1>
      <p>Library Management</p>
    </div>
  </a>

  <div class="nav-links">
    <a href="home.php" class="active">Home</a>
    <a href="about.php">About</a>
    <a href="services.php">Services</a>
    <a href="product-list.php">Product List</a>
    <a href="product-details.php">Product Details</a>
    <a href="blog.php">Blog</a>
    <a href="faq.php">FAQ</a>
    <a href="contact.php">Contact</a>
  </div>

  <div class="nav-cta">
    <?php if ($user): ?>
      <a href="<?= ($user['role']==='admin'||$user['role']==='librarian')?'admin.php':($user['role']==='teacher'?'teacher.php':'student.php') ?>" class="btn-fill">Dashboard (<?= esc($user['name']) ?>)</a>
      <a href="logout.php" class="btn-outline">Logout</a>
    <?php else: ?>
      <a href="index.php?login=1" class="btn-outline">Login</a>
      <a href="index.php?login=1" class="btn-fill">Register</a>
    <?php endif; ?>
  </div>

  <div class="hamburger" onclick="toggleMobile()">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- Mobile Nav -->
<div class="mobile-nav" id="mobileNav">
  <a href="home.php">🏠 Home</a>
  <a href="about.php">ℹ️ About</a>
  <a href="services.php">⚙️ Services</a>
  <a href="product-list.php">📚 Product List</a>
  <a href="product-details.php">🔍 Product Details</a>
  <a href="blog.php">📝 Blog</a>
  <a href="faq.php">❓ FAQ</a>
  <a href="contact.php">✉️ Contact</a>
  <?php if ($user): ?>
    <a href="<?= ($user['role']==='admin'||$user['role']==='librarian')?'admin.php':($user['role']==='teacher'?'teacher.php':'student.php') ?>" style="color:var(--gold-light);font-weight:600;">📊 Go to Dashboard</a>
    <a href="logout.php">🚪 Logout</a>
  <?php else: ?>
    <a href="index.php?login=1" style="color:var(--gold-light);font-weight:600;">🔑 Login / Register</a>
  <?php endif; ?>
</div>

<!-- ====== HERO ====== -->
<section class="hero" id="home">
  <div class="hero-bg">
    <div class="hero-orb"></div>
    <div class="hero-orb"></div>
    <div class="hero-orb"></div>
  </div>
  <div class="hero-content">
    <div class="hero-badge">Est. 2026 · Premier University Library</div>
    <h1>Your Gateway to<br><em>Knowledge &amp; Learning</em></h1>
    <p class="hero-sub">Access thousands of books, course materials, and academic resources from Premier University's digital library — anytime, anywhere.</p>
    <div class="hero-btns">
      <?php if ($user): ?>
        <a href="<?= ($user['role']==='admin'||$user['role']==='librarian')?'admin.php':($user['role']==='teacher'?'teacher.php':'student.php') ?>" class="hero-btn-primary">📊 Go to My Dashboard</a>
      <?php else: ?>
        <a href="index.php?login=1" class="hero-btn-primary">🔑 Login to Library</a>
      <?php endif; ?>
      <a href="about.php" class="hero-btn-ghost">Learn More →</a>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><div class="val" id="heroBooks"><?= $totalBooks ?>+</div><div class="lbl">Books</div></div>
      <div class="hero-stat"><div class="val" id="heroStudents"><?= max(1200, $totalUsers * 200) ?>+</div><div class="lbl">Students</div></div>
      <div class="hero-stat"><div class="val">24/7</div><div class="lbl">Access</div></div>
      <div class="hero-stat"><div class="val">100%</div><div class="lbl">Digital</div></div>
    </div>
  </div>
  <div class="scroll-down"><div class="arrow"></div>Scroll</div>
</section>

<!-- ====== FEATURES ====== -->
<section class="section" id="features">
  <div class="section-title">
    <div class="eyebrow">What We Offer</div>
    <h2>Everything You Need in One Library</h2>
    <p>A comprehensive digital library management platform built with modern web technologies.</p>
  </div>
  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon">📖</div>
      <h3>Smart Book Catalog</h3>
      <p>Browse, search, and filter hundreds of books by title, author, or category. Real-time availability shown instantly.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📤</div>
      <h3>Issue &amp; Return System</h3>
      <p>Admins can issue books to students, track due dates, and process returns — with overdue alerts built in.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📁</div>
      <h3>Course Material Upload</h3>
      <p>Teachers upload PDFs, lecture slides, assignments, and lab manuals that students can download anytime.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📢</div>
      <h3>Notification Board</h3>
      <p>Admin posts important notices. Students and teachers see them instantly on their dashboards.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">👥</div>
      <h3>Multi-Role Access</h3>
      <p>Tailored access types — Admin, Librarian, Teacher, and Student — each with their own specialized dashboard.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">💬</div>
      <h3>Feedback &amp; Contact</h3>
      <p>Students and teachers can send messages and feedback directly to the library admin anytime.</p>
    </div>
  </div>
</section>


<!-- ====== CAMPUS GALLERY ====== -->
<section class="gallery-section" id="gallery">
  <div class="section-title">
    <div class="eyebrow">Campus &amp; Collections</div>
    <h2>Inside the Library</h2>
    <p>A glimpse of Premier University&#39;s reading spaces, stacks, and academic community.</p>
  </div>
  <div class="gallery-grid">
    <div class="gallery-item wide">
      <img src="images/library.jpeg" alt="Central Library stacks">
      <div class="gallery-caption">Central Library · Open Stacks</div>
    </div>
    <div class="gallery-item wide">
      <img src="images/convo.jpg" alt="University convocation">
      <div class="gallery-caption">Premier University Community</div>
    </div>
  </div>
</section>

<!-- ====== STATS BAND ====== -->
<div class="stats-band">
  <div class="stats-band-inner">
    <div class="sband-item"><div class="val" id="statBooks"><?= $totalBooks ?></div><div class="lbl">Book Titles</div></div>
    <div class="sband-item"><div class="val" id="statUsers"><?= $totalUsers ?></div><div class="lbl">Registered Users</div></div>
    <div class="sband-item"><div class="val" id="statFiles"><?= $totalFiles ?></div><div class="lbl">Course Materials</div></div>
    <div class="sband-item"><div class="val" id="statNotices"><?= $totalNotices ?></div><div class="lbl">Notices Posted</div></div>
  </div>
</div>

<!-- ====== LIVE NOTICES ====== -->
<section class="section xml-section" id="notices">
  <div class="section-title">
    <div class="eyebrow">Notice Board</div>
    <h2>Latest Library Announcements</h2>
    <p>Stay updated with the latest news from the library administration.</p>
  </div>
  <div class="xml-notice-list" id="xmlNoticeList">
    <?php if (!empty($notices)): ?>
      <?php foreach ($notices as $n): ?>
        <div class="xml-item">
          <div class="x-title">📢 <?= esc($n['Title']) ?></div>
          <div style="font-size:0.86rem;color:#374151;margin-top:6px;line-height:1.6;"><?= esc($n['Message']) ?></div>
          <div class="x-meta"><?= formatDate($n['Created_At']) ?> · Posted by <?= esc($n['Posted_By'] ?? 'Library Admin') ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center;padding:30px;color:var(--muted);">No announcements at this time.</div>
    <?php endif; ?>
  </div>
</section>

<!-- ====== TESTIMONIALS ====== -->
<section class="section testimonials-section">
  <div class="section-title">
    <div class="eyebrow">Testimonials</div>
    <h2>What People Say</h2>
    <p>Feedback from our library's students, teachers, and administrators.</p>
  </div>
  <div class="testimonials-grid">
    <div class="testimonial-card">
      <div class="testimonial-quote">"</div>
      <p>The new digital library system makes it so easy to check which books are available before coming to campus. Saves me so much time!</p>
      <div class="testimonial-author">
        <div class="t-avatar">SK</div>
        <div><div class="t-name">Sumaiya Khanam</div><div class="t-role">Student, CSE — 46th Batch</div></div>
      </div>
    </div>
    <div class="testimonial-card">
      <div class="testimonial-quote">"</div>
      <p>Uploading lecture slides and assignments for my students has never been this straightforward. Everything is organized perfectly.</p>
      <div class="testimonial-author">
        <div class="t-avatar">DR</div>
        <div><div class="t-name">Dr. Rahman</div><div class="t-role">Faculty, Dept. of CSE</div></div>
      </div>
    </div>
    <div class="testimonial-card">
      <div class="testimonial-quote">"</div>
      <p>Managing hundreds of books, tracking who borrowed what, and sending notices — it all happens in a few clicks now. Impressive work!</p>
      <div class="testimonial-author">
        <div class="t-avatar">SA</div>
        <div><div class="t-name">Admin</div><div class="t-role">Library Administrator</div></div>
      </div>
    </div>
  </div>
</section>

<!-- ====== CTA ====== -->
<section class="cta-section">
  <h2>Ready to Access the Library?</h2>
  <p>Login with your student or teacher account to access books, course materials, and more.</p>
  <div class="cta-btns">
    <a href="index.php?login=1" class="hero-btn-primary">Login Now →</a>
    <a href="contact.php" class="hero-btn-ghost">Contact Us</a>
  </div>
</section>

<!-- ====== FOOTER ====== -->
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-grid">
      <div class="footer-brand">
        <h3>📚 Premier University Library</h3>
        <p>A digital library management system providing seamless access to academic resources for students, teachers, and administrators of Premier University.</p>
      </div>
      <div class="footer-col">
        <h4>Navigation</h4>
        <a href="home.php">Home</a>
        <a href="about.php">About</a>
        <a href="services.php">Services</a>
        <a href="blog.php">Blog</a>
        <a href="faq.php">FAQ</a>
      </div>
      <div class="footer-col">
        <h4>Portals</h4>
        <a href="index.php?login=1">Student Login</a>
        <a href="index.php?login=1">Teacher Login</a>
        <a href="index.php?login=1">Admin Login</a>
        <a href="index.php?login=1">Register</a>
      </div>
      <div class="footer-col">
        <h4>Contact</h4>
        <a href="contact.php">Send Message</a>
        <a href="faq.php">FAQ</a>
        <a href="about.php">About the Team</a>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 Premier University IT. All rights reserved.</p>
      <p>Designed &amp; Developed by <a href="#">Loop Infinity</a></p>
    </div>
  </div>
</footer>

<script src="theme.js"></script>
<script>
// Navbar scroll effect
window.addEventListener('scroll', function(){
  document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 50);
});

// Mobile navigation toggle
function toggleMobile(){
  document.getElementById('mobileNav').classList.toggle('open');
}

</script>

<?php
if (file_exists(__DIR__ . '/config/popup.php')) {
    require_once __DIR__ . '/config/popup.php';
    if (function_exists('render_team_popup')) { render_team_popup(); }
}
?>
</body>
</html>
