<?php
/**
 * Premier University Library — Contact Us
 * Powered by PHP & MySQL
 */
require_once __DIR__ . '/config/db.php';
$db = getDB();
$user = getCurrentUser();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'General Inquiry');
    $role = trim($_POST['role'] ?? 'Student');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please complete all required fields.';
    } else {
        $stmt = $db->prepare("INSERT INTO feedback (User_ID, Member_Name, Email, Subject, Message, Role, Status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$user['id'] ?? null, $name, $email, $subject, $message, $role]);
        $success = 'Thank you! Your message has been received by the library administration. We will reply to your email shortly.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Premier University Library — Contact Us</title>
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
.contact-container{max-width:1050px;margin:50px auto;padding:0 20px;}
.contact-grid{display:grid;grid-template-columns:1fr 1.5fr;gap:40px;}
.info-card{background:#fff;border-radius:16px;padding:32px;border:1px solid var(--border);box-shadow:0 4px 20px rgba(0,0,0,0.04);}
.info-item{display:flex;gap:16px;margin-bottom:24px;}
.info-icon{width:44px;height:44px;border-radius:10px;background:#eff6ff;color:var(--royal);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.info-item h4{font-size:0.95rem;color:var(--navy);font-weight:700;margin-bottom:4px;}
.info-item p{font-size:0.85rem;color:var(--muted);line-height:1.6;}
.form-card{background:#fff;border-radius:16px;padding:36px;border:1px solid var(--border);box-shadow:0 4px 20px rgba(0,0,0,0.04);}
.f-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.f-group{margin-bottom:18px;}
.f-group label{display:block;font-size:0.8rem;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;}
.f-group input,.f-group select,.f-group textarea{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.9rem;}
.f-group input:focus,.f-group select:focus,.f-group textarea:focus{outline:none;border-color:var(--royal);box-shadow:0 0 0 3px rgba(26,58,107,0.1);}
@media(max-width:800px){
  .contact-grid{grid-template-columns:1fr;}
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
    <a href="product-details.php">Product Details</a>
    <a href="blog.php">Blog</a>
    <a href="faq.php">FAQ</a>
    <a href="contact.php" class="active">Contact</a>
    <?php if ($user): ?>
      <a href="<?= ($user['role']==='admin'||$user['role']==='librarian')?'admin.php':($user['role']==='teacher'?'teacher.php':'student.php') ?>" style="color:var(--gold-light);font-weight:600;">Dashboard</a>
    <?php else: ?>
      <a href="index.php?login=1" style="color:var(--gold-light);font-weight:600;">Login</a>
    <?php endif; ?>
  </div>
</nav>

<div class="page-hero">
  <h1>Contact Us</h1>
  <p>Reach out to the Premier University Library staff for reference help, circulation inquiries, or system feedback.</p>
</div>

<div class="contact-container">
  <?php if ($success): ?>
    <div class="alert alert-success show" style="margin-bottom:24px;"><?= esc($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger show" style="margin-bottom:24px;"><?= esc($error) ?></div>
  <?php endif; ?>

  <div class="contact-grid">
    <div class="info-card">
      <h3 style="font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--navy);margin-bottom:20px;">Library Information</h3>
      
      <div class="info-item">
        <div class="info-icon">📍</div>
        <div>
          <h4>Physical Location</h4>
          <p>Premier University Central Campus<br>1/A O.R. Nizam Road, Prabartak Circle<br>Panchlaish, Chittagong, Bangladesh</p>
        </div>
      </div>

      <div class="info-item">
        <div class="info-icon">⏰</div>
        <div>
          <h4>Opening Hours</h4>
          <p>Sunday – Thursday: 8:00 AM – 6:00 PM<br>Friday &amp; Saturday: Closed (Digital portal active 24/7)</p>
        </div>
      </div>

      <div class="info-item">
        <div class="info-icon">📞</div>
        <div>
          <h4>Direct Phone</h4>
          <p>+880-31-656917<br>+880-31-656918 (Ext: 204)</p>
        </div>
      </div>

      <div class="info-item">
        <div class="info-icon">✉️</div>
        <div>
          <h4>Official Email</h4>
          <p>library@puc.ac.bd<br>support@puc.ac.bd</p>
        </div>
      </div>
    </div>

    <div class="form-card">
      <h3 style="font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--navy);margin-bottom:6px;">Send us a Message</h3>
      <p style="font-size:0.86rem;color:var(--muted);margin-bottom:20px;">All messages are delivered directly to the library administration.</p>

      <form method="POST" action="contact.php">
        <div class="f-row">
          <div class="f-group">
            <label>Full Name *</label>
            <input type="text" name="name" value="<?= esc($user['name'] ?? '') ?>" placeholder="Your name" required>
          </div>
          <div class="f-group">
            <label>Email Address *</label>
            <input type="email" name="email" value="<?= esc($user['email'] ?? '') ?>" placeholder="name@puc.ac.bd" required>
          </div>
        </div>

        <div class="f-row">
          <div class="f-group">
            <label>I Am A</label>
            <select name="role">
              <option value="Student" <?= ($user && $user['role']==='student') ? 'selected' : '' ?>>Student</option>
              <option value="Teacher" <?= ($user && $user['role']==='teacher') ? 'selected' : '' ?>>Teacher / Faculty</option>
              <option value="Guest">Alumni / Guest</option>
            </select>
          </div>
          <div class="f-group">
            <label>Subject</label>
            <input type="text" name="subject" placeholder="e.g. Book request, card renewal" required>
          </div>
        </div>

        <div class="f-group">
          <label>Message *</label>
          <textarea name="message" rows="5" placeholder="Write your question or feedback in detail…" required></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;">Send Message</button>
      </form>
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
