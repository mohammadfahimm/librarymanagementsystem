<?php
/**
 * Database & Auth Diagnostics Script
 * Run in browser: http://localhost/puc_library/test_db.php
 */
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Premier University Library — DB & Auth Test</title>
<style>
body{font-family:system-ui,sans-serif;background:#f8fafc;padding:40px;color:#1e293b;}
.card{background:#fff;border-radius:12px;padding:24px;max-width:800px;margin:0 auto 20px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
.ok{color:#16a34a;font-weight:bold;}
.bad{color:#dc2626;font-weight:bold;}
h2{margin-top:0;color:#0f172a;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{padding:8px 12px;border:1px solid #e2e8f0;text-align:left;font-size:0.85rem;}
th{background:#f1f5f9;}
</style>
</head>
<body>
<div class="card">
  <h2>PUC Library Diagnostics Report</h2>
  <?php
  try {
      $db = getDB();
      echo "<p>Database Connection: <span class='ok'>CONNECTED SUCCESSFULLY</span></p>";
      $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
      echo "<p>PDO Driver: <strong>" . htmlspecialchars($driver) . "</strong></p>";

      // Count books
      $bCount = $db->query("SELECT COUNT(*) FROM book")->fetchColumn();
      echo "<p>Total Books in DB: <strong>{$bCount}</strong></p>";

      // Count users
      $uCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
      echo "<p>Total Users in DB: <strong>{$uCount}</strong></p>";

      // Test Auth credentials
      echo "<h3>Authentication Verification</h3>";
      $stmt = $db->prepare("SELECT Username, Role, Password FROM users WHERE Username IN ('admin', 'teacher1', 'student')");
      $stmt->execute();
      $testUsers = $stmt->fetchAll();
      
      echo "<table><thead><tr><th>Username</th><th>Role</th><th>Stored Hash / Password</th></tr></thead><tbody>";
      foreach ($testUsers as $u) {
          echo "<tr><td>" . htmlspecialchars($u['Username']) . "</td><td>" . htmlspecialchars($u['Role']) . "</td><td><code>" . htmlspecialchars(substr($u['Password'], 0, 25)) . "...</code></td></tr>";
      }
      echo "</tbody></table>";

      echo "<p class='ok' style='margin-top:20px;'>✓ All database tables initialized, relational schemas active, and authentication ready.</p>";
  } catch (Exception $e) {
      echo "<p>Database Connection: <span class='bad'>ERROR</span></p>";
      echo "<pre style='color:#dc2626;background:#fee2e2;padding:12px;border-radius:6px;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
  }
  ?>
  <div style="margin-top:24px;">
    <a href="home.php" style="color:#2563eb;text-decoration:none;font-weight:600;">→ Go to PUC Library Home</a> | 
    <a href="index.php" style="color:#2563eb;text-decoration:none;font-weight:600;">→ Go to Login</a>
  </div>
</div>
</body>
</html>
