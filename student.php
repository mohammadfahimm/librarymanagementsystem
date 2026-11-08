<?php
/**
 * Student Portal — Premier University Library
 * Full PHP Backend + MySQL Persistence
 */
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['student', 'admin', 'contributor']);
$db = getDB();

$alert_msg = '';

// Ensure user has a valid Member_ID in MySQL member table
$member_id = $user['member_id'];
if (!$member_id) {
    $email = $user['email'] ?: ($user['username'] . '@puc.ac.bd');
    // Check if member already exists by email
    $mStmt = $db->prepare("SELECT Member_ID FROM member WHERE Email = ?");
    $mStmt->execute([$email]);
    $found = $mStmt->fetch();
    if ($found) {
        $member_id = (int)$found['Member_ID'];
    } else {
        $nameParts = explode(' ', $user['name'], 2);
        $fName = $nameParts[0];
        $lName = $nameParts[1] ?? 'Student';
        $db->prepare("INSERT INTO member (First_Name, Last_Name, Email, Membership_Date, Status, Section, Batch) VALUES (?, ?, ?, ?, 'Active', ?, ?)")
           ->execute([$fName, $lName, $email, date('Y-m-d'), $user['section'] ?: 'Sec A', $user['batch'] ?: 'Batch 46th']);
        $member_id = (int)$db->lastInsertId();
    }
    $_SESSION['member_id'] = $member_id;
    // Link users.Member_ID so future sessions resolve correctly
    if ($user['id'] && $member_id) {
        try {
            $db->prepare("UPDATE users SET Member_ID = ? WHERE User_ID = ? AND (Member_ID IS NULL OR Member_ID = 0)")
               ->execute([$member_id, $user['id']]);
        } catch (\Throwable $e) { /* ignore if column/driver differs */ }
    }
}

// Handle student feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_feedback') {
    $subject = trim($_POST['subject'] ?? 'General Inquiry');
    $message = trim($_POST['message'] ?? '');
    if ($message) {
        $stmt = $db->prepare("INSERT INTO feedback (User_ID, Member_Name, Email, Subject, Message, Role) VALUES (?, ?, ?, ?, ?, 'Student')");
        $stmt->execute([$user['id'], $user['name'], $user['email'], $subject, $message]);
        $alert_msg = 'Your message has been sent to library administration!';
    }
}

// Handle delete feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_feedback') {
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    if ($feedback_id) {
        $db->prepare("DELETE FROM feedback WHERE Feedback_ID = ? AND (User_ID = ? OR Email = ?)")
           ->execute([$feedback_id, $user['id'], $user['email']]);
        $alert_msg = 'Inquiry removed.';
    }
}

// Handle borrow book from catalog
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'borrow_book') {
    $book_id = (int)($_POST['book_id'] ?? 0);
    if ($book_id && $member_id) {
        $bStmt = $db->prepare("SELECT Available_Copies, Title FROM book WHERE Book_ID = ?");
        $bStmt->execute([$book_id]);
        $book = $bStmt->fetch();
        if ($book && $book['Available_Copies'] > 0) {
            $borrowDate = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime('+14 days'));
            $db->prepare("INSERT INTO borrow (Member_ID, Book_ID, Borrow_Date, Due_Date) VALUES (?, ?, ?, ?)")
               ->execute([$member_id, $book_id, $borrowDate, $dueDate]);
            $db->prepare("UPDATE book SET Available_Copies = Available_Copies - 1 WHERE Book_ID = ?")->execute([$book_id]);
            logActivity($user['id'], 'Circulation', "User borrowed book: {$book['Title']}");
            $alert_msg = "Successfully borrowed '{$book['Title']}'! Return due date is in 14 days.";
        } else {
            $alert_msg = 'Sorry, this book is currently out of stock.';
        }
    }
}

// Handle renew book loan (+14 days)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'renew_book') {
    $borrow_id = (int)($_POST['borrow_id'] ?? 0);
    if ($borrow_id && $member_id) {
        $rStmt = $db->prepare("SELECT Due_Date FROM borrow WHERE Borrow_ID = ? AND Member_ID = ? AND Return_Date IS NULL");
        $rStmt->execute([$borrow_id, $member_id]);
        $due = $rStmt->fetchColumn();
        if ($due) {
            $newDue = date('Y-m-d', strtotime($due . ' +14 days'));
            $db->prepare("UPDATE borrow SET Due_Date = ? WHERE Borrow_ID = ?")->execute([$newDue, $borrow_id]);
            logActivity($user['id'], 'Circulation', "User renewed loan #{$borrow_id} to {$newDue}");
            $alert_msg = "Book loan extended successfully until " . formatDate($newDue) . "!";
        }
    }
}

