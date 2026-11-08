<?php
/**
 * Teacher Portal — Premier University Library
 * Full PHP Backend + MySQL Persistence
 */
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['teacher', 'admin']);
$db = getDB();

$alert_msg = '';

// Handle Teacher File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_material') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Lecture Slides');
        $course = trim($_POST['course'] ?? '');
        $access = trim($_POST['access'] ?? 'all');

        if (!empty($title) && !empty($_FILES['material']['name'])) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = basename($_FILES['material']['name']);
            $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileName);
            $targetPath = $uploadDir . $safeName;

            if (move_uploaded_file($_FILES['material']['tmp_name'], $targetPath)) {
                $db->prepare("INSERT INTO digital_files (Title, Folder_Category, Course, File_Path, File_Name, File_Size, Uploaded_By, Access_Level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$title, $category, $course, 'uploads/' . $safeName, $fileName, $_FILES['material']['size'], $user['name'], $access]);
                logActivity($user['id'], 'File Manager', "Teacher uploaded course material: {$title}");
                $alert_msg = "Material uploaded successfully!";
            }
        }
    } elseif ($action === 'delete_material') {
        $file_id = (int)($_POST['file_id'] ?? 0);
        if ($file_id) {
            $db->prepare("DELETE FROM digital_files WHERE File_ID = ? AND Uploaded_By = ?")->execute([$file_id, $user['name']]);
            $alert_msg = "Material removed.";
        }
    } elseif ($action === 'edit_material') {
        $file_id = (int)($_POST['file_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Lecture Slides');
        $course = trim($_POST['course'] ?? '');
        $access = trim($_POST['access'] ?? 'all');
        if ($file_id && $title) {
            $db->prepare("UPDATE digital_files SET Title = ?, Folder_Category = ?, Course = ?, Access_Level = ? WHERE File_ID = ? AND Uploaded_By = ?")
               ->execute([$title, $category, $course, $access, $file_id, $user['name']]);
            $alert_msg = "Material information updated!";
        }
    } elseif ($action === 'send_feedback') {
        $subject = trim($_POST['subject'] ?? 'Faculty Inquiry');
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            $db->prepare("INSERT INTO feedback (User_ID, Member_Name, Email, Subject, Message, Role) VALUES (?, ?, ?, ?, ?, 'Teacher')")
               ->execute([$user['id'], $user['name'], $user['email'], $subject, $message]);
            $alert_msg = 'Your request has been submitted to library administration!';
        }
    } elseif ($action === 'delete_feedback') {
        $feedback_id = (int)($_POST['feedback_id'] ?? 0);
        if ($feedback_id) {
            $db->prepare("DELETE FROM feedback WHERE Feedback_ID = ? AND (User_ID = ? OR Email = ?)")
               ->execute([$feedback_id, $user['id'], $user['email']]);
            $alert_msg = "Inquiry removed.";
        }
    }
}

// Teacher's uploaded files (flexible match on name variants e.g. Dr. Rahman / Dr. Anisur Rahman)
$myFiles = $db->prepare("SELECT * FROM digital_files WHERE Uploaded_By = ? OR Uploaded_By LIKE ? ORDER BY File_ID DESC");
$myFiles->execute([$user['name'], '%' . $user['name'] . '%']);
$teacherFiles = $myFiles->fetchAll();

// Teacher's feedback submissions & admin responses
$tfbStmt = $db->prepare("SELECT * FROM feedback WHERE User_ID = ? OR Email = ? ORDER BY Feedback_ID DESC");
$tfbStmt->execute([$user['id'], $user['email']]);
$teacherFeedbacks = $tfbStmt->fetchAll();

// All materials
$allMaterials = $db->query("SELECT * FROM digital_files ORDER BY File_ID DESC")->fetchAll();

