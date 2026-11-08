<?php
/**
 * Premier University Library — Login & Registration Portal
 * Powered by PHP & MySQL
 */
require_once __DIR__ . '/config/db.php';

// Root URL (http://localhost/puc_library/) opens the public home page.
// Login portal: index.php?login=1  or submit POST to this file.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_GET['login']) && !isLoggedIn()) {
    header('Location: home.php');
    exit;
}


$error = '';
$success = '';

// If already logged in, redirect to respective portal
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'student';
    if ($role === 'admin' || $role === 'librarian') {
        header('Location: admin.php');
        exit;
    } elseif ($role === 'teacher') {
        header('Location: teacher.php');
        exit;
    } else {
        header('Location: student.php');
        exit;
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $db = getDB();

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username/ID and password.';
        } else {
            // Find user in DB
            $stmt = $db->prepare("SELECT u.*, m.Section, m.Batch FROM users u LEFT JOIN member m ON u.Member_ID = m.Member_ID WHERE u.Username = ? OR u.Email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            $authenticated = false;
            $inactiveAccount = false;
            if ($user) {
                if ((int)($user['Is_Active'] ?? 0) !== 1) {
                    $inactiveAccount = true;
                } else {
                    // Check password with bcrypt or plaintext fallback for seed credentials
                    if (password_verify($password, $user['Password']) || $password === $user['Password']) {
                        $authenticated = true;
                    }
                }
            }

            // Quick demo accounts fallback
            if (!$authenticated && !$inactiveAccount) {
                if ($username === 'admin' && $password === 'admin123') {
                    $user = ['User_ID' => 1, 'Username' => 'admin', 'Full_Name' => 'System Administrator', 'Role' => 'admin', 'Email' => 'admin@library.com'];
                    $authenticated = true;
                } elseif ($username === 'teacher1' && $password === 'teach123') {
                    $user = ['User_ID' => 4, 'Username' => 'teacher1', 'Full_Name' => 'Dr. Rahman', 'Role' => 'teacher', 'Email' => 'teacher@library.com'];
                    $authenticated = true;
                } elseif ($username === 'student' && $password === '1234') {
                    $user = ['User_ID' => 3, 'Username' => 'student', 'Full_Name' => 'Demo Student', 'Role' => 'student', 'Email' => 'student@library.com', 'Section' => 'A', 'Batch' => '46th'];
                    $authenticated = true;
                } elseif ($username === 'idea_guy' && $password === 'idea123') {
                    $user = ['User_ID' => 6, 'Username' => 'idea_guy', 'Full_Name' => 'Idea Contributor', 'Role' => 'student', 'Email' => 'idea@library.com', 'Section' => 'A', 'Batch' => '46th'];
                    $authenticated = true;
                } elseif ($username === 'tester1' && $password === 'test123') {
                    $user = ['User_ID' => 7, 'Username' => 'tester1', 'Full_Name' => 'QA Tester', 'Role' => 'student', 'Email' => 'tester@library.com', 'Section' => 'B', 'Batch' => '46th'];
                    $authenticated = true;
                }
            }

            if ($authenticated && $user) {
                $_SESSION['user_id'] = $user['User_ID'];
                $_SESSION['username'] = $user['Username'];
                $_SESSION['full_name'] = $user['Full_Name'];
                $_SESSION['email'] = $user['Email'] ?? '';
                $_SESSION['role'] = $user['Role'];
                $_SESSION['member_id'] = $user['Member_ID'] ?? null;
                $_SESSION['section'] = $user['Section'] ?? '';
                $_SESSION['batch'] = $user['Batch'] ?? '';

                logActivity($user['User_ID'], 'Login', "User {$user['Username']} successfully logged in.");

                // If AJAX request, return JSON
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    echo json_encode([
                        'success' => true,
                        'role' => $user['Role'],
                        'redirect' => ($user['Role'] === 'admin' || $user['Role'] === 'librarian') ? 'admin.php' : ($user['Role'] === 'teacher' ? 'teacher.php' : 'student.php')
                    ]);
                    exit;
                }

                if ($user['Role'] === 'admin' || $user['Role'] === 'librarian') {
                    header('Location: admin.php');
                } elseif ($user['Role'] === 'teacher') {
                    header('Location: teacher.php');
                } else {
                    header('Location: student.php');
                }
                exit;
            } else {
                $error = 'Invalid credentials. Please verify your username and password.';
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit;
                }
            }
        }
    } elseif ($action === 'register') {
        $role = $_POST['role'] ?? 'student';
        $username = v_str($_POST['reg_id'] ?? '', 50);
        $fullname = v_str($_POST['reg_name'] ?? '', 100);
        $password = (string)($_POST['reg_pass'] ?? '');
        $section = v_str($_POST['reg_section'] ?? '', 20);
        $batch = v_str($_POST['reg_batch'] ?? '', 20);

        $v = new Validator();
        $v->username($username);
        $v->required($fullname, 'Full name')->length($fullname, 'Full name', 2, 100);
        $v->password($password, 4, 72);
        $v->oneOf($role, ['student', 'teacher'], 'Role');
        if (!$v->ok()) {
            $error = $v->firstError();
        } else {
            // Check if username already exists
            $stmt = $db->prepare("SELECT User_ID FROM users WHERE Username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'An account with this ID or Username already exists.';
            } else {
                $email = strpos($username, '@') !== false ? $username : "{$username}@puc.ac.bd";
                $names = explode(' ', $fullname, 2);
                $first_name = $names[0];
                $last_name = $names[1] ?? 'User';

                // Insert into member table
                $stmtM = $db->prepare("INSERT INTO member (First_Name, Last_Name, Email, Membership_Date, Status, Section, Batch) VALUES (?, ?, ?, CURRENT_DATE, 'Active', ?, ?)");
                $stmtM->execute([$first_name, $last_name, $email, $section, $batch]);
                $member_id = $db->lastInsertId();

                // Insert into users table
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtU = $db->prepare("INSERT INTO users (Username, Email, Password, Full_Name, Role, Is_Active, Member_ID) VALUES (?, ?, ?, ?, ?, 1, ?)");
                $stmtU->execute([$username, $email, $hash, $fullname, $role, $member_id]);
                $user_id = $db->lastInsertId();

                logActivity($user_id, 'Registration', "New user {$username} registered with role {$role}");

                $success = 'Account created successfully! You can now log in.';
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    echo json_encode(['success' => true, 'message' => $success]);
                    exit;
                }
            }
        }
        if ($error && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Premier University Library — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --navy:#0a1628;--royal:#1a3a6b;--gold:#c9a84c;--gold-light:#f0d080;
  --cream:#faf7f0;--white:#ffffff;--red:#c0392b;--green:#27ae60;
  --text:#1a1a2e;--muted:#6b7280;
}
body{font-family:'DM Sans',sans-serif;background:var(--cream);min-height:100vh;display:flex;flex-direction:column;}
/* TOP NAV BAR */
.top-nav{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(10,22,40,0.95);backdrop-filter:blur(10px);border-bottom:1px solid rgba(201,168,76,0.15);padding:0 40px;height:60px;display:flex;align-items:center;justify-content:space-between;}
.top-nav .brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.top-nav .brand span{color:#fff;font-family:'Playfair Display',serif;font-size:0.95rem;font-weight:700;}
.top-nav .home-link{color:rgba(255,255,255,0.6);text-decoration:none;font-size:0.82rem;padding:6px 14px;border-radius:6px;transition:all 0.2s;}
.top-nav .home-link:hover{color:var(--gold-light);background:rgba(255,255,255,0.07);}
/* BG */
.bg{position:fixed;inset:0;z-index:0;background:linear-gradient(135deg,var(--navy) 0%,var(--royal) 60%,#0d2147 100%);overflow:hidden;}
.bg::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a84c' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");}
.bg-circle{position:absolute;border-radius:50%;background:rgba(201,168,76,0.06);animation:float 8s ease-in-out infinite;}
.bg-circle:nth-child(1){width:400px;height:400px;top:-100px;right:-100px;animation-delay:0s;}
.bg-circle:nth-child(2){width:300px;height:300px;bottom:-80px;left:-80px;animation-delay:-4s;}
@keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-20px);}}
/* LAYOUT */
.page{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:80px 20px 30px;}
/* HEADER */
.site-header{width:100%;max-width:1100px;display:flex;align-items:center;justify-content:space-between;margin-bottom:30px;}
.logo-area{display:flex;align-items:center;gap:14px;}
.logo-icon{width:52px;height:52px;background:linear-gradient(135deg,var(--gold),var(--gold-light));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:26px;box-shadow:0 4px 16px rgba(201,168,76,0.4);overflow:hidden;}
.logo-icon img{width:40px;height:40px;object-fit:contain;}
.logo-text h1{font-family:'Playfair Display',serif;color:var(--white);font-size:1.3rem;font-weight:700;line-height:1.1;}
.logo-text p{color:rgba(255,255,255,0.55);font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;}
.header-badge{background:rgba(255,255,255,0.08);border:1px solid rgba(201,168,76,0.25);color:var(--gold-light);font-size:0.7rem;letter-spacing:1.5px;text-transform:uppercase;padding:5px 14px;border-radius:20px;}
/* HERO */
.hero-section{text-align:center;margin-bottom:30px;animation:fadeUp 0.7s ease both;}
.hero-section h2{font-family:'Playfair Display',serif;color:var(--white);font-size:2.2rem;font-weight:900;line-height:1.2;margin-bottom:8px;}
.hero-section h2 span{color:var(--gold);}
.hero-section p{color:rgba(255,255,255,0.55);font-size:0.92rem;}
/* BOX */
.auth-box{width:100%;max-width:460px;background:rgba(255,255,255,0.97);border-radius:20px;box-shadow:0 24px 80px rgba(0,0,0,0.4);overflow:hidden;animation:fadeUp 0.8s ease 0.1s both;}
@keyframes fadeUp{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);}}
/* TABS */
.tabs{display:flex;background:var(--navy);}
.tab-btn{flex:1;padding:16px;border:none;background:transparent;color:rgba(255,255,255,0.5);font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:600;cursor:pointer;letter-spacing:0.5px;transition:all 0.25s;border-bottom:3px solid transparent;}
.tab-btn.active{color:var(--gold);border-bottom-color:var(--gold);background:rgba(201,168,76,0.08);}
/* FORM BODY */
.tab-body{padding:32px;display:none;}
.tab-body.active{display:block;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:0.78rem;font-weight:600;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;}
.form-group input,.form-group select{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--text);transition:border-color 0.2s,box-shadow 0.2s;background:#fafafa;}
.form-group input:focus,.form-group select:focus{outline:none;border-color:var(--royal);box-shadow:0 0 0 3px rgba(26,58,107,0.1);background:var(--white);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.btn-primary{width:100%;padding:13px;background:linear-gradient(135deg,var(--royal),var(--navy));color:var(--white);border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:600;cursor:pointer;letter-spacing:0.5px;transition:all 0.25s;margin-top:8px;box-shadow:0 4px 14px rgba(26,58,107,0.35);}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(26,58,107,0.45);}
.msg{font-size:0.84rem;margin-top:10px;padding:8px 12px;border-radius:6px;display:none;}
.msg.error{background:#fef2f2;color:var(--red);border:1px solid #fecaca;display:block;}
.msg.success{background:#f0fdf4;color:var(--green);border:1px solid #bbf7d0;display:block;}
/* QUICK LOGIN */
.quick-hint{margin-top:20px;padding-top:18px;border-top:1px solid #f0f0f0;font-size:0.78rem;color:var(--muted);}
.quick-hint strong{color:var(--royal);}
.hint-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.chip{background:#f0f4ff;color:var(--royal);padding:3px 10px;border-radius:12px;font-size:0.75rem;cursor:pointer;border:1px solid #dde6ff;transition:background 0.2s;}
.chip:hover{background:#dde6ff;}
/* BACK LINK */
.back-home{margin-top:20px;text-align:center;}
.back-home a{color:rgba(255,255,255,0.5);font-size:0.8rem;text-decoration:none;transition:color 0.2s;}
.back-home a:hover{color:var(--gold-light);}
/* FOOTER */
footer{position:relative;z-index:1;margin-top:auto;padding:20px;text-align:center;color:rgba(255,255,255,0.35);font-size:0.78rem;}
footer a{color:var(--gold);text-decoration:none;}
</style>
</head>
<body>
<!-- TOP NAV -->
<nav class="top-nav">
  <a href="home.php" class="brand">
    <div style="display:flex;align-items:center;gap:8px;">
      <img src="images/puc_logo.png" alt="Logo" style="width:35px;height:35px;object-fit:contain;">
      <span>Premier University Library</span>
    </div>
  </a>
  <div style="display:flex;gap:8px;">
    <a href="home.php" class="home-link">🏠 Home</a>
    <a href="about.php" class="home-link">About</a>
    <a href="contact.php" class="home-link">Contact</a>
  </div>
</nav>

<div class="bg">
  <div class="bg-circle"></div>
  <div class="bg-circle"></div>
</div>
<div class="page">
  <div class="hero-section">
    <h2>Welcome to the<br><span>Digital Library</span></h2>
    <p>Access books, course materials &amp; academic resources</p>
  </div>
  <div class="auth-box">
    <div class="tabs">
      <button class="tab-btn active" id="btnTabLogin" onclick="switchTab('login')">Login</button>
      <button class="tab-btn" id="btnTabSignup" onclick="switchTab('signup')">Sign Up</button>
    </div>

    <!-- LOGIN FORM -->
    <div id="loginBody" class="tab-body active">
      <form method="POST" action="index.php" id="loginForm" onsubmit="return handleLogin(event)">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
          <label for="loginUser">Username / Student ID</label>
          <input type="text" id="loginUser" name="username" placeholder="Enter your ID or username" required autofocus>
        </div>
        <div class="form-group">
          <label for="loginPass">Password</label>
          <input type="password" id="loginPass" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn-primary" id="loginBtn">Login →</button>
      </form>
      <div id="loginMsg" class="msg <?= $error ? 'error' : ($success ? 'success' : '') ?>"><?= esc($error ?: $success) ?></div>
      <div class="quick-hint">
        <strong>Quick access:</strong> click a role to autofill
        <div class="hint-chips">
          <span class="chip" onclick="fill('admin','admin123')">🛡️ Admin</span>
          <span class="chip" onclick="fill('teacher1','teach123')">👨‍🏫 Teacher</span>
          <span class="chip" onclick="fill('student','1234')">👨‍🎓 Student Demo</span>
          <span class="chip" onclick="fill('idea_guy','idea123')">💡 Idea Contributor</span>
          <span class="chip" onclick="fill('tester1','test123')">🧪 QA Tester</span>
        </div>
      </div>
    </div>

    <!-- SIGNUP FORM -->
    <div id="signupBody" class="tab-body">
      <form method="POST" action="index.php" id="signupForm" onsubmit="return handleSignup(event)">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
          <label for="regRole">Role</label>
          <select id="regRole" name="role" onchange="toggleStudentFields()">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="regId">ID / Username</label>
            <input type="text" id="regId" name="reg_id" placeholder="Numeric ID or username" required>
          </div>
          <div class="form-group">
            <label for="regName">Full Name</label>
            <input type="text" id="regName" name="reg_name" placeholder="Your full name" required>
          </div>
        </div>
        <div class="form-group">
          <label for="regPass">Password</label>
          <input type="password" id="regPass" name="reg_pass" placeholder="Create a password" required>
        </div>
        <div id="studentFields">
          <div class="form-row">
            <div class="form-group">
              <label for="regSection">Section</label>
              <input type="text" id="regSection" name="reg_section" placeholder="e.g. A">
            </div>
            <div class="form-group">
              <label for="regBatch">Batch</label>
              <input type="text" id="regBatch" name="reg_batch" placeholder="e.g. 46th">
            </div>
          </div>
        </div>
        <button type="submit" class="btn-primary" id="signupBtn">Create Account</button>
      </form>
      <div id="signupMsg" class="msg"></div>
    </div>
  </div>

  <div class="back-home">
    <a href="home.php">← Back to Home</a>
  </div>
</div>

<footer>
  &copy; 2026 Premier University IT &nbsp;|&nbsp; Designed &amp; Developed by <a href="#">Loop Infinity</a>
</footer>

<script src="theme.js"></script>
<script>
function switchTab(t){
  document.querySelectorAll('.tab-btn').forEach((b,i)=>b.classList.toggle('active',i===(t==='login'?0:1)));
  document.getElementById('loginBody').classList.toggle('active',t==='login');
  document.getElementById('signupBody').classList.toggle('active',t==='signup');
}
function toggleStudentFields(){
  document.getElementById('studentFields').style.display=document.getElementById('regRole').value==='student'?'block':'none';
}
function fill(u,p){
  document.getElementById('loginUser').value=u;
  document.getElementById('loginPass').value=p;
}
function showMsg(id,text,type){
  const el=document.getElementById(id);
  el.textContent=text;
  el.className='msg '+type;
}

async function handleLogin(e){
  e.preventDefault();
  const u = document.getElementById('loginUser').value.trim();
  const p = document.getElementById('loginPass').value.trim();
  if(!u || !p){
    showMsg('loginMsg','Please enter credentials.','error');
    return false;
  }
  const btn = document.getElementById('loginBtn');
  btn.textContent = 'Logging in…';
  btn.disabled = true;

  const formData = new FormData(document.getElementById('loginForm'));
  try{
    const res = await fetch('index.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });
    const data = await res.json();
    if(data.success){
      showMsg('loginMsg','Login successful! Redirecting…','success');
      setTimeout(()=>{ window.location.href = data.redirect; }, 400);
    } else {
      showMsg('loginMsg', data.message || 'Invalid credentials.', 'error');
      btn.textContent = 'Login →';
      btn.disabled = false;
    }
  }catch(err){
    document.getElementById('loginForm').submit();
  }
  return false;
}

async function handleSignup(e){
  e.preventDefault();
  const id = document.getElementById('regId').value.trim();
  const name = document.getElementById('regName').value.trim();
  const pass = document.getElementById('regPass').value;
  if(!id || !name || !pass){
    showMsg('signupMsg','Please fill all required fields.','error');
    return false;
  }
  const btn = document.getElementById('signupBtn');
  btn.textContent = 'Creating account…';
  btn.disabled = true;

  const formData = new FormData(document.getElementById('signupForm'));
  try{
    const res = await fetch('index.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });
    const data = await res.json();
    if(data.success){
      showMsg('signupMsg', data.message || 'Account created! You can now login.', 'success');
      setTimeout(()=>{
        switchTab('login');
        fill(id, pass);
        btn.textContent = 'Create Account';
        btn.disabled = false;
      }, 1000);
    } else {
      showMsg('signupMsg', data.message || 'Failed to create account.', 'error');
      btn.textContent = 'Create Account';
      btn.disabled = false;
    }
  }catch(err){
    document.getElementById('signupForm').submit();
  }
  return false;
}
</script>
</body>
</html>