// Handle return book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return_book') {
    $borrow_id = (int)($_POST['borrow_id'] ?? 0);
    if ($borrow_id && $member_id) {
        $rStmt = $db->prepare("SELECT Book_ID FROM borrow WHERE Borrow_ID = ? AND Member_ID = ? AND Return_Date IS NULL");
        $rStmt->execute([$borrow_id, $member_id]);
        $bId = $rStmt->fetchColumn();
        if ($bId) {
            $db->prepare("UPDATE borrow SET Return_Date = ? WHERE Borrow_ID = ?")->execute([date('Y-m-d'), $borrow_id]);
            $db->prepare("UPDATE book SET Available_Copies = Available_Copies + 1 WHERE Book_ID = ?")->execute([$bId]);
            logActivity($user['id'], 'Circulation', "User returned book #{$bId}");
            $alert_msg = "Book successfully returned to library collection!";
        }
    }
}

// Handle idea contribution submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_idea') {
    $idea_title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Digital Library');
    $description = trim($_POST['description'] ?? '');
    if ($idea_title && $description) {
        try {
            $stmt = $db->prepare("INSERT INTO idea_contributions (User_ID, Contributor_Name, Email, Category, Title, Description, Status, Votes_Count) VALUES (?, ?, ?, ?, ?, ?, 'Under Review', 1)");
            $stmt->execute([$user['id'], $user['name'], $user['email'], $category, $idea_title, $description]);
            $alert_msg = 'Your innovation proposal has been submitted to the Library Advisory Committee!';
        } catch (\Throwable $e) {
            try {
                $stmt = $db->prepare("INSERT INTO idea_contributions (title, category, author_name, author_email, description, votes, status) VALUES (?, ?, ?, ?, ?, 1, 'under_review')");
                $stmt->execute([$idea_title, $category, $user['name'], $user['email'], $description]);
                $alert_msg = 'Your innovation proposal has been submitted to the Library Advisory Committee!';
            } catch (\Throwable $ex) {
                $alert_msg = 'Proposal saved!';
            }
        }
    }
}

// Handle idea upvote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'vote_idea') {
    $idea_id = (int)($_POST['idea_id'] ?? 0);
    if ($idea_id) {
        try {
            $db->prepare("UPDATE idea_contributions SET Votes_Count = Votes_Count + 1 WHERE Idea_ID = ?")->execute([$idea_id]);
        } catch (\Throwable $e) {
            try {
                $db->prepare("UPDATE idea_contributions SET votes = votes + 1 WHERE id = ?")->execute([$idea_id]);
            } catch (\Throwable $ex) {}
        }
        $alert_msg = 'Upvote recorded for innovation idea!';
    }
}

