<?php
/**
 * Premier University Library — FAQ
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
<title>Premier University Library — FAQ</title>
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
.faq-container{max-width:850px;margin:50px auto;padding:0 20px;}
.faq-search{width:100%;padding:14px 18px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;margin-bottom:30px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.03);}
.faq-item{background:#fff;border-radius:12px;border:1px solid var(--border);margin-bottom:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.03);}
.faq-question{padding:20px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none;font-weight:600;color:var(--navy);font-size:0.95rem;}
.faq-question:hover{background:#fafbff;}
.faq-answer{padding:0 20px 20px;color:var(--muted);font-size:0.88rem;line-height:1.7;display:none;}
.faq-item.active .faq-answer{display:block;}
.faq-item.active .faq-toggle{transform:rotate(180deg);}
.faq-toggle{transition:transform 0.2s;color:var(--gold);font-size:1.1rem;}
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
    <a href="blog.php">Blog</a>
    <a href="faq.php" class="active">FAQ</a>
    <a href="contact.php">Contact</a>
    <?php if ($user): ?>
      <a href="<?= ($user['role']==='admin'||$user['role']==='librarian')?'admin.php':($user['role']==='teacher'?'teacher.php':'student.php') ?>" style="color:var(--gold-light);font-weight:600;">Dashboard</a>
    <?php else: ?>
      <a href="index.php" style="color:var(--gold-light);font-weight:600;">Login</a>
    <?php endif; ?>
  </div>
</nav>

<div class="page-hero">
  <h1>Frequently Asked Questions</h1>
  <p>Find answers to common questions about library cards, borrowing limits, course materials, and digital access.</p>
</div>

<div class="faq-container">
  <input type="text" class="faq-search" id="faqSearch" placeholder="Type a question or keyword to search FAQ…" oninput="filterFAQ()">

  <div class="faq-item active">
    <div class="faq-question" onclick="toggleFAQ(this)">
      <span>How many books can a student borrow at a time?</span>
      <span class="faq-toggle">▼</span>
    </div>
    <div class="faq-answer">
      Enrolled Premier University students may borrow up to <strong>3 books</strong> concurrently for a standard lending duration of <strong>14 calendar days</strong>. Renewals can be requested online or at the circulation desk provided no other student has placed a hold on the title.
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFAQ(this)">
      <span>How do I access and download teacher lecture materials?</span>
      <span class="faq-toggle">▼</span>
    </div>
    <div class="faq-answer">
      Login to the <strong>Student Portal</strong> using your student ID and navigate to the <strong>"Course Materials"</strong> tab. All lecture slides, lab manuals, and PDFs uploaded by faculty for your batch and courses are immediately available for direct download.
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFAQ(this)">
      <span>What happens if a book is returned past its due date?</span>
      <span class="faq-toggle">▼</span>
    </div>
    <div class="faq-answer">
      Books returned after the scheduled due date incur a nominal fee of 5 BDT per day per book to encourage timely circulation among peers. Automated overdue alerts will display in red on your student dashboard.
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFAQ(this)">
      <span>How can faculty members upload course modules?</span>
      <span class="faq-toggle">▼</span>
    </div>
    <div class="faq-answer">
      Faculty members can log in using their teacher credentials on the <strong>Teacher Portal</strong>. From the "Course Materials" tab, you can select the file from your device, choose category (Lecture Slides, Assignment, Lab Manual, etc.), set course code, and choose visibility.
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFAQ(this)">
      <span>Where is the Premier University physical library located?</span>
      <span class="faq-toggle">▼</span>
    </div>
    <div class="faq-answer">
      The central university library is situated at Building 2, Floor 3, Premier University, Chittagong, Bangladesh. It remains open from 8:00 AM to 6:00 PM on Sunday through Thursday.
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question" onclick="toggleFAQ(this)">
      <span>Can I recommend a book for university acquisition?</span>
      <span class="faq-toggle">▼</span>
    </div>
    <div class="faq-answer">
      Yes! Both students and teachers can submit book acquisition recommendations directly through the <strong>Contact &amp; Feedback</strong> portal on your dashboard or via the public Contact page.
    </div>
  </div>
</div>

<footer style="background:var(--navy);color:rgba(255,255,255,0.4);text-align:center;padding:30px;font-size:0.8rem;margin-top:80px;">
  &copy; 2026 Premier University IT &nbsp;|&nbsp; Designed &amp; Developed by <strong>Loop Infinity</strong>
</footer>

<script src="theme.js"></script>
<script>
function toggleFAQ(el) {
  var item = el.parentElement;
  item.classList.toggle('active');
}
function filterFAQ() {
  var q = document.getElementById('faqSearch').value.toLowerCase();
  document.querySelectorAll('.faq-item').forEach(function(item) {
    var text = item.textContent.toLowerCase();
    item.style.display = (!q || text.includes(q)) ? 'block' : 'none';
  });
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