// Books catalog
$books = $db->query("
    SELECT b.*, c.Category_Name, " . sqlNameConcat('a.First_Name', 'a.Last_Name') . " AS Author_Name
    FROM book b
    LEFT JOIN category c ON b.Category_ID = c.Category_ID
    LEFT JOIN writes w ON b.Book_ID = w.Book_ID
    LEFT JOIN author a ON w.Author_ID = a.Author_ID
    ORDER BY b.Title ASC
")->fetchAll();

$notices = $db->query("SELECT * FROM notifications ORDER BY Notification_ID DESC LIMIT 8")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Portal — Premier University Library</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <img src="images/puc_logo.png" alt="Logo" class="w-40-img">
    </div>
    <h2>Premier University<br>Library</h2>
    <p>Faculty Portal</p>
  </div>
  <div class="sidebar-user">
    <div class="u-name"><?= esc($user['name']) ?></div>
    <div class="u-role">Faculty Member</div>
  </div>
  <nav class="nav-section">
    <div class="nav-label">Navigation</div>
    <a class="nav-item active" onclick="showSection('dashboard')"><span class="icon">🏠</span>Overview</a>
    <a class="nav-item" onclick="showSection('upload')"><span class="icon">📤</span>Upload Course Notes</a>
    <a class="nav-item" onclick="showSection('my-files')"><span class="icon">📁</span>My Uploaded Files</a>
    <a class="nav-item" onclick="showSection('catalog')"><span class="icon">🔍</span>Library Catalog</a>
    <a class="nav-item" onclick="showSection('notices')"><span class="icon">📢</span>Notice Board</a>
    <a class="nav-item" onclick="showSection('feedback')"><span class="icon">💬</span>Contact Admin</a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-btn" href="logout.php"><span>🚪</span>Logout</a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <h3 id="pageTitle">Faculty Overview</h3>
    <div class="topbar-right">
      <span class="date"><?= date('l, d F Y') ?></span>
      <a href="home.php" style="font-size:0.8rem;color:var(--royal);text-decoration:none;font-weight:600;margin-left:12px;">🌐 Home</a>
    </div>
  </div>

  <div class="content">
    <?php if ($alert_msg): ?>
      <div class="alert alert-success show"><?= esc($alert_msg) ?></div>
    <?php endif; ?>

    <!-- ===== OVERVIEW ===== -->
    <section id="sec-dashboard">
      <div class="stat-grid">
        <div class="stat-card" style="border-color:var(--royal)">
          <div class="s-icon">📁</div>
          <div><div class="s-val"><?= count($teacherFiles) ?></div><div class="s-lbl">My Uploaded Materials</div></div>
        </div>
        <div class="stat-card" style="border-color:var(--gold)">
          <div class="s-icon">📚</div>
          <div><div class="s-val"><?= count($books) ?></div><div class="s-lbl">Total Library Titles</div></div>
        </div>
        <div class="stat-card" style="border-color:var(--green)">
          <div class="s-icon">📢</div>
          <div><div class="s-val"><?= count($notices) ?></div><div class="s-lbl">Active Announcements</div></div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h4>My Recent Uploads</h4>
          <button class="btn btn-primary btn-sm" onclick="showSection('upload')">+ Upload New Material</button>
        </div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>Title</th><th>Category</th><th>Course</th><th>Uploaded Date</th><th>Access</th><th>File</th></tr></thead>
            <tbody>
              <?php if (!empty($teacherFiles)): ?>
                <?php foreach (array_slice($teacherFiles, 0, 5) as $tf): ?>
                  <tr>
                    <td><strong><?= esc($tf['Title']) ?></strong></td>
                    <td><span class="badge badge-blue"><?= esc($tf['Folder_Category']) ?></span></td>
                    <td><?= esc($tf['Course'] ?: 'General') ?></td>
                    <td><?= formatDate($tf['Uploaded_At']) ?></td>
                    <td><?= $tf['Access_Level'] === 'all' ? '<span class="badge badge-green">Students</span>' : '<span class="badge badge-gold">Faculty Only</span>' ?></td>
                    <td><a href="<?= esc($tf['File_Path']) ?>" download class="btn btn-sm btn-primary">⬇ Download</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6"><div class="empty-state"><p>You have not uploaded any materials yet.</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== UPLOAD ===== -->
    <section id="sec-upload" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Upload Academic Material / Slides</h4></div>
        <div class="panel-body">
          <form method="POST" action="teacher.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_material">
            <div class="form-grid">
              <div class="f-group"><label>Material Title *</label><input type="text" name="title" placeholder="e.g. Chapter 4 - Operating Systems" required></div>
              <div class="f-group"><label>Category</label>
                <select name="category">
                  <option>Lecture Slides</option>
                  <option>Assignment Questions</option>
                  <option>Lab Manual</option>
                  <option>Reference Notes</option>
                  <option>Sample Exam</option>
                </select>
              </div>
              <div class="f-group"><label>Course Code</label><input type="text" name="course" placeholder="e.g. CSE 311"></div>
              <div class="f-group"><label>Visibility</label>
                <select name="access">
                  <option value="all">Visible to All Enrolled Students</option>
                  <option value="teacher">Faculty / Teachers Only</option>
                </select>
              </div>
            </div>
            <div class="f-group mt-14">
              <label>Select Document / Archive (PDF, PPTX, DOCX, ZIP)</label>
              <input type="file" name="material" required>
            </div>
            <button type="submit" class="btn btn-primary mt-16">Upload Material</button>
          </form>
        </div>
      </div>
    </section>

    <!-- ===== MY FILES ===== -->
    <section id="sec-my-files" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Manage My Uploaded Documents</h4></div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>Title</th><th>Category</th><th>Course</th><th>Date</th><th>Access</th><th>File</th><th>Action</th></tr></thead>
            <tbody>
              <?php if (!empty($teacherFiles)): ?>
                <?php foreach ($teacherFiles as $f): ?>
                  <tr>
                    <td><strong><?= esc($f['Title']) ?></strong></td>
                    <td><span class="badge badge-blue"><?= esc($f['Folder_Category']) ?></span></td>
                    <td><?= esc($f['Course'] ?: '—') ?></td>
                    <td><?= formatDate($f['Uploaded_At']) ?></td>
                    <td><?= $f['Access_Level'] === 'all' ? '<span class="badge badge-green">Students</span>' : '<span class="badge badge-gold">Faculty</span>' ?></td>
                    <td><a href="<?= esc($f['File_Path']) ?>" download class="btn btn-sm btn-primary">⬇ Download</a></td>
                    <td>
                      <div style="display:flex;gap:4px;">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="openEditMaterial(<?= htmlspecialchars(json_encode($f)) ?>)">✏️</button>
                        <form method="POST" action="teacher.php" style="display:inline;" onsubmit="return confirm('Delete this file?')">
                          <input type="hidden" name="action" value="delete_material">
                          <input type="hidden" name="file_id" value="<?= $f['File_ID'] ?>">
                          <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="7"><div class="empty-state"><p>No files uploaded yet.</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== CATALOG ===== -->
    <section id="sec-catalog" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Browse University Library Catalog</h4></div>
        <div class="panel-body">
          <div class="search-bar">
            <input type="text" id="tCatSearch" placeholder="Search book title or author…" oninput="filterTeacherCatalog()">
          </div>
          <table>
            <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Availability</th><th>Digital Copy</th></tr></thead>
            <tbody id="tCatalogBody">
              <?php foreach ($books as $b): ?>
                <tr data-text="<?= strtolower(esc($b['Title'] . ' ' . ($b['Author_Name'] ?? ''))) ?>">
                  <td><strong><?= esc($b['Title']) ?></strong></td>
                  <td><?= esc($b['Author_Name'] ?: 'Academic') ?></td>
                  <td><span class="badge badge-blue"><?= esc($b['Category_Name'] ?: 'General') ?></span></td>
                  <td>
                    <?php if ($b['Available_Copies'] > 0): ?>
                      <span class="badge badge-green"><?= $b['Available_Copies'] ?> Available</span>
                    <?php else: ?>
                      <span class="badge badge-red">Out of Stock</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($b['File_Path'])): ?>
                      <a href="<?= esc($b['File_Path']) ?>" download class="btn btn-sm" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">📄 PDF</a>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== NOTICES ===== -->
    <section id="sec-notices" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Official Notices</h4></div>
        <div class="panel-body">
          <?php if (!empty($notices)): ?>
            <?php foreach ($notices as $n): ?>
              <div style="background:#f8f9fb;border-left:4px solid var(--royal);padding:14px 18px;border-radius:8px;margin-bottom:12px;">
                <h4 style="color:var(--navy);font-size:0.95rem;"><?= esc($n['Title']) ?></h4>
                <p style="font-size:0.88rem;color:#374151;margin-top:6px;line-height:1.6;"><?= esc($n['Message']) ?></p>
                <div style="font-size:0.75rem;color:var(--muted);margin-top:8px;"><?= formatDate($n['Created_At']) ?> · By <?= esc($n['Posted_By'] ?? 'Admin') ?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color:var(--muted);">No announcements at this time.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- ===== FEEDBACK ===== -->
    <section id="sec-feedback" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Contact Library Administration</h4></div>
        <div class="panel-body">
          <form method="POST" action="teacher.php">
            <input type="hidden" name="action" value="send_feedback">
            <div class="f-group">
              <label>Subject</label>
              <input type="text" name="subject" placeholder="e.g. Course Book Recommendation, Reserve Shelf Request" required>
            </div>
            <div class="f-group mt-14">
              <label>Your Message / Recommendation</label>
              <textarea name="message" rows="4" placeholder="Detail your request…" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary mt-16">Send to Library</button>
          </form>
        </div>
      </div>

      <div class="panel mt-16">
        <div class="panel-head"><h4>My Submitted Inquiries &amp; Librarian Responses</h4></div>
        <div class="panel-body">
          <?php if (!empty($teacherFeedbacks)): ?>
            <?php foreach ($teacherFeedbacks as $tfb): ?>
              <div style="background:#f8f9fb;padding:14px 16px;border-radius:8px;margin-bottom:12px;border:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <div>
                    <strong style="color:var(--navy);font-size:0.9rem;"><?= esc($tfb['Subject']) ?></strong>
                    <span class="badge <?= ($tfb['Status'] ?? 'Pending') === 'Resolved' ? 'badge-green' : 'badge-gold' ?>" style="margin-left:8px;"><?= esc($tfb['Status'] ?? 'Pending') ?></span>
                  </div>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:0.75rem;color:var(--muted);"><?= formatDate($tfb['Created_At']) ?></span>
                    <form method="POST" action="teacher.php" style="display:inline;" onsubmit="return confirm('Delete this inquiry?')">
                      <input type="hidden" name="action" value="delete_feedback">
                      <input type="hidden" name="feedback_id" value="<?= $tfb['Feedback_ID'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger" style="padding:2px 6px;">🗑️</button>
                    </form>
                  </div>
                </div>
                <p style="font-size:0.86rem;color:#374151;margin-top:6px;line-height:1.5;"><?= esc($tfb['Message']) ?></p>
                <?php if (!empty($tfb['Reply'])): ?>
                  <div style="margin-top:10px;padding:8px 12px;background:#e8f4fd;border-left:3px solid var(--royal);border-radius:4px;font-size:0.84rem;color:#1e3a8a;">
                    <strong>Librarian Reply:</strong> <?= esc($tfb['Reply']) ?>
                  </div>
                <?php else: ?>
                  <p style="font-size:0.78rem;color:var(--muted);margin-top:6px;font-style:italic;">Awaiting administration review...</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color:var(--muted);padding:8px 0;">No inquiries submitted yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>
  <div class="main-footer">&copy; 2026 Premier University IT &nbsp;|&nbsp; Developed by <strong>Loop Infinity</strong></div>