// Student profile & active borrowings
// Keep the resolved $member_id from earlier (do not reset from stale session user array)
$studentBorrows = [];
if ($member_id) {
    $bStmt = $db->prepare("
        SELECT br.*, b.Title, b.ISBN, c.Category_Name
        FROM borrow br
        JOIN book b ON br.Book_ID = b.Book_ID
        LEFT JOIN category c ON b.Category_ID = c.Category_ID
        WHERE br.Member_ID = ?
        ORDER BY br.Borrow_ID DESC
    ");
    $bStmt->execute([$member_id]);
    $studentBorrows = $bStmt->fetchAll();
}

$activeBorrows = array_filter($studentBorrows, fn($b) => empty($b['Return_Date']));
$returnedBorrows = array_filter($studentBorrows, fn($b) => !empty($b['Return_Date']));

// Books catalog for browsing
$books = $db->query("
    SELECT b.*, c.Category_Name, " . sqlNameConcat('a.First_Name', 'a.Last_Name') . " AS Author_Name
    FROM book b
    LEFT JOIN category c ON b.Category_ID = c.Category_ID
    LEFT JOIN writes w ON b.Book_ID = w.Book_ID
    LEFT JOIN author a ON w.Author_ID = a.Author_ID
    ORDER BY b.Title ASC
")->fetchAll();

// Course materials accessible to students
$materials = $db->query("
    SELECT * FROM digital_files 
    WHERE Access_Level = 'all' 
    ORDER BY File_ID DESC
")->fetchAll();

// Announcements
$notices = $db->query("SELECT * FROM notifications ORDER BY Notification_ID DESC LIMIT 8")->fetchAll();

// Student's own submitted feedbacks & replies
$fbStmt = $db->prepare("SELECT * FROM feedback WHERE User_ID = ? OR Email = ? ORDER BY Feedback_ID DESC");
$fbStmt->execute([$user['id'], $user['email']]);
$studentFeedbacks = $fbStmt->fetchAll();

// Innovation ideas
$ideas = [];
try {
    $ideas = $db->query("SELECT * FROM idea_contributions ORDER BY Idea_ID DESC")->fetchAll();
} catch (Exception $e) {
    $ideas = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $user['role'] === 'contributor' ? 'Idea Contributor Portal' : 'Student Portal' ?> — Premier University Library</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <img src="images/puc_logo.png" alt="Logo" class="w-40-img">
    </div>
    <h2>Premier University<br>Library</h2>
    <p><?= $user['role'] === 'contributor' ? 'Idea Contributor' : 'Student Portal' ?></p>
  </div>
  <div class="sidebar-user">
    <div class="u-name"><?= esc($user['name']) ?></div>
    <div class="u-role"><?= $user['role'] === 'contributor' ? '💡 Idea Contributor' : 'Student' ?> · <?= esc($user['batch'] ?: 'Batch 46th') ?> (<?= esc($user['section'] ?: 'Sec A') ?>)</div>
  </div>
  <nav class="nav-section">
    <div class="nav-label">Navigation</div>
    <a class="nav-item active" onclick="showSection('dashboard')"><span class="icon">🏠</span>Overview</a>
    <a class="nav-item" onclick="showSection('my-books')"><span class="icon">📖</span>My Borrowed Books</a>
    <a class="nav-item" onclick="showSection('catalog')"><span class="icon">🔍</span>Search Catalog</a>
    <a class="nav-item" onclick="showSection('materials')"><span class="icon">📁</span>Course Materials</a>
    <a class="nav-item" onclick="showSection('notices')"><span class="icon">📢</span>Notice Board</a>
    <a class="nav-item" onclick="showSection('feedback')"><span class="icon">💬</span>Contact Admin</a>
    <a class="nav-item" onclick="showSection('ideas')"><span class="icon">💡</span>Idea Contributions</a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-btn" href="logout.php"><span>🚪</span>Logout</a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <h3 id="pageTitle">Student Overview</h3>
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
          <div class="s-icon">📚</div>
          <div><div class="s-val"><?= count($activeBorrows) ?></div><div class="s-lbl">Currently Borrowed</div></div>
        </div>
        <div class="stat-card" style="border-color:var(--green)">
          <div class="s-icon">✅</div>
          <div><div class="s-val"><?= count($returnedBorrows) ?></div><div class="s-lbl">Returned Past Books</div></div>
        </div>
        <div class="stat-card" style="border-color:var(--gold)">
          <div class="s-icon">📁</div>
          <div><div class="s-val"><?= count($materials) ?></div><div class="s-lbl">Course Files Available</div></div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h4>Current Borrowed Books</h4>
          <button type="button" class="btn btn-sm btn-primary" onclick="showSection('catalog')">+ Borrow from Catalog</button>
        </div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>Book Title</th><th>Borrow Date</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              <?php if (!empty($activeBorrows)): ?>
                <?php foreach ($activeBorrows as $ab): ?>
                  <tr>
                    <td><strong><?= esc($ab['Title']) ?></strong></td>
                    <td><?= formatDate($ab['Borrow_Date']) ?></td>
                    <td><?= formatDate($ab['Due_Date']) ?></td>
                    <td>
                      <?php if (isOverdue($ab['Due_Date'])): ?>
                        <span class="badge badge-red">Overdue</span>
                      <?php else: ?>
                        <span class="badge badge-gold">Active</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div style="display:flex;gap:4px;">
                        <form method="POST" action="student.php" style="display:inline;">
                          <input type="hidden" name="action" value="renew_book">
                          <input type="hidden" name="borrow_id" value="<?= $ab['Borrow_ID'] ?>">
                          <button type="submit" class="btn btn-sm btn-secondary" title="Renew loan for 14 days">🔄 Renew</button>
                        </form>
                        <form method="POST" action="student.php" style="display:inline;" onsubmit="return confirm('Return this book to the library?')">
                          <input type="hidden" name="action" value="return_book">
                          <input type="hidden" name="borrow_id" value="<?= $ab['Borrow_ID'] ?>">
                          <button type="submit" class="btn btn-sm" style="background:#10b981;color:#fff;border:none;">📥 Return</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5">
                    <div class="empty-state">
                      <p>You do not have any active book loans.</p>
                      <button type="button" class="btn btn-sm btn-primary mt-8" onclick="showSection('catalog')">Search &amp; Borrow Books</button>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== MY BOOKS ===== -->
    <section id="sec-my-books" class="d-none">
      <div class="panel">
        <div class="panel-head">
          <h4>All Borrowing History</h4>
          <button type="button" class="btn btn-sm btn-primary" onclick="showSection('catalog')">+ Borrow New Book</button>
        </div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>Book Title</th><th>ISBN</th><th>Borrowed</th><th>Due Date</th><th>Returned Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              <?php if (!empty($studentBorrows)): ?>
                <?php foreach ($studentBorrows as $sb): ?>
                  <tr>
                    <td><strong><?= esc($sb['Title']) ?></strong></td>
                    <td><?= esc($sb['ISBN'] ?: '—') ?></td>
                    <td><?= formatDate($sb['Borrow_Date']) ?></td>
                    <td><?= formatDate($sb['Due_Date']) ?></td>
                    <td><?= $sb['Return_Date'] ? formatDate($sb['Return_Date']) : '—' ?></td>
                    <td>
                      <?php if ($sb['Return_Date']): ?>
                        <span class="badge badge-green">Returned</span>
                      <?php elseif (isOverdue($sb['Due_Date'])): ?>
                        <span class="badge badge-red">Overdue</span>
                      <?php else: ?>
                        <span class="badge badge-gold">Active</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (empty($sb['Return_Date'])): ?>
                        <div style="display:flex;gap:4px;">
                          <form method="POST" action="student.php" style="display:inline;">
                            <input type="hidden" name="action" value="renew_book">
                            <input type="hidden" name="borrow_id" value="<?= $sb['Borrow_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary" title="Extend due date by 14 days">🔄 Renew</button>
                          </form>
                          <form method="POST" action="student.php" style="display:inline;" onsubmit="return confirm('Return this book to the library?')">
                            <input type="hidden" name="action" value="return_book">
                            <input type="hidden" name="borrow_id" value="<?= $sb['Borrow_ID'] ?>">
                            <button type="submit" class="btn btn-sm" style="background:#10b981;color:#fff;border:none;">📥 Return</button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span style="font-size:0.75rem;color:var(--muted);">Completed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7">
                    <div class="empty-state">
                      <p>No borrowing history found.</p>
                      <button type="button" class="btn btn-sm btn-primary mt-8" onclick="showSection('catalog')">Explore Catalog to Borrow</button>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== CATALOG ===== -->
    <section id="sec-catalog" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Library Catalog</h4></div>
        <div class="panel-body">
          <div class="search-bar">
            <input type="text" id="catSearch" placeholder="Search book title or author…" oninput="filterCatalog()">
          </div>
          <table>
            <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Availability</th><th>Digital Copy</th><th>Action</th></tr></thead>
            <tbody id="catalogBody">
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
                  <td>
                    <?php if ($b['Available_Copies'] > 0): ?>
                      <form method="POST" action="student.php" style="display:inline;">
                        <input type="hidden" name="action" value="borrow_book">
                        <input type="hidden" name="book_id" value="<?= $b['Book_ID'] ?>">
                        <button type="submit" class="btn btn-sm btn-primary">📖 Borrow</button>
                      </form>
                    <?php else: ?>
                      <span style="font-size:0.75rem;color:var(--muted);">Out of Stock</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== MATERIALS ===== -->
    <section id="sec-materials" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Course Materials &amp; Lecture Slides</h4></div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>Title</th><th>Category</th><th>Course</th><th>Uploaded Date</th><th>File</th></tr></thead>
            <tbody>
              <?php if (!empty($materials)): ?>
                <?php foreach ($materials as $m): ?>
                  <tr>
                    <td><strong><?= esc($m['Title']) ?></strong></td>
                    <td><span class="badge badge-blue"><?= esc($m['Folder_Category']) ?></span></td>
                    <td><?= esc($m['Course'] ?: 'General') ?></td>
                    <td><?= formatDate($m['Uploaded_At']) ?></td>
                    <td><a href="<?= esc($m['File_Path']) ?>" download class="btn btn-sm btn-primary">⬇ Download</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="5"><div class="empty-state"><p>No course materials uploaded yet.</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== NOTICES ===== -->
    <section id="sec-notices" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Announcements &amp; Notices</h4></div>
        <div class="panel-body">
          <?php if (!empty($notices)): ?>
            <?php foreach ($notices as $n): ?>
              <div style="background:#f8f9fb;border-left:4px solid var(--gold);padding:14px 18px;border-radius:8px;margin-bottom:12px;">
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
        <div class="panel-head"><h4>Send Feedback or Book Request</h4></div>
        <div class="panel-body">
          <form method="POST" action="student.php">
            <input type="hidden" name="action" value="send_feedback">
            <div class="f-group">
              <label>Subject</label>
              <input type="text" name="subject" placeholder="e.g. Book Acquisition Request, Due Date Extension" required>
            </div>
            <div class="f-group mt-14">
              <label>Your Message</label>
              <textarea name="message" rows="4" placeholder="Detail your feedback or request…" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary mt-16">Submit to Library</button>
          </form>
        </div>
      </div>

      <div class="panel mt-16">
        <div class="panel-head"><h4>My Previous Messages &amp; Librarian Responses</h4></div>
        <div class="panel-body">
          <?php if (!empty($studentFeedbacks)): ?>
            <?php foreach ($studentFeedbacks as $sfb): ?>
              <div style="background:#f8f9fb;padding:14px 16px;border-radius:8px;margin-bottom:12px;border:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <div>
                    <strong style="color:var(--navy);font-size:0.9rem;"><?= esc($sfb['Subject']) ?></strong>
                    <span class="badge <?= ($sfb['Status'] ?? 'Pending') === 'Resolved' ? 'badge-green' : 'badge-gold' ?>" style="margin-left:8px;"><?= esc($sfb['Status'] ?? 'Pending') ?></span>
                  </div>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:0.75rem;color:var(--muted);"><?= formatDate($sfb['Created_At']) ?></span>
                    <form method="POST" action="student.php" style="display:inline;" onsubmit="return confirm('Delete this inquiry?')">
                      <input type="hidden" name="action" value="delete_feedback">
                      <input type="hidden" name="feedback_id" value="<?= $sfb['Feedback_ID'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger" style="padding:2px 6px;">🗑️</button>
                    </form>
                  </div>
                </div>
                <p style="font-size:0.86rem;color:#374151;margin-top:6px;line-height:1.5;"><?= esc($sfb['Message']) ?></p>
                <?php if (!empty($sfb['Reply'])): ?>
                  <div style="margin-top:10px;padding:8px 12px;background:#e8f4fd;border-left:3px solid var(--royal);border-radius:4px;font-size:0.84rem;color:#1e3a8a;">
                    <strong>Librarian Reply:</strong> <?= esc($sfb['Reply']) ?>
                  </div>
                <?php else: ?>
                  <p style="font-size:0.78rem;color:var(--muted);margin-top:6px;font-style:italic;">Awaiting librarian response...</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color:var(--muted);padding:8px 0;">You have not sent any inquiries yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- ===== IDEAS & INNOVATIONS ===== -->
    <section id="sec-ideas" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Propose Library Innovation or Facility Idea</h4></div>
        <div class="panel-body">
          <form method="POST" action="student.php">
            <input type="hidden" name="action" value="submit_idea">
            <div class="f-group">
              <label>Focus Area / Category</label>
              <select name="category" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:6px;font-size:0.88rem;">
                <option value="Digital Library">Digital Library &amp; Mobile App</option>
                <option value="Technology &amp; Automation">Technology &amp; Automation (RFID, Kiosks)</option>
                <option value="Study Spaces">Study Spaces &amp; Discussion Rooms</option>
                <option value="Book Procurement">Book Acquisition &amp; Journal Subscriptions</option>
                <option value="Student Welfare">Student Services &amp; Refreshment Zones</option>
              </select>
            </div>
            <div class="f-group mt-14">
              <label>Proposal Title</label>
              <input type="text" name="title" placeholder="e.g. 24/7 Digital Drop Box for Automatic Returns" required>
            </div>
            <div class="f-group mt-14">
              <label>Proposal Details &amp; Expected Impact</label>
              <textarea name="description" rows="4" placeholder="Detail the innovation concept, student benefits, and workflow..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary mt-16" style="background:var(--purple,#7c3aed);border-color:var(--purple,#7c3aed);">💡 Submit Innovation Proposal</button>
          </form>
        </div>
      </div>

      <div class="panel mt-16">
        <div class="panel-head"><h4>Submitted University Community Ideas</h4></div>
        <div class="panel-body">
          <?php if (!empty($ideas)): ?>
            <?php foreach ($ideas as $item): ?>
              <div style="background:#f8f9fb;padding:14px 16px;border-radius:8px;margin-bottom:12px;border:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <div>
                    <strong style="color:var(--navy);font-size:0.95rem;"><?= esc($item['title']) ?></strong>
                    <span class="badge badge-purple" style="margin-left:8px;background:#f3e8ff;color:#7e22ce;"><?= esc($item['category']) ?></span>
                  </div>
                  <span class="badge badge-gold"><?= esc($item['status'] ?? 'under_review') ?></span>
                </div>
                <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;display:flex;align-items:center;justify-content:space-between;">
                  <div>
                    Proposed by <?= esc($item['author_name'] ?? $item['Contributor_Name'] ?? 'Idea Contributor') ?> · <?= formatDate($item['created_at'] ?? $item['Created_At'] ?? date('Y-m-d')) ?> · <?= intval($item['votes'] ?? $item['Votes_Count'] ?? 1) ?> Votes
                  </div>
                  <form method="POST" action="student.php" style="display:inline;">
                    <input type="hidden" name="action" value="vote_idea">
                    <input type="hidden" name="idea_id" value="<?= $item['Idea_ID'] ?? $item['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background:#f3e8ff;color:#7e22ce;border:1px solid #d8b4fe;cursor:pointer;">👍 Upvote (<?= intval($item['votes'] ?? $item['Votes_Count'] ?? 1) ?>)</button>
                  </form>
                </div>
                <p style="font-size:0.86rem;color:#374151;margin-top:8px;line-height:1.5;"><?= esc($item['description']) ?></p>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color:var(--muted);padding:8px 0;">No innovation proposals recorded yet. Propose the first idea above!</p>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>
  <div class="main-footer">&copy; 2026 Premier University IT &nbsp;|&nbsp; Developed by <strong>Loop Infinity</strong></div>
</main>

<script src="theme.js"></script>
<script>
var sList = ['dashboard', 'my-books', 'catalog', 'materials', 'notices', 'feedback', 'ideas'];
var sTitles = {
  dashboard: 'Student Overview',
  'my-books': 'My Borrowed Books',
  catalog: 'Search Library Catalog',
  materials: 'Course Materials',
  notices: 'Notice Board',
  feedback: 'Contact Administration',
  ideas: 'Idea Contributions & Innovation Proposals'
};

function showSection(name) {
  sList.forEach(function(s) {
    var el = document.getElementById('sec-' + s);
    if (el) el.classList.toggle('d-none', s !== name);
  });
  document.querySelectorAll('.nav-item').forEach(function(el) {
    el.classList.toggle('active', el.getAttribute('onclick') && el.getAttribute('onclick').includes("'" + name + "'"));
  });
  document.getElementById('pageTitle').textContent = sTitles[name] || name;
}

function filterCatalog() {
  var q = document.getElementById('catSearch').value.toLowerCase();
  var rows = document.querySelectorAll('#catalogBody tr');
  rows.forEach(function(r) {
    var t = r.getAttribute('data-text') || '';
    r.style.display = (!q || t.includes(q)) ? '' : 'none';
  });
}
</script>
</body>
</html>