</main>

<!-- EDIT MATERIAL MODAL -->
<div class="modal-overlay" id="editMaterialModal">
  <div class="modal">
    <div class="modal-head">
      <h4>Edit Uploaded Material</h4>
      <button class="modal-close" onclick="closeModal('editMaterialModal')">✕</button>
    </div>
    <form method="POST" action="teacher.php">
      <input type="hidden" name="action" value="edit_material">
      <input type="hidden" name="file_id" id="em_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="f-group"><label>Material Title *</label><input type="text" name="title" id="em_title" required></div>
          <div class="f-group"><label>Category</label>
            <select name="category" id="em_cat">
              <option>Lecture Slides</option>
              <option>Assignment Questions</option>
              <option>Lab Manual</option>
              <option>Reference Notes</option>
              <option>Sample Exam</option>
            </select>
          </div>
          <div class="f-group"><label>Course Code</label><input type="text" name="course" id="em_course"></div>
          <div class="f-group"><label>Visibility</label>
            <select name="access" id="em_access">
              <option value="all">Visible to All Enrolled Students</option>
              <option value="teacher">Faculty / Teachers Only</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeModal('editMaterialModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script src="theme.js"></script>
<script>
var tSections = ['dashboard', 'upload', 'my-files', 'catalog', 'notices', 'feedback'];
var tTitles = {
  dashboard: 'Faculty Overview',
  upload: 'Upload Course Materials',
  'my-files': 'My Uploaded Files',
  catalog: 'Browse University Library Catalog',
  notices: 'Notice Board',
  feedback: 'Contact Administration'
};

function showSection(name) {
  tSections.forEach(function(s) {
    var el = document.getElementById('sec-' + s);
    if (el) el.classList.toggle('d-none', s !== name);
  });
  document.querySelectorAll('.nav-item').forEach(function(el) {
    el.classList.toggle('active', el.getAttribute('onclick') && el.getAttribute('onclick').includes("'" + name + "'"));
  });
  document.getElementById('pageTitle').textContent = tTitles[name] || name;
}

function openEditMaterial(file) {
  document.getElementById('em_id').value = file.File_ID;
  document.getElementById('em_title').value = file.Title || '';
  document.getElementById('em_cat').value = file.Folder_Category || 'Lecture Slides';
  document.getElementById('em_course').value = file.Course || '';
  document.getElementById('em_access').value = file.Access_Level || 'all';
  document.getElementById('editMaterialModal').classList.add('open');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

function filterTeacherCatalog() {
  var q = document.getElementById('tCatSearch').value.toLowerCase();
  var rows = document.querySelectorAll('#tCatalogBody tr');
  rows.forEach(function(r) {
    var t = r.getAttribute('data-text') || '';
    r.style.display = (!q || t.includes(q)) ? '' : 'none';
  });
}
</script>
</body>
</html>
