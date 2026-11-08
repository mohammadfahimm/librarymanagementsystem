<?php
/**
 * Admin Dashboard — Premier University Library
 * Full PHP Backend + MySQL Persistence
 */
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();

$alert_msg = '';
$alert_type = 'success';

// Handle Direct POST Actions (Book CRUD, Issue, Return, Upload, Notice, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD BOOK
    if ($action === 'add_book') {
        $title = v_str($_POST['title'] ?? '', 200);
        $author_name = v_str($_POST['author'] ?? '', 100);
        $isbn = v_str($_POST['isbn'] ?? '', 20);
        $category_name = v_str($_POST['category'] ?? 'CSE', 50);
        $quantity = (int)($_POST['quantity'] ?? 1);

        $v = new Validator();
        $v->required($title, 'Title')->length($title, 'Title', 1, 200);
        $v->required($author_name, 'Author')->length($author_name, 'Author', 2, 100);
        $v->isbn($isbn, true);
        $v->intRange($quantity, 'Quantity', 1, 500);
        $imgErr = v_image_upload($_FILES['cover_image'] ?? []);
        if ($imgErr) $v->fail($imgErr);
        $docErr = v_doc_upload($_FILES['file'] ?? []);
        if ($docErr) $v->fail($docErr);

        if (!$v->ok()) {
            $alert_msg = $v->firstError();
            $alert_type = 'danger';
        } elseif (true) {
            // Find or create Category
            $catStmt = $db->prepare("SELECT Category_ID FROM category WHERE Category_Name = ?");
            $catStmt->execute([$category_name]);
            $cat = $catStmt->fetch();
            if ($cat) {
                $category_id = $cat['Category_ID'];
            } else {
                $db->prepare("INSERT INTO category (Category_Name) VALUES (?)")->execute([$category_name]);
                $category_id = $db->lastInsertId();
            }

            // Find or create Author
            $authParts = explode(' ', $author_name, 2);
            $fName = $authParts[0];
            $lName = $authParts[1] ?? '';
            $authStmt = $db->prepare("SELECT Author_ID FROM author WHERE First_Name = ? AND Last_Name = ?");
            $authStmt->execute([$fName, $lName]);
            $auth = $authStmt->fetch();
            if ($auth) {
                $author_id = $auth['Author_ID'];
            } else {
                $db->prepare("INSERT INTO author (First_Name, Last_Name) VALUES (?, ?)")->execute([$fName, $lName]);
                $author_id = $db->lastInsertId();
            }

            // Handle optional PDF and Cover Image uploads
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $coversDir = __DIR__ . '/uploads/covers/';
            if (!is_dir($coversDir)) mkdir($coversDir, 0777, true);

            $filePath = null;
            if (!empty($_FILES['file']['name']) && ($_FILES['file']['error'] ?? 1) === UPLOAD_ERR_OK) {
                $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['file']['name']);
                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $safeName)) {
                    $filePath = 'uploads/' . $safeName;
                }
            }
            $coverPath = null;
            if (!empty($_FILES['cover_image']['name']) && ($_FILES['cover_image']['error'] ?? 1) === UPLOAD_ERR_OK) {
                $cext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                if (in_array($cext, ['jpg','jpeg','png','webp','gif'], true)) {
                    $cname = 'cover_' . time() . '.' . $cext;
                    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $coversDir . $cname)) {
                        $coverPath = 'uploads/covers/' . $cname;
                    }
                }
            }

            try {
                $stmtB = $db->prepare("INSERT INTO book (Title, ISBN, Category_ID, Quantity, Available_Copies, File_Path, Cover_Image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtB->execute([$title, $isbn ?: ('978-' . rand(100000, 999999)), $category_id, $quantity, $quantity, $filePath, $coverPath]);
            } catch (\Throwable $e) {
                $stmtB = $db->prepare("INSERT INTO book (Title, ISBN, Category_ID, Quantity, Available_Copies, File_Path) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtB->execute([$title, $isbn ?: ('978-' . rand(100000, 999999)), $category_id, $quantity, $quantity, $filePath]);
            }
            $book_id = $db->lastInsertId();

            $db->prepare("INSERT INTO writes (Author_ID, Book_ID) VALUES (?, ?)")->execute([$author_id, $book_id]);
            logActivity($user['id'], 'Book Management', "Added new book: {$title}");
            $alert_msg = "Book '{$title}' added successfully!";
        }
    }

    // EDIT BOOK
    elseif ($action === 'edit_book') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        $title = v_str($_POST['title'] ?? '', 200);
        $author_name = v_str($_POST['author'] ?? '', 100);
        $isbn = v_str($_POST['isbn'] ?? '', 20);
        $category_name = v_str($_POST['category'] ?? '', 50);
        $quantity = (int)($_POST['quantity'] ?? 1);
        $available = (int)($_POST['available'] ?? 1);

        $v = new Validator();
        if ($book_id <= 0) $v->fail('Invalid book ID.');
        $v->required($title, 'Title')->length($title, 'Title', 1, 200);
        $v->isbn($isbn, true);
        $v->intRange($quantity, 'Quantity', 1, 500);
        $v->intRange($available, 'Available copies', 0, 500);
        if ($available > $quantity) $v->fail('Available copies cannot exceed total quantity.');
        $imgErr = v_image_upload($_FILES['cover_image'] ?? []);
        if ($imgErr) $v->fail($imgErr);
        $docErr = v_doc_upload($_FILES['file'] ?? []);
        if ($docErr) $v->fail($docErr);

        if (!$v->ok()) {
            $alert_msg = $v->firstError();
            $alert_type = 'danger';
        } elseif ($book_id && $title) {
            $catStmt = $db->prepare("SELECT Category_ID FROM category WHERE Category_Name = ?");
            $catStmt->execute([$category_name]);
            $cat = $catStmt->fetch();
            $category_id = $cat ? $cat['Category_ID'] : 1;

            $db->prepare("UPDATE book SET Title = ?, ISBN = ?, Category_ID = ?, Quantity = ?, Available_Copies = ? WHERE Book_ID = ?")
               ->execute([$title, $isbn, $category_id, $quantity, $available, $book_id]);

            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $coversDir = __DIR__ . '/uploads/covers/';
            if (!is_dir($coversDir)) mkdir($coversDir, 0777, true);

            if (!empty($_FILES['file']['name']) && ($_FILES['file']['error'] ?? 1) === UPLOAD_ERR_OK) {
                $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['file']['name']);
                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $safeName)) {
                    try {
                        $db->prepare("UPDATE book SET File_Path = ? WHERE Book_ID = ?")->execute(['uploads/' . $safeName, $book_id]);
                    } catch (\Throwable $e) {}
                }
            }
            if (!empty($_FILES['cover_image']['name']) && ($_FILES['cover_image']['error'] ?? 1) === UPLOAD_ERR_OK) {
                $cext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                if (in_array($cext, ['jpg','jpeg','png','webp','gif'], true)) {
                    $cname = 'cover_' . time() . '.' . $cext;
                    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $coversDir . $cname)) {
                        try {
                            $db->prepare("UPDATE book SET Cover_Image = ? WHERE Book_ID = ?")->execute(['uploads/covers/' . $cname, $book_id]);
                        } catch (\Throwable $e) {}
                    }
                }
            }

            if ($author_name) {
                $authParts = explode(' ', $author_name, 2);
                $fName = $authParts[0]; $lName = $authParts[1] ?? '';
                try {
                    $db->prepare("UPDATE author SET First_Name = ?, Last_Name = ? WHERE Author_ID IN (SELECT Author_ID FROM writes WHERE Book_ID = ?)")->execute([$fName, $lName, $book_id]);
                } catch (\Throwable $e) {
                    // SQLite may restrict subquery update — update via join table
                    $st = $db->prepare("SELECT Author_ID FROM writes WHERE Book_ID = ? LIMIT 1");
                    $st->execute([$book_id]);
                    $aid = $st->fetchColumn();
                    if ($aid) {
                        $db->prepare("UPDATE author SET First_Name = ?, Last_Name = ? WHERE Author_ID = ?")->execute([$fName, $lName, $aid]);
                    }
                }
            }
            logActivity($user['id'], 'Book Management', "Updated book ID #{$book_id}");
            $alert_msg = "Book updated successfully!";
        }
    }

    // DELETE BOOK
    elseif ($action === 'delete_book') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        if ($book_id) {
            $db->prepare("DELETE FROM writes WHERE Book_ID = ?")->execute([$book_id]);
            $db->prepare("DELETE FROM borrow WHERE Book_ID = ?")->execute([$book_id]);
            $db->prepare("DELETE FROM book WHERE Book_ID = ?")->execute([$book_id]);
            logActivity($user['id'], 'Book Management', "Deleted book ID #{$book_id}");
            $alert_msg = "Book deleted successfully!";
        }
    }

    // ISSUE BOOK
    elseif ($action === 'issue_book') {
        $student_ident = trim($_POST['student_id'] ?? '');
        $student_name = trim($_POST['student_name'] ?? '');
        $book_id = (int)($_POST['book_id'] ?? 0);
        $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));

        if ($student_ident && $book_id) {
            // Find or create member
            $mStmt = $db->prepare("SELECT Member_ID FROM member WHERE Email = ? OR First_Name LIKE ? OR Member_ID = ?");
            $mStmt->execute([$student_ident, "%$student_ident%", is_numeric($student_ident) ? (int)$student_ident : 0]);
            $member = $mStmt->fetch();

            if (!$member) {
                $names = explode(' ', $student_name ?: $student_ident, 2);
                $db->prepare("INSERT INTO member (First_Name, Last_Name, Email, Membership_Date, Status) VALUES (?, ?, ?, ?, 'Active')")
                   ->execute([$names[0], $names[1] ?? 'Student', (is_numeric($student_ident) ? "{$student_ident}@puc.ac.bd" : $student_ident), date('Y-m-d')]);
                $member_id = $db->lastInsertId();
            } else {
                $member_id = $member['Member_ID'];
            }

            // Verify availability
            $bStmt = $db->prepare("SELECT Available_Copies FROM book WHERE Book_ID = ?");
            $bStmt->execute([$book_id]);
            $avail = $bStmt->fetchColumn();

            if ($avail > 0) {
                $db->prepare("INSERT INTO borrow (Member_ID, Book_ID, Borrow_Date, Due_Date) VALUES (?, ?, ?, ?)")
                   ->execute([$member_id, $book_id, $issue_date, $due_date]);
                $db->prepare("UPDATE book SET Available_Copies = Available_Copies - 1 WHERE Book_ID = ?")->execute([$book_id]);
                logActivity($user['id'], 'Circulation', "Issued book #{$book_id} to member #{$member_id}");
                $alert_msg = "Book issued successfully!";
            } else {
                $alert_msg = "Error: Book is currently out of stock.";
                $alert_type = 'error';
            }
        }
    }

    // RETURN BOOK
    elseif ($action === 'return_book') {
        $borrow_id = (int)($_POST['borrow_id'] ?? 0);
        if ($borrow_id) {
            $stmt = $db->prepare("SELECT Book_ID, Return_Date, Due_Date FROM borrow WHERE Borrow_ID = ?");
            $stmt->execute([$borrow_id]);
            $record = $stmt->fetch();

            if ($record && empty($record['Return_Date'])) {
                $returnDate = date('Y-m-d');
                $db->prepare("UPDATE borrow SET Return_Date = ? WHERE Borrow_ID = ?")->execute([$returnDate, $borrow_id]);
                $db->prepare("UPDATE book SET Available_Copies = Available_Copies + 1 WHERE Book_ID = ?")->execute([$record['Book_ID']]);

                // Auto fine if overdue
                $fineRate = 10.00;
                try {
                    $r = $db->query("SELECT Setting_Value FROM settings WHERE Setting_Key = 'fine_rate_per_day'")->fetchColumn();
                    if ($r !== false && $r !== null) $fineRate = (float)$r;
                } catch (\Throwable $e) {}
                $dueTs = strtotime($record['Due_Date']);
                $retTs = strtotime($returnDate);
                if ($dueTs && $retTs && $retTs > $dueTs) {
                    $daysLate = (int)ceil(($retTs - $dueTs) / 86400);
                    $amount = round($daysLate * $fineRate, 2);
                    $reason = "Returned {$daysLate} day(s) late";
                    try {
                        $chk = $db->prepare("SELECT Fine_ID FROM fine WHERE Borrow_ID = ?");
                        $chk->execute([$borrow_id]);
                        $fid = $chk->fetchColumn();
                        if ($fid) {
                            $db->prepare("UPDATE fine SET Amount = ?, Reason = ?, Payment_Status = 'Unpaid' WHERE Fine_ID = ?")->execute([$amount, $reason, $fid]);
                        } else {
                            $db->prepare("INSERT INTO fine (Borrow_ID, Amount, Reason, Payment_Status) VALUES (?, ?, ?, 'Unpaid')")->execute([$borrow_id, $amount, $reason]);
                        }
                        $alert_msg = "Book returned late. Fine of \$" . number_format($amount, 2) . " recorded ({$daysLate} day(s)).";
                    } catch (\Throwable $e) {
                        $alert_msg = "Book returned successfully (fine table unavailable).";
                    }
                } else {
                    $alert_msg = "Book returned successfully! No fine assessed.";
                }
                logActivity($user['id'], 'Circulation', "Processed return for borrow #{$borrow_id}");
            }
        }
    }

    // PAY FINE
    elseif ($action === 'pay_fine') {
        $fine_id = (int)($_POST['fine_id'] ?? 0);
        if ($fine_id) {
            try {
                $db->prepare("UPDATE fine SET Payment_Status = 'Paid', Paid_Date = ? WHERE Fine_ID = ?")->execute([date('Y-m-d'), $fine_id]);
                logActivity($user['id'], 'Fines', "Collected payment for fine #{$fine_id}");
                $alert_msg = "Fine payment recorded.";
            } catch (\Throwable $e) { $alert_msg = "Could not update fine."; }
        }
    }

    // WAIVE FINE
    elseif ($action === 'waive_fine') {
        $fine_id = (int)($_POST['fine_id'] ?? 0);
        if ($fine_id) {
            try {
                $db->prepare("UPDATE fine SET Payment_Status = 'Paid', Reason = COALESCE(Reason,'') || ' [Waived]', Paid_Date = ? WHERE Fine_ID = ?")->execute([date('Y-m-d'), $fine_id]);
                // MySQL-safe reason append fallback
                try {
                    $db->prepare("UPDATE fine SET Payment_Status = 'Paid', Reason = CONCAT(COALESCE(Reason,''), ' [Waived by admin]'), Paid_Date = ? WHERE Fine_ID = ?")->execute([date('Y-m-d'), $fine_id]);
                } catch (\Throwable $e2) {}
                logActivity($user['id'], 'Fines', "Waived fine #{$fine_id}");
                $alert_msg = "Fine waived.";
            } catch (\Throwable $e) { $alert_msg = "Could not waive fine."; }
        }
    }

    // SAVE SETTINGS
    elseif ($action === 'save_settings') {
        $settingsValidator = validate_circulation_settings(
            $_POST['loan_duration_days'] ?? null,
            $_POST['fine_rate_per_day'] ?? null,
            $_POST['max_books_per_member'] ?? null
        );
        if (!$settingsValidator->ok()) {
            $alert_msg = $settingsValidator->firstError();
            $alert_type = 'danger';
        } else {
            $pairs = [
                'library_name' => trim($_POST['library_name'] ?? 'Premier University Library'),
                'loan_duration_days' => (string)(int)$_POST['loan_duration_days'],
                'fine_rate_per_day' => (string)(float)$_POST['fine_rate_per_day'],
                'max_books_per_member' => (string)(int)$_POST['max_books_per_member'],
                'allow_reservations' => isset($_POST['allow_reservations']) ? 'yes' : 'no',
                'contact_email' => trim($_POST['contact_email'] ?? 'library@puc.ac.bd'),
                'contact_phone' => trim($_POST['contact_phone'] ?? ''),
                'library_hours' => trim($_POST['library_hours'] ?? 'Mon–Fri: 8am–10pm'),
                'popup_enabled' => isset($_POST['popup_enabled']) ? 'yes' : 'no',
                'popup_once' => isset($_POST['popup_once']) ? 'yes' : 'no',
                'popup_image' => trim($_POST['popup_image'] ?? 'images/PopUp.png') ?: 'images/PopUp.png',
                'popup_pages' => trim($_POST['popup_pages'] ?? 'home.php,about.php'),
            ];
            try {
                foreach ($pairs as $k => $v) {
                    $stmt = $db->prepare("INSERT INTO settings (Setting_Key, Setting_Value) VALUES (?, ?) ON DUPLICATE KEY UPDATE Setting_Value = VALUES(Setting_Value)");
                    try {
                        $stmt->execute([$k, $v]);
                    } catch (\Throwable $e) {
                        $db->prepare("INSERT OR REPLACE INTO settings (Setting_Key, Setting_Value) VALUES (?, ?)")->execute([$k, $v]);
                    }
                }
                logActivity($user['id'], 'Settings', 'Updated library circulation policies');
                $alert_msg = "Library settings saved.";
            } catch (\Throwable $e) {
                $alert_msg = "Settings save failed: " . $e->getMessage();
            }
        }
    }

    // ADD MEMBER
    elseif ($action === 'add_member') {
        $fn = v_str($_POST['first_name'] ?? '', 50);
        $ln = v_str($_POST['last_name'] ?? '', 50);
        $em = v_str($_POST['email'] ?? '', 100);
        $ph = v_str($_POST['phone'] ?? '', 20);
        $sec = v_str($_POST['section'] ?? '', 20);
        $batch = v_str($_POST['batch'] ?? '', 20);
        $v = new Validator();
        $v->required($fn, 'First name')->length($fn, 'First name', 1, 50);
        $v->required($ln, 'Last name')->length($ln, 'Last name', 1, 50);
        $v->email($em, 'Email');
        $v->phone($ph, true);
        if (!$v->ok()) {
            $alert_msg = $v->firstError();
            $alert_type = 'danger';
        } elseif ($fn && $ln && $em) {
            try {
                $db->prepare("INSERT INTO member (First_Name, Last_Name, Email, Phone, Membership_Date, Status, Section, Batch) VALUES (?, ?, ?, ?, ?, 'Active', ?, ?)")
                   ->execute([$fn, $ln, $em, $ph ?: null, date('Y-m-d'), $sec ?: null, $batch ?: null]);
                logActivity($user['id'], 'Members', "Added member {$fn} {$ln}");
                $alert_msg = "Member registered successfully.";
            } catch (\Throwable $e) {
                $alert_msg = "Could not add member: " . $e->getMessage();
            }
        } else {
            $alert_msg = "First name, last name, and email are required.";
        }
    }

    // UPLOAD FILE
    elseif ($action === 'upload_file') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Course Notes');
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
                logActivity($user['id'], 'File Manager', "Uploaded course material: {$title}");
                $alert_msg = "File uploaded successfully!";
            }
        }
    }

    // DELETE FILE
    elseif ($action === 'delete_file') {
        $file_id = (int)($_POST['file_id'] ?? 0);
        if ($file_id) {
            $db->prepare("DELETE FROM digital_files WHERE File_ID = ?")->execute([$file_id]);
            $alert_msg = "File deleted successfully!";
        }
    }

    // POST NOTIFICATION
    elseif ($action === 'post_notice') {
        $msg = trim($_POST['message'] ?? '');
        if (!empty($msg)) {
            $title = explode('.', $msg)[0];
            $title = substr($title, 0, 80);
            $db->prepare("INSERT INTO notifications (Title, Message, Posted_By) VALUES (?, ?, ?)")
               ->execute([$title, $msg, $user['name']]);
            logActivity($user['id'], 'Notifications', "Posted notice: {$title}");
            $alert_msg = "Notification posted successfully!";
        }
    }

    // DELETE NOTIFICATION
    elseif ($action === 'delete_notice') {
        $notice_id = (int)($_POST['notice_id'] ?? 0);
        if ($notice_id) {
            $db->prepare("DELETE FROM notifications WHERE Notification_ID = ?")->execute([$notice_id]);
            $alert_msg = "Notification removed.";
        }
    }

    // EDIT NOTIFICATION
    elseif ($action === 'edit_notice') {
        $notice_id = (int)($_POST['notice_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $msg = trim($_POST['message'] ?? '');
        if ($notice_id && $title && $msg) {
            $db->prepare("UPDATE notifications SET Title = ?, Message = ? WHERE Notification_ID = ?")->execute([$title, $msg, $notice_id]);
            logActivity($user['id'], 'Notifications', "Updated notice #{$notice_id}");
            $alert_msg = "Notice updated successfully!";
        }
    }

    // RENEW BOOK (EXTEND DUE DATE BY 14 DAYS)
    elseif ($action === 'renew_book') {
        $borrow_id = (int)($_POST['borrow_id'] ?? 0);
        if ($borrow_id) {
            $stmt = $db->prepare("SELECT Due_Date FROM borrow WHERE Borrow_ID = ? AND Return_Date IS NULL");
            $stmt->execute([$borrow_id]);
            $curDue = $stmt->fetchColumn();
            if ($curDue) {
                $newDue = date('Y-m-d', strtotime($curDue . ' +14 days'));
                $db->prepare("UPDATE borrow SET Due_Date = ? WHERE Borrow_ID = ?")->execute([$newDue, $borrow_id]);
                logActivity($user['id'], 'Circulation', "Renewed loan #{$borrow_id} to {$newDue}");
                $alert_msg = "Loan renewed successfully until " . formatDate($newDue);
            }
        }
    }


    // ADD AUTHOR
    elseif ($action === 'add_author') {
        $fn = v_str($_POST['first_name'] ?? '', 50);
        $ln = v_str($_POST['last_name'] ?? '', 50);
        $em = v_str($_POST['email'] ?? '', 100);
        $country = v_str($_POST['country'] ?? '', 50);
        $v = new Validator();
        $v->required($fn, 'First name')->length($fn, 'First name', 1, 50);
        $v->required($ln, 'Last name')->length($ln, 'Last name', 1, 50);
        $v->email($em, 'Email', true);
        $v->length($country, 'Country', 0, 50);
        if (!$v->ok()) {
            $alert_msg = $v->firstError();
            $alert_type = 'danger';
        } else {
            try {
                $db->prepare("INSERT INTO author (First_Name, Last_Name, Email, Country) VALUES (?, ?, ?, ?)")
                   ->execute([$fn, $ln, $em !== '' ? $em : null, $country !== '' ? $country : null]);
                logActivity($user['id'], 'Authors', "Added author {$fn} {$ln}");
                $alert_msg = "Author {$fn} {$ln} added.";
            } catch (\Throwable $e) {
                $alert_msg = "Could not add author (duplicate email or DB error).";
                $alert_type = 'danger';
            }
        }
    }

    // EDIT AUTHOR
    elseif ($action === 'edit_author') {
        $aid = (int)($_POST['author_id'] ?? 0);
        $fn = v_str($_POST['first_name'] ?? '', 50);
        $ln = v_str($_POST['last_name'] ?? '', 50);
        $em = v_str($_POST['email'] ?? '', 100);
        $country = v_str($_POST['country'] ?? '', 50);
        $v = new Validator();
        if ($aid <= 0) $v->fail('Invalid author ID.');
        $v->required($fn, 'First name')->required($ln, 'Last name');
        $v->email($em, 'Email', true);
        if (!$v->ok()) {
            $alert_msg = $v->firstError();
            $alert_type = 'danger';
        } else {
            $db->prepare("UPDATE author SET First_Name=?, Last_Name=?, Email=?, Country=? WHERE Author_ID=?")
               ->execute([$fn, $ln, $em !== '' ? $em : null, $country !== '' ? $country : null, $aid]);
            logActivity($user['id'], 'Authors', "Updated author #{$aid}");
            $alert_msg = "Author updated.";
        }
    }

    // DELETE AUTHOR
    elseif ($action === 'delete_author') {
        $aid = (int)($_POST['author_id'] ?? 0);
        if ($aid) {
            try {
                $db->prepare("DELETE FROM writes WHERE Author_ID=?")->execute([$aid]);
                $db->prepare("DELETE FROM author WHERE Author_ID=?")->execute([$aid]);
                logActivity($user['id'], 'Authors', "Deleted author #{$aid}");
                $alert_msg = "Author deleted.";
            } catch (\Throwable $e) {
                $alert_msg = "Could not delete author: " . $e->getMessage();
            }
        }
    }


    // DELETE BORROW RECORD
    elseif ($action === 'delete_borrow') {
        $borrow_id = (int)($_POST['borrow_id'] ?? 0);
        if ($borrow_id) {
            $stmt = $db->prepare("SELECT Book_ID, Return_Date FROM borrow WHERE Borrow_ID = ?");
            $stmt->execute([$borrow_id]);
            $rec = $stmt->fetch();
            if ($rec && empty($rec['Return_Date'])) {
                $db->prepare("UPDATE book SET Available_Copies = Available_Copies + 1 WHERE Book_ID = ?")->execute([$rec['Book_ID']]);
            }
            $db->prepare("DELETE FROM borrow WHERE Borrow_ID = ?")->execute([$borrow_id]);
            logActivity($user['id'], 'Circulation', "Deleted borrow record #{$borrow_id}");
            $alert_msg = "Borrow record removed.";
        }
    }

    // EDIT FILE
    elseif ($action === 'edit_file') {
        $file_id = (int)($_POST['file_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Course Notes');
        $course = trim($_POST['course'] ?? '');
        $access = trim($_POST['access'] ?? 'all');
        if ($file_id && $title) {
            $db->prepare("UPDATE digital_files SET Title = ?, Folder_Category = ?, Course = ?, Access_Level = ? WHERE File_ID = ?")
               ->execute([$title, $category, $course, $access, $file_id]);
            logActivity($user['id'], 'File Manager', "Updated file #{$file_id}: {$title}");
            $alert_msg = "File information updated!";
        }
    }

    // ADD USER
    elseif ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '123456');
        $role = trim($_POST['role'] ?? 'student');
        $section = trim($_POST['section'] ?? '');
        $batch = trim($_POST['batch'] ?? '');

        if ($username && $full_name && $email) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $names = explode(' ', $full_name, 2);
            // Create member first (Section / Batch live on member table)
            $db->prepare("INSERT INTO member (First_Name, Last_Name, Email, Membership_Date, Status, Section, Batch) VALUES (?, ?, ?, ?, 'Active', ?, ?)")
               ->execute([$names[0], $names[1] ?? '', $email, date('Y-m-d'), $section, $batch]);
            $member_id = $db->lastInsertId();
            // Create users row linked to member
            $db->prepare("INSERT INTO users (Username, Full_Name, Email, Password, Role, Is_Active, Member_ID) VALUES (?, ?, ?, ?, ?, 1, ?)")
               ->execute([$username, $full_name, $email, $hash, $role, $member_id]);

            logActivity($user['id'], 'User Management', "Created user account: {$username} ({$role})");
            $alert_msg = "User '{$full_name}' added successfully!";
        }
    }

    // EDIT USER
    elseif ($action === 'edit_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'student');
        $section = trim($_POST['section'] ?? '');
        $batch = trim($_POST['batch'] ?? '');

        if ($user_id && $full_name) {
            $db->prepare("UPDATE users SET Full_Name = ?, Email = ?, Role = ? WHERE User_ID = ?")
               ->execute([$full_name, $email, $role, $user_id]);
            // Keep member Section / Batch in sync when present
            $mStmt = $db->prepare("SELECT Member_ID FROM users WHERE User_ID = ?");
            $mStmt->execute([$user_id]);
            $mid = $mStmt->fetchColumn();
            if ($mid) {
                $db->prepare("UPDATE member SET First_Name = ?, Last_Name = ?, Email = ?, Section = ?, Batch = ? WHERE Member_ID = ?")
                   ->execute([
                       explode(' ', $full_name, 2)[0],
                       explode(' ', $full_name, 2)[1] ?? '',
                       $email,
                       $section,
                       $batch,
                       $mid
                   ]);
            }
            logActivity($user['id'], 'User Management', "Updated user account #{$user_id}");
            $alert_msg = "User account updated successfully!";
        }
    }

    // DELETE USER
    elseif ($action === 'delete_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id && $user_id !== $user['id']) {
            $db->prepare("DELETE FROM users WHERE User_ID = ?")->execute([$user_id]);
            logActivity($user['id'], 'User Management', "Deleted user account #{$user_id}");
            $alert_msg = "User account removed.";
        } else {
            $alert_msg = "Error: Cannot delete your own active administrator account.";
            $alert_type = 'error';
        }
    }

    // REPLY FEEDBACK
    elseif ($action === 'reply_feedback') {
        $feedback_id = (int)($_POST['feedback_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        if ($feedback_id && $reply) {
            try {
                $db->prepare("UPDATE feedback SET Reply = ?, Status = 'Resolved' WHERE Feedback_ID = ?")->execute([$reply, $feedback_id]);
            } catch (\Throwable $e) {
                // In case Reply column not in table yet
                $db->exec("ALTER TABLE feedback ADD COLUMN Reply TEXT");
                $db->prepare("UPDATE feedback SET Reply = ?, Status = 'Resolved' WHERE Feedback_ID = ?")->execute([$reply, $feedback_id]);
            }
            logActivity($user['id'], 'Feedback', "Replied to feedback #{$feedback_id}");
            $alert_msg = "Reply submitted and marked resolved!";
        }
    }

    // DELETE FEEDBACK
    elseif ($action === 'delete_feedback') {
        $feedback_id = (int)($_POST['feedback_id'] ?? 0);
        if ($feedback_id) {
            $db->prepare("DELETE FROM feedback WHERE Feedback_ID = ?")->execute([$feedback_id]);
            $alert_msg = "Feedback message deleted.";
        }
    }

    // SEND COMMUNICATION
    elseif ($action === 'send_communication') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $recipient_role = trim($_POST['recipient_role'] ?? 'All');
        $channel = trim($_POST['channel'] ?? 'Announcement');

        if ($title && $message) {
            try {
                $stmt = $db->prepare("INSERT INTO communication (Sender_ID, Sender_Name, Sender_Role, Recipient_Role, Title, Message, Channel, Status) VALUES (?, ?, 'Admin', ?, ?, ?, ?, 'Active')");
                $stmt->execute([$user['id'], $user['name'], $recipient_role, $title, $message, $channel]);
            } catch (\Throwable $e) {
                if (isSqlite()) {
                    $db->exec("CREATE TABLE IF NOT EXISTS communication (
                        Communication_ID INTEGER PRIMARY KEY AUTOINCREMENT,
                        Sender_ID INTEGER,
                        Sender_Name TEXT NOT NULL,
                        Sender_Role TEXT DEFAULT 'Admin',
                        Recipient_Role TEXT DEFAULT 'All',
                        Title TEXT NOT NULL,
                        Message TEXT NOT NULL,
                        Channel TEXT DEFAULT 'Announcement',
                        Status TEXT DEFAULT 'Active',
                        Created_At TEXT DEFAULT CURRENT_TIMESTAMP
                    )");
                } else {
                    $db->exec("CREATE TABLE IF NOT EXISTS communication (
                        Communication_ID INT AUTO_INCREMENT PRIMARY KEY,
                        Sender_ID INT DEFAULT NULL,
                        Sender_Name VARCHAR(100) NOT NULL,
                        Sender_Role VARCHAR(50) NOT NULL,
                        Recipient_Role VARCHAR(50) NOT NULL,
                        Title VARCHAR(255) NOT NULL,
                        Message TEXT NOT NULL,
                        Channel VARCHAR(50) DEFAULT 'Announcement',
                        Status VARCHAR(50) DEFAULT 'Active',
                        Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
                $stmt = $db->prepare("INSERT INTO communication (Sender_ID, Sender_Name, Sender_Role, Recipient_Role, Title, Message, Channel, Status) VALUES (?, ?, 'Admin', ?, ?, ?, ?, 'Active')");
                $stmt->execute([$user['id'], $user['name'], $recipient_role, $title, $message, $channel]);
            }
            logActivity($user['id'], 'Communication', "Broadcasted message: {$title} to {$recipient_role}");
            $alert_msg = "Communication published successfully!";
        }
    }

    // DELETE COMMUNICATION
    elseif ($action === 'delete_communication') {
        $comm_id = (int)($_POST['comm_id'] ?? 0);
        if ($comm_id) {
            $db->prepare("DELETE FROM communication WHERE Communication_ID = ?")->execute([$comm_id]);
            logActivity($user['id'], 'Communication', "Deleted communication ID #{$comm_id}");
            $alert_msg = "Communication removed.";
        }
    }

    // Return JSON if AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => $alert_type !== 'error', 'message' => $alert_msg]);
        exit;
    }
}

// Fetch all required data from database
// 1. Dashboard counts
$bookTitlesCount = (int)$db->query("SELECT COUNT(*) FROM book")->fetchColumn();
$totalQty = (int)$db->query("SELECT COALESCE(SUM(Quantity), 0) FROM book")->fetchColumn();
$totalAvailable = (int)$db->query("SELECT COALESCE(SUM(Available_Copies), 0) FROM book")->fetchColumn();
$activeIssuesCount = (int)$db->query("SELECT COUNT(*) FROM borrow WHERE Return_Date IS NULL")->fetchColumn();
$overdueCount = (int)$db->query("SELECT COUNT(*) FROM borrow WHERE Return_Date IS NULL AND Due_Date < CURRENT_DATE")->fetchColumn();
$filesCount = (int)$db->query("SELECT COUNT(*) FROM digital_files")->fetchColumn();

// 2. Books list
$booksQuery = "
  SELECT b.*, c.Category_Name, " . sqlNameConcat('a.First_Name', 'a.Last_Name') . " AS Author_Name
  FROM book b
  LEFT JOIN category c ON b.Category_ID = c.Category_ID
  LEFT JOIN writes w ON b.Book_ID = w.Book_ID
  LEFT JOIN author a ON w.Author_ID = a.Author_ID
  ORDER BY b.Book_ID DESC
";
$books = $db->query($booksQuery)->fetchAll();

// 3. Borrows / Issues
$issuesQuery = "
  SELECT br.*, b.Title AS Book_Title, " . sqlNameConcat('m.First_Name', 'm.Last_Name') . " AS Student_Name, m.Email AS Student_Email
  FROM borrow br
  JOIN book b ON br.Book_ID = b.Book_ID
  JOIN member m ON br.Member_ID = m.Member_ID
  ORDER BY br.Borrow_ID DESC
";
$allIssues = $db->query($issuesQuery)->fetchAll();
$recentIssues = array_slice($allIssues, 0, 5);
$activeIssues = array_filter($allIssues, fn($i) => empty($i['Return_Date']));
$overdueIssues = array_filter($activeIssues, fn($i) => isOverdue($i['Due_Date']));

// 4. Files
$files = $db->query("SELECT * FROM digital_files ORDER BY File_ID DESC")->fetchAll();

// 5. Users
$users = $db->query("
  SELECT u.*, m.Section, m.Batch,
  (SELECT COUNT(*) FROM borrow br WHERE br.Member_ID = u.Member_ID AND br.Return_Date IS NULL) AS Active_Borrows
  FROM users u
  LEFT JOIN member m ON u.Member_ID = m.Member_ID
  ORDER BY u.User_ID ASC
")->fetchAll();

// 6. Notices
$notices = $db->query("SELECT * FROM notifications ORDER BY Notification_ID DESC")->fetchAll();

// 7. Feedback
$feedbacks = $db->query("SELECT * FROM feedback ORDER BY Feedback_ID DESC")->fetchAll();

// 8. Communication
$communications = [];
try {
    $communications = $db->query("SELECT * FROM communication ORDER BY Communication_ID DESC")->fetchAll();
} catch (\Throwable $e) {
    try {
        if (isSqlite()) {
            $db->exec("CREATE TABLE IF NOT EXISTS communication (
                Communication_ID INTEGER PRIMARY KEY AUTOINCREMENT,
                Sender_ID INTEGER,
                Sender_Name TEXT NOT NULL,
                Sender_Role TEXT DEFAULT 'Admin',
                Recipient_Role TEXT DEFAULT 'All',
                Title TEXT NOT NULL,
                Message TEXT NOT NULL,
                Channel TEXT DEFAULT 'Announcement',
                Status TEXT DEFAULT 'Active',
                Created_At TEXT DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS communication (
                Communication_ID INT AUTO_INCREMENT PRIMARY KEY,
                Sender_ID INT DEFAULT NULL,
                Sender_Name VARCHAR(100) NOT NULL,
                Sender_Role VARCHAR(50) NOT NULL,
                Recipient_Role VARCHAR(50) NOT NULL,
                Title VARCHAR(255) NOT NULL,
                Message TEXT NOT NULL,
                Channel VARCHAR(50) DEFAULT 'Announcement',
                Status VARCHAR(50) DEFAULT 'Active',
                Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $communications = $db->query("SELECT * FROM communication ORDER BY Communication_ID DESC")->fetchAll();
    } catch (\Throwable $ex) {
        $communications = [];
    }
}

// 9. Fines
$fines = [];
$totalUnpaidFines = 0.0;
$totalPaidFines = 0.0;
try {
    $fines = $db->query("
        SELECT f.*, b.Title AS Book_Title, br.Due_Date, br.Return_Date,
               " . sqlNameConcat('m.First_Name', 'm.Last_Name') . " AS Member_Name, m.Email AS Member_Email
        FROM fine f
        JOIN borrow br ON f.Borrow_ID = br.Borrow_ID
        JOIN book b ON br.Book_ID = b.Book_ID
        JOIN member m ON br.Member_ID = m.Member_ID
        ORDER BY CASE WHEN f.Payment_Status = 'Unpaid' THEN 0 ELSE 1 END, f.Fine_ID DESC
    ")->fetchAll();
    $totalUnpaidFines = (float)$db->query("SELECT COALESCE(SUM(Amount),0) FROM fine WHERE Payment_Status = 'Unpaid'")->fetchColumn();
    $totalPaidFines = (float)$db->query("SELECT COALESCE(SUM(Amount),0) FROM fine WHERE Payment_Status = 'Paid'")->fetchColumn();
} catch (\Throwable $e) { $fines = []; }

// 10. Members
$members = [];
try {
    $members = $db->query("
        SELECT m.*,
          (SELECT COUNT(*) FROM borrow br WHERE br.Member_ID = m.Member_ID AND br.Return_Date IS NULL) AS Active_Borrows,
          (SELECT COUNT(*) FROM borrow br WHERE br.Member_ID = m.Member_ID) AS Total_Borrows
        FROM member m ORDER BY m.Member_ID DESC
    ")->fetchAll();
} catch (\Throwable $e) { $members = []; }

// 11. Settings
function admin_get_setting(PDO $db, string $key, string $default = ''): string {
    try {
        $v = $db->prepare("SELECT Setting_Value FROM settings WHERE Setting_Key = ?");
        $v->execute([$key]);
        $r = $v->fetchColumn();
        return $r !== false && $r !== null ? (string)$r : $default;
    } catch (\Throwable $e) { return $default; }
}
$cfg_library_name = admin_get_setting($db, 'library_name', 'Premier University Library');
$cfg_loan_days = admin_get_setting($db, 'loan_duration_days', '14');
$cfg_fine_rate = admin_get_setting($db, 'fine_rate_per_day', '10.00');
$cfg_max_books = admin_get_setting($db, 'max_books_per_member', '5');
$cfg_allow_res = admin_get_setting($db, 'allow_reservations', 'yes');
$cfg_email = admin_get_setting($db, 'contact_email', 'library@puc.ac.bd');
$cfg_phone = admin_get_setting($db, 'contact_phone', '');
$cfg_hours = admin_get_setting($db, 'library_hours', 'Mon–Fri: 8am–10pm | Sat–Sun: 10am–6pm');
$cfg_popup_enabled = admin_get_setting($db, 'popup_enabled', 'yes');
$cfg_popup_once = admin_get_setting($db, 'popup_once', 'yes');
$cfg_popup_image = admin_get_setting($db, 'popup_image', 'images/PopUp.png');
$cfg_popup_pages = admin_get_setting($db, 'popup_pages', 'home.php,about.php');

// 12. Activity logs
$activityLogs = [];
try {
    $activityLogs = $db->query("
        SELECT a.*, u.Username, u.Full_Name
        FROM activity_logs a
        LEFT JOIN users u ON a.User_ID = u.User_ID
        ORDER BY a.Created_At DESC LIMIT 80
    ")->fetchAll();
} catch (\Throwable $e) { $activityLogs = []; }


// Normalize alert CSS class
if ($alert_type === 'error') $alert_type = 'danger';

// Authors list
$authorsList = [];
try {
    $authorsList = $db->query("SELECT * FROM author ORDER BY Author_ID ASC")->fetchAll();
} catch (\Throwable $e) { $authorsList = []; }

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — Premier University Library</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <img src="images/puc_logo.png" alt="Premier University Logo" class="w-40-img">
    </div>
    <h2>Premier University<br>Library</h2>
    <p>Admin Panel</p>
  </div>
  <div class="sidebar-user">
    <div class="u-name" id="sideUser"><?= esc($user['name']) ?></div>
    <div class="u-role">Administrator</div>
  </div>
  <nav class="nav-section">
    <div class="nav-label">Main</div>
    <a class="nav-item active" onclick="showSection('dashboard')"><span class="icon">🏠</span>Dashboard</a>
    <a class="nav-item" onclick="showSection('books')"><span class="icon">📖</span>Books</a>
    <a class="nav-item" onclick="showSection('authors')"><span class="icon">✍️</span>Authors</a>
    <a class="nav-item" onclick="showSection('issue')"><span class="icon">📤</span>Issue &amp; Return</a>
    <a class="nav-item" onclick="showSection('files')"><span class="icon">📁</span>File Manager</a>
    <a class="nav-item" onclick="showSection('users')"><span class="icon">👥</span>Users</a>
    <a class="nav-item" onclick="showSection('members')"><span class="icon">🪪</span>Members</a>
    <a class="nav-item" onclick="showSection('fines')"><span class="icon">💰</span>Fines</a>
  </nav>
  <nav class="nav-section">
    <div class="nav-label">Communication &amp; Community</div>
    <a class="nav-item" onclick="showSection('communication')"><span class="icon">📡</span>Communication</a>
    <a class="nav-item" onclick="showSection('notices')"><span class="icon">📢</span>Notifications</a>
    <a class="nav-item" onclick="showSection('feedback')"><span class="icon">💬</span>Feedback</a>
  </nav>
  <nav class="nav-section">
    <div class="nav-label">System</div>
    <a class="nav-item" onclick="showSection('settings')"><span class="icon">⚙️</span>Settings</a>
    <a class="nav-item" onclick="showSection('activity')"><span class="icon">📋</span>Activity Log</a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-btn" href="logout.php"><span>🚪</span>Logout</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <h3 id="pageTitle">Dashboard</h3>
    <div class="topbar-right">
      <span class="date"><?= date('l, d F Y') ?></span>
      <a href="home.php" style="font-size:0.8rem;color:var(--royal);text-decoration:none;font-weight:600;margin-left:12px;">🌐 View Site</a>
    </div>
  </div>
  <div class="content">

    <?php if ($alert_msg): ?>
      <div class="alert alert-<?= $alert_type ?> show"><?= esc($alert_msg) ?></div>
    <?php endif; ?>

    <!-- ===== DASHBOARD ===== -->
    <section id="sec-dashboard">
      <div class="stat-grid" id="statsGrid">
        <div class="stat-card">
          <div class="s-icon">📚</div>
          <div><div class="s-val"><?= $bookTitlesCount ?></div><div class="s-lbl">Book Titles</div></div>
        </div>
        <div class="stat-card" style="border-color:#27ae60">
          <div class="s-icon">✅</div>
          <div><div class="s-val"><?= $totalAvailable ?></div><div class="s-lbl">Available Copies</div></div>
        </div>
        <div class="stat-card" style="border-color:#c9a84c">
          <div class="s-icon">📤</div>
          <div><div class="s-val"><?= $activeIssuesCount ?></div><div class="s-lbl">Books Issued</div></div>
        </div>
        <div class="stat-card" style="border-color:#c0392b">
          <div class="s-icon">⚠️</div>
          <div><div class="s-val"><?= $overdueCount ?></div><div class="s-lbl">Overdue</div></div>
        </div>
        <div class="stat-card" style="border-color:#7c3aed">
          <div class="s-icon">📁</div>
          <div><div class="s-val"><?= $filesCount ?></div><div class="s-lbl">Files Uploaded</div></div>
        </div>
      </div>

      <div class="grid-2-col">
        <div class="panel">
          <div class="panel-head"><h4>Recent Issues</h4></div>
          <div class="panel-body p-0">
            <table>
              <thead><tr><th>Student</th><th>Book</th><th>Due</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (!empty($recentIssues)): ?>
                  <?php foreach ($recentIssues as $ri): ?>
                    <tr>
                      <td><?= esc($ri['Student_Name'] ?: $ri['Student_Email']) ?></td>
                      <td><?= esc($ri['Book_Title']) ?></td>
                      <td><?= formatDate($ri['Due_Date']) ?></td>
                      <td>
                        <?php if ($ri['Return_Date']): ?>
                          <span class="badge badge-green">Returned</span>
                        <?php elseif (isOverdue($ri['Due_Date'])): ?>
                          <span class="badge badge-red">Overdue</span>
                        <?php else: ?>
                          <span class="badge badge-gold">Active</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4"><div class="empty-state"><p>No issues yet.</p></div></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head"><h4>Overdue Books</h4></div>
          <div class="panel-body p-0">
            <table>
              <thead><tr><th>Student</th><th>Book</th><th>Days Late</th></tr></thead>
              <tbody>
                <?php if (!empty($overdueIssues)): ?>
                  <?php foreach ($overdueIssues as $oi): ?>
                    <?php
                      $days = max(1, floor((time() - strtotime($oi['Due_Date'])) / 86400));
                    ?>
                    <tr>
                      <td><?= esc($oi['Student_Name'] ?: $oi['Student_Email']) ?></td>
                      <td><?= esc($oi['Book_Title']) ?></td>
                      <td><span class="badge badge-red"><?= $days ?> days</span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--muted);">✅ No overdue books</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== BOOKS ===== -->
    <section id="sec-books" class="d-none">
      <div class="panel">
        <div class="panel-head">
          <h4>Book Catalog</h4>
          <button class="btn btn-primary btn-sm" onclick="openAddBookModal()">+ Add Book</button>
        </div>
        <div class="panel-body">
          <div class="search-bar">
            <input type="text" id="bookSearch" placeholder="Search title, author, ISBN…" oninput="filterBooks()">
            <select id="bookCatFilter" onchange="filterBooks()">
              <option value="">All Categories</option>
              <option>CSE</option><option>EEE</option><option>Mathematics</option><option>Physics</option><option>Fiction</option><option>Fantasy</option>
            </select>
            <select id="bookStatusFilter" onchange="filterBooks()">
              <option value="">All Status</option>
              <option value="available">Available</option>
              <option value="out">Out of Stock</option>
            </select>
          </div>
          <table>
            <thead><tr><th>#</th><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Qty</th><th>Avail.</th><th>Status</th><th>File</th><th>Actions</th></tr></thead>
            <tbody id="booksBody">
              <?php foreach ($books as $b): ?>
                <tr data-title="<?= strtolower(esc($b['Title'])) ?>" data-author="<?= strtolower(esc($b['Author_Name'] ?? '')) ?>" data-isbn="<?= esc($b['ISBN']) ?>" data-category="<?= esc($b['Category_Name'] ?? '') ?>" data-avail="<?= (int)$b['Available_Copies'] ?>">
                  <td><?= $b['Book_ID'] ?></td>
                  <td><strong><?= esc($b['Title']) ?></strong></td>
                  <td><?= esc($b['Author_Name'] ?: 'Academic') ?></td>
                  <td><?= esc($b['ISBN'] ?: '—') ?></td>
                  <td><span class="badge badge-blue"><?= esc($b['Category_Name'] ?: 'General') ?></span></td>
                  <td><?= $b['Quantity'] ?></td>
                  <td><?= $b['Available_Copies'] ?></td>
                  <td><span class="badge <?= $b['Available_Copies'] > 0 ? 'badge-green' : 'badge-red' ?>"><?= $b['Available_Copies'] > 0 ? 'Available' : 'Out of Stock' ?></span></td>
                  <td>
                    <?php if (!empty($b['File_Path'])): ?>
                      <a href="<?= esc($b['File_Path']) ?>" download class="btn btn-sm" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">📄 PDF</a>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="btn btn-sm btn-gold" onclick="openEditBook(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)">✏️</button>
                    <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete this book?')">
                      <input type="hidden" name="action" value="delete_book">
                      <input type="hidden" name="book_id" value="<?= $b['Book_ID'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== ISSUE / RETURN ===== -->

    <!-- ===== AUTHORS ===== -->
    <section id="sec-authors" class="d-none">
      <div class="panel" style="margin-bottom:16px;">
        <div class="panel-head"><h4>Add Author</h4></div>
        <div class="panel-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="add_author">
            <div class="form-grid">
              <div class="f-group"><label>First Name *</label><input type="text" name="first_name" required></div>
              <div class="f-group"><label>Last Name *</label><input type="text" name="last_name" required></div>
              <div class="f-group"><label>Email</label><input type="email" name="email" placeholder="author@example.com"></div>
              <div class="f-group"><label>Country</label><input type="text" name="country" placeholder="e.g. Bangladesh"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:16px;">Add Author</button>
          </form>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h4>All Authors (<?= count($authorsList) ?>)</h4></div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>Author_ID</th><th>First_Name</th><th>Last_Name</th><th>Email</th><th>Country</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (empty($authorsList)): ?>
                <tr><td colspan="6"><div class="empty-state"><p>No authors yet.</p></div></td></tr>
              <?php else: foreach ($authorsList as $au): ?>
                <tr>
                  <td>#<?= (int)$au['Author_ID'] ?></td>
                  <td><?= esc($au['First_Name']) ?></td>
                  <td><?= esc($au['Last_Name']) ?></td>
                  <td><?= esc($au['Email'] ?? '—') ?></td>
                  <td><?= esc($au['Country'] ?? '—') ?></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-secondary" onclick='openEditAuthor(<?= json_encode($au, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP) ?>)'>Edit</button>
                    <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete this author?')">
                      <input type="hidden" name="action" value="delete_author">
                      <input type="hidden" name="author_id" value="<?= (int)$au['Author_ID'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section id="sec-issue" class="d-none">
      <div class="sec-tabs">
        <button class="sec-tab active" onclick="switchIssueTab('issue-tab', this)">Issue Book</button>
        <button class="sec-tab" onclick="switchIssueTab('return-tab', this)">Return Book</button>
        <button class="sec-tab" onclick="switchIssueTab('history-tab', this)">All Records</button>
      </div>

      <!-- Tab: Issue -->
      <div id="issue-tab">
        <div class="panel">
          <div class="panel-head"><h4>Issue a Book</h4></div>
          <div class="panel-body">
            <form method="POST" action="admin.php">
              <input type="hidden" name="action" value="issue_book">
              <div class="form-grid">
                <div class="f-group">
                  <label>Student ID / Email *</label>
                  <input type="text" name="student_id" placeholder="Student ID or Email" required>
                </div>
                <div class="f-group">
                  <label>Student Full Name</label>
                  <input type="text" name="student_name" placeholder="Full name">
                </div>
                <div class="f-group">
                  <label>Select Book *</label>
                  <select name="book_id" required>
                    <option value="">-- Choose Book --</option>
                    <?php foreach ($books as $b): ?>
                      <?php if ($b['Available_Copies'] > 0): ?>
                        <option value="<?= $b['Book_ID'] ?>"><?= esc($b['Title']) ?> (<?= $b['Available_Copies'] ?> available)</option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="f-group">
                  <label>Issue Date</label>
                  <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="f-group">
                  <label>Due Date (14 Days)</label>
                  <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                </div>
              </div>
              <button type="submit" class="btn btn-primary mt-16">Issue Book</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Tab: Return -->
      <div id="return-tab" class="d-none">
        <div class="panel">
          <div class="panel-head"><h4>Return a Book</h4></div>
          <div class="panel-body">
            <div class="search-bar">
              <input type="text" id="returnSearch" placeholder="Search by student or book title…" oninput="filterReturns()">
            </div>
            <table>
              <thead><tr><th>Issue ID</th><th>Student</th><th>Book</th><th>Issued</th><th>Due</th><th>Status</th><th>Action</th></tr></thead>
              <tbody id="returnBody">
                <?php if (!empty($activeIssues)): ?>
                  <?php foreach ($activeIssues as $ai): ?>
                    <tr data-search="<?= strtolower(esc($ai['Student_Name'] . ' ' . $ai['Book_Title'])) ?>">
                      <td>#<?= $ai['Borrow_ID'] ?></td>
                      <td><?= esc($ai['Student_Name'] ?: $ai['Student_Email']) ?></td>
                      <td><?= esc($ai['Book_Title']) ?></td>
                      <td><?= formatDate($ai['Borrow_Date']) ?></td>
                      <td><?= formatDate($ai['Due_Date']) ?></td>
                      <td><?= isOverdue($ai['Due_Date']) ? '<span class="badge badge-red">Overdue</span>' : '<span class="badge badge-gold">Active</span>' ?></td>
                      <td>
                        <div style="display:flex;gap:4px;">
                          <form method="POST" action="admin.php" style="display:inline;">
                            <input type="hidden" name="action" value="return_book">
                            <input type="hidden" name="borrow_id" value="<?= $ai['Borrow_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-success">Return</button>
                          </form>
                          <form method="POST" action="admin.php" style="display:inline;">
                            <input type="hidden" name="action" value="renew_book">
                            <input type="hidden" name="borrow_id" value="<?= $ai['Borrow_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary" title="Extend due date by 14 days">+14d</button>
                          </form>
                          <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete this borrow record?')">
                            <input type="hidden" name="action" value="delete_borrow">
                            <input type="hidden" name="borrow_id" value="<?= $ai['Borrow_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="7"><div class="empty-state"><p>No active issues.</p></div></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tab: History -->
      <div id="history-tab" class="d-none">
        <div class="panel">
          <div class="panel-head"><h4>All Issue Records</h4></div>
          <div class="panel-body p-0">
            <table>
              <thead><tr><th>ID</th><th>Student</th><th>Book</th><th>Issued</th><th>Due</th><th>Returned</th><th>Status</th><th>Action</th></tr></thead>
              <tbody>
                <?php if (!empty($allIssues)): ?>
                  <?php foreach ($allIssues as $i): ?>
                    <tr>
                      <td>#<?= $i['Borrow_ID'] ?></td>
                      <td><?= esc($i['Student_Name'] ?: $i['Student_Email']) ?></td>
                      <td><?= esc($i['Book_Title']) ?></td>
                      <td><?= formatDate($i['Borrow_Date']) ?></td>
                      <td><?= formatDate($i['Due_Date']) ?></td>
                      <td><?= $i['Return_Date'] ? formatDate($i['Return_Date']) : '—' ?></td>
                      <td>
                        <?php if ($i['Return_Date']): ?>
                          <span class="badge badge-green">Returned</span>
                        <?php elseif (isOverdue($i['Due_Date'])): ?>
                          <span class="badge badge-red">Overdue</span>
                        <?php else: ?>
                          <span class="badge badge-gold">Active</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete issue record #<?= $i['Borrow_ID'] ?>?')">
                          <input type="hidden" name="action" value="delete_borrow">
                          <input type="hidden" name="borrow_id" value="<?= $i['Borrow_ID'] ?>">
                          <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--muted);">No records found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== FILES ===== -->
    <section id="sec-files" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Upload Course Material / PDF</h4></div>
        <div class="panel-body">
          <form method="POST" action="admin.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_file">
            <div class="form-grid">
              <div class="f-group"><label>File Title *</label><input type="text" name="title" placeholder="e.g. Week 1 Lecture Slides" required></div>
              <div class="f-group"><label>Category</label>
                <select name="category">
                  <option>Course Notes</option><option>Lecture Slides</option><option>Assignment</option><option>Lab Manual</option><option>Book PDF</option><option>Other</option>
                </select>
              </div>
              <div class="f-group"><label>Course Code</label><input type="text" name="course" placeholder="e.g. CSE 101"></div>
              <div class="f-group"><label>Visible To</label>
                <select name="access">
                  <option value="all">All (Students &amp; Teachers)</option>
                  <option value="teacher">Teachers Only</option>
                </select>
              </div>
            </div>
            <div class="f-group mt-14">
              <label>Select File</label>
              <input type="file" name="material" required>
            </div>
            <button type="submit" class="btn btn-primary mt-16">Upload File</button>
          </form>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h4>File Library</h4></div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>Title</th><th>Category</th><th>Course</th><th>Uploaded</th><th>Access</th><th>File</th><th>Action</th></tr></thead>
            <tbody>
              <?php if (!empty($files)): ?>
                <?php foreach ($files as $f): ?>
                  <tr>
                    <td><strong><?= esc($f['Title']) ?></strong></td>
                    <td><span class="badge badge-blue"><?= esc($f['Folder_Category']) ?></span></td>
                    <td><?= esc($f['Course'] ?: '—') ?></td>
                    <td><?= formatDate($f['Uploaded_At']) ?></td>
                    <td><?= $f['Access_Level'] === 'all' ? '<span class="badge badge-green">All</span>' : '<span class="badge badge-gold">Teachers</span>' ?></td>
                    <td><a href="<?= esc($f['File_Path']) ?>" download class="btn btn-sm" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">⬇ Download</a></td>
                    <td>
                      <div style="display:flex;gap:4px;">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="openEditFile(<?= htmlspecialchars(json_encode($f)) ?>)">✏️</button>
                        <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete this file?')">
                          <input type="hidden" name="action" value="delete_file">
                          <input type="hidden" name="file_id" value="<?= $f['File_ID'] ?>">
                          <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="7"><div class="empty-state"><p>No materials uploaded yet.</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== USERS ===== -->
    <section id="sec-users" class="d-none">
      <div class="panel">
        <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;">
          <h4>Registered Users</h4>
          <button type="button" class="btn btn-sm btn-primary" onclick="openAddUserModal()">+ Add New User</button>
        </div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>ID/Username</th><th>Name</th><th>Email</th><th>Role</th><th>Section</th><th>Batch</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= esc($u['Username']) ?></td>
                  <td><strong><?= esc($u['Full_Name']) ?></strong></td>
                  <td><?= esc($u['Email']) ?></td>
                  <td><span class="badge <?= $u['Role']==='admin'?'badge-red':($u['Role']==='teacher'?'badge-gold':'badge-blue') ?>"><?= esc($u['Role']) ?></span></td>
                  <td><?= esc($u['Section'] ?: '—') ?></td>
                  <td><?= esc($u['Batch'] ?: '—') ?></td>
                  <td>
                    <div style="display:flex;gap:4px;">
                      <button type="button" class="btn btn-sm btn-secondary" onclick="openEditUser(<?= htmlspecialchars(json_encode($u)) ?>)">✏️</button>
                      <?php if ($u['User_ID'] != $user['id']): ?>
                        <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete user account <?= esc($u['Username']) ?>?')">
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="user_id" value="<?= $u['User_ID'] ?>">
                          <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== COMMUNICATION ===== -->
    <section id="sec-communication" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Compose &amp; Broadcast Communication Message</h4></div>
        <div class="panel-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="send_communication">
            <div class="form-grid">
              <div class="f-group">
                <label>Target Audience / Recipient Role *</label>
                <select name="recipient_role">
                  <option value="All">All University Roles (Students, Teachers, Contributors)</option>
                  <option value="Teacher">Faculty &amp; Teachers Only</option>
                  <option value="Student">Students Only</option>
                  <option value="Contributor">Idea Contributors Only</option>
                </select>
              </div>
              <div class="f-group">
                <label>Communication Channel</label>
                <select name="channel">
                  <option value="Announcement">Official Announcement</option>
                  <option value="Notice">Policy &amp; Academic Notice</option>
                  <option value="Alert">Urgent Clearance Alert</option>
                  <option value="Direct">Administrative Direct Message</option>
                </select>
              </div>
            </div>
            <div class="f-group mt-14">
              <label>Communication Subject / Title *</label>
              <input type="text" name="title" placeholder="e.g. End of Semester Library Clearance &amp; Book Return Schedule" required>
            </div>
            <div class="f-group mt-14">
              <label>Detailed Message Content *</label>
              <textarea name="message" rows="4" placeholder="Draft the message or advisory to be broadcasted to the target audience..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary mt-16">📡 Broadcast Communication</button>
          </form>
        </div>
      </div>

      <div class="panel mt-16">
        <div class="panel-head">
          <h4>Communication Logs &amp; Dispatched Messages</h4>
          <span class="badge badge-blue"><?= count($communications) ?> Messages Recorded</span>
        </div>
        <div class="panel-body p-0">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Subject &amp; Excerpt</th>
                <th>Channel</th>
                <th>Sender</th>
                <th>Target Audience</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($communications)): ?>
                <?php foreach ($communications as $cm): ?>
                  <tr>
                    <td class="font-mono" style="color:var(--muted);font-size:0.8rem;">#<?= $cm['Communication_ID'] ?></td>
                    <td>
                      <strong><?= esc($cm['Title']) ?></strong>
                      <p style="font-size:0.8rem;color:#4b5563;margin-top:2px;line-height:1.4;"><?= esc(substr($cm['Message'], 0, 100)) ?><?= strlen($cm['Message']) > 100 ? '...' : '' ?></p>
                    </td>
                    <td>
                      <span class="badge <?= $cm['Channel'] === 'Alert' ? 'badge-red' : ($cm['Channel'] === 'Notice' ? 'badge-gold' : 'badge-blue') ?>">
                        <?= esc($cm['Channel']) ?>
                      </span>
                    </td>
                    <td style="font-size:0.85rem;"><?= esc($cm['Sender_Name']) ?></td>
                    <td>
                      <span class="badge" style="background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;">
                        <?= esc($cm['Recipient_Role']) ?>
                      </span>
                    </td>
                    <td style="font-size:0.8rem;color:var(--muted);"><?= formatDate($cm['Created_At']) ?></td>
                    <td><span class="badge badge-green"><?= esc($cm['Status'] ?: 'Active') ?></span></td>
                    <td>
                      <form method="POST" action="admin.php" onsubmit="return confirm('Remove communication message?')">
                        <input type="hidden" name="action" value="delete_communication">
                        <input type="hidden" name="comm_id" value="<?= $cm['Communication_ID'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8"><div class="empty-state"><p>No communication records logged yet. Broadcast your first message above.</p></div></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== NOTICES ===== -->
    <section id="sec-notices" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Post Notification</h4></div>
        <div class="panel-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="post_notice">
            <div class="f-group">
              <label>Message</label>
              <textarea name="message" rows="3" class="notice-textarea" placeholder="Type notification message…" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary mt-12">Post Notification</button>
          </form>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h4>All Notifications</h4></div>
        <div class="panel-body">
          <?php if (!empty($notices)): ?>
            <?php foreach ($notices as $n): ?>
              <div style="background:#f8f9fb;border-left:4px solid var(--royal);padding:12px 16px;border-radius:8px;margin-bottom:10px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div>
                  <p style="font-size:0.9rem;font-weight:600;color:var(--navy);"><?= esc($n['Title']) ?></p>
                  <p style="font-size:0.86rem;color:#374151;margin-top:4px;"><?= esc($n['Message']) ?></p>
                  <p style="font-size:0.75rem;color:var(--muted);margin-top:6px;"><?= formatDate($n['Created_At']) ?> · <?= esc($n['Posted_By'] ?? 'Admin') ?></p>
                </div>
                <div style="display:flex;gap:4px;">
                  <button type="button" class="btn btn-sm btn-secondary" onclick="openEditNotice(<?= htmlspecialchars(json_encode($n)) ?>)">✏️</button>
                  <form method="POST" action="admin.php" onsubmit="return confirm('Delete notice?')">
                    <input type="hidden" name="action" value="delete_notice">
                    <input type="hidden" name="notice_id" value="<?= $n['Notification_ID'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color:var(--muted);">No notifications posted.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- ===== FEEDBACK ===== -->
    <section id="sec-feedback" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Received Messages &amp; Feedback</h4></div>
        <div class="panel-body">
          <?php if (!empty($feedbacks)): ?>
            <?php foreach ($feedbacks as $fb): ?>
              <div style="background:#f8f9fb;padding:14px 16px;border-radius:8px;margin-bottom:12px;border:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                  <div>
                    <strong style="font-size:0.9rem;color:var(--navy);"><?= esc($fb['Member_Name']) ?> (<?= esc($fb['Email']) ?>)</strong>
                    <span class="badge <?= ($fb['Status'] ?? 'Pending') === 'Resolved' ? 'badge-green' : 'badge-gold' ?>" style="margin-left:8px;"><?= esc($fb['Status'] ?? 'Pending') ?></span>
                    <span class="badge badge-blue" style="margin-left:4px;"><?= esc($fb['Role'] ?? 'Member') ?></span>
                  </div>
                  <span style="font-size:0.75rem;color:var(--muted);"><?= formatDate($fb['Created_At']) ?></span>
                </div>
                <div style="font-size:0.85rem;color:var(--gold);font-weight:600;margin-top:6px;"><?= esc($fb['Subject']) ?></div>
                <p style="font-size:0.88rem;margin-top:6px;color:#374151;line-height:1.6;"><?= esc($fb['Message']) ?></p>
                <?php if (!empty($fb['Reply'])): ?>
                  <div style="margin-top:10px;padding:8px 12px;background:#e8f4fd;border-left:3px solid var(--royal);border-radius:4px;font-size:0.84rem;color:#1e3a8a;">
                    <strong>Librarian Reply:</strong> <?= esc($fb['Reply']) ?>
                  </div>
                <?php endif; ?>
                <div style="margin-top:12px;display:flex;gap:6px;justify-content:flex-end;">
                  <button type="button" class="btn btn-sm btn-primary" onclick="openReplyFeedback(<?= htmlspecialchars(json_encode($fb)) ?>)">💬 <?= empty($fb['Reply']) ? 'Reply' : 'Update Reply' ?></button>
                  <form method="POST" action="admin.php" onsubmit="return confirm('Delete this feedback inquiry?')">
                    <input type="hidden" name="action" value="delete_feedback">
                    <input type="hidden" name="feedback_id" value="<?= $fb['Feedback_ID'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color:var(--muted);">No feedback received yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- ===== MEMBERS ===== -->
    <section id="sec-members" class="d-none">
      <div class="panel" style="margin-bottom:16px;">
        <div class="panel-head"><h4>Add Library Member</h4></div>
        <div class="panel-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="add_member">
            <div class="form-grid">
              <div class="f-group"><label>First Name *</label><input type="text" name="first_name" required></div>
              <div class="f-group"><label>Last Name *</label><input type="text" name="last_name" required></div>
              <div class="f-group"><label>Email *</label><input type="email" name="email" required></div>
              <div class="f-group"><label>Phone</label><input type="text" name="phone"></div>
              <div class="f-group"><label>Section</label><input type="text" name="section" placeholder="e.g. A"></div>
              <div class="f-group"><label>Batch</label><input type="text" name="batch" placeholder="e.g. 46th"></div>
            </div>
            <button type="submit" class="btn btn-primary mt-16">Register Member</button>
          </form>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h4>All Members (<?= count($members) ?>)</h4></div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Section/Batch</th><th>Active Loans</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($members)): ?>
                <tr><td colspan="7"><div class="empty-state"><p>No members yet.</p></div></td></tr>
              <?php else: foreach ($members as $m): ?>
                <tr>
                  <td>#<?= (int)$m['Member_ID'] ?></td>
                  <td><strong><?= esc($m['First_Name'].' '.$m['Last_Name']) ?></strong></td>
                  <td><?= esc($m['Email']) ?></td>
                  <td><?= esc(($m['Section']??'—').' / '.($m['Batch']??'—')) ?></td>
                  <td><?= (int)($m['Active_Borrows']??0) ?></td>
                  <td><?= (int)($m['Total_Borrows']??0) ?></td>
                  <td><span class="badge badge-green"><?= esc($m['Status']??'Active') ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== FINES ===== -->
    <section id="sec-fines" class="d-none">
      <div class="stat-grid" style="margin-bottom:16px;">
        <div class="stat-card" style="border-color:#c0392b"><div class="s-icon">⚠️</div><div><div class="s-val">$<?= number_format($totalUnpaidFines,2) ?></div><div class="s-lbl">Unpaid Fines</div></div></div>
        <div class="stat-card" style="border-color:#27ae60"><div class="s-icon">✅</div><div><div class="s-val">$<?= number_format($totalPaidFines,2) ?></div><div class="s-lbl">Collected</div></div></div>
        <div class="stat-card"><div class="s-icon">💰</div><div><div class="s-val"><?= count($fines) ?></div><div class="s-lbl">Fine Records</div></div></div>
      </div>
      <div class="panel">
        <div class="panel-head"><h4>Fines &amp; Penalties</h4></div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>ID</th><th>Member</th><th>Book</th><th>Due / Return</th><th>Reason</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              <?php if (empty($fines)): ?>
                <tr><td colspan="8"><div class="empty-state"><p>No fine records. Overdue returns will create fines automatically.</p></div></td></tr>
              <?php else: foreach ($fines as $f):
                $unpaid = ($f['Payment_Status'] ?? '') === 'Unpaid';
              ?>
                <tr>
                  <td>#<?= (int)$f['Fine_ID'] ?></td>
                  <td><?= esc($f['Member_Name'] ?? '') ?><br><small style="color:var(--muted)"><?= esc($f['Member_Email']??'') ?></small></td>
                  <td><?= esc($f['Book_Title'] ?? '') ?></td>
                  <td><small>Due <?= formatDate($f['Due_Date']??'') ?><br>Ret <?= formatDate($f['Return_Date']??'') ?></small></td>
                  <td><?= esc($f['Reason'] ?? '—') ?></td>
                  <td><strong>$<?= number_format((float)$f['Amount'], 2) ?></strong></td>
                  <td><?= $unpaid ? '<span class="badge badge-red">Unpaid</span>' : '<span class="badge badge-green">Paid</span>' ?></td>
                  <td>
                    <?php if ($unpaid && (float)$f['Amount'] > 0): ?>
                      <form method="POST" action="admin.php" style="display:inline"><input type="hidden" name="action" value="pay_fine"><input type="hidden" name="fine_id" value="<?= (int)$f['Fine_ID'] ?>"><button class="btn btn-sm btn-success" type="submit">Pay</button></form>
                      <form method="POST" action="admin.php" style="display:inline" onsubmit="return confirm('Waive this fine?')"><input type="hidden" name="action" value="waive_fine"><input type="hidden" name="fine_id" value="<?= (int)$f['Fine_ID'] ?>"><button class="btn btn-sm btn-gold" type="submit">Waive</button></form>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ===== SETTINGS ===== -->
    <section id="sec-settings" class="d-none">
      <div class="panel">
        <div class="panel-head"><h4>Circulation Policies &amp; Settings</h4></div>
        <div class="panel-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="save_settings">
            <div class="form-grid">
              <div class="f-group"><label>Library Name</label><input type="text" name="library_name" value="<?= esc($cfg_library_name) ?>"></div>
              <div class="f-group"><label>Loan Duration (days)</label><input type="number" name="loan_duration_days" min="1" max="180" value="<?= esc($cfg_loan_days) ?>"></div>
              <div class="f-group"><label>Fine Rate ($ / day)</label><input type="number" step="0.5" min="0" name="fine_rate_per_day" value="<?= esc($cfg_fine_rate) ?>"></div>
              <div class="f-group"><label>Max Books per Member</label><input type="number" name="max_books_per_member" min="1" max="50" value="<?= esc($cfg_max_books) ?>"></div>
              <div class="f-group"><label>Contact Email</label><input type="email" name="contact_email" value="<?= esc($cfg_email) ?>"></div>
              <div class="f-group"><label>Contact Phone</label><input type="text" name="contact_phone" value="<?= esc($cfg_phone) ?>"></div>
              <div class="f-group" style="grid-column:1 / -1;"><label>Library Hours</label><input type="text" name="library_hours" value="<?= esc($cfg_hours) ?>"></div>
              <div class="f-group" style="grid-column:1 / -1;">
                <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:0.9rem;color:var(--navy);">
                  <input type="checkbox" name="allow_reservations" value="yes" <?= $cfg_allow_res==='yes'?'checked':'' ?> style="width:auto;">
                  Allow Online Reservation Holds
                </label>
              </div>
            </div>
            <input type="hidden" name="popup_image" value="<?= esc($cfg_popup_image) ?>">
            <input type="hidden" name="popup_pages" value="<?= esc($cfg_popup_pages) ?>">
            <?php if ($cfg_popup_enabled === 'yes'): ?><input type="hidden" name="popup_enabled" value="yes"><?php endif; ?>
            <?php if ($cfg_popup_once === 'yes'): ?><input type="hidden" name="popup_once" value="yes"><?php endif; ?>
            <button type="submit" class="btn btn-primary" style="margin-top:16px;">Save Circulation Settings</button>
          </form>
        </div>
      </div>

      <div class="panel" style="margin-top:16px;">
        <div class="panel-head"><h4>Team Popup Manager</h4></div>
        <div class="panel-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="save_settings">
            <!-- keep other settings from being wiped: hidden echoes current circulation values -->
            <input type="hidden" name="library_name" value="<?= esc($cfg_library_name) ?>">
            <input type="hidden" name="loan_duration_days" value="<?= esc($cfg_loan_days) ?>">
            <input type="hidden" name="fine_rate_per_day" value="<?= esc($cfg_fine_rate) ?>">
            <input type="hidden" name="max_books_per_member" value="<?= esc($cfg_max_books) ?>">
            <input type="hidden" name="contact_email" value="<?= esc($cfg_email) ?>">
            <input type="hidden" name="contact_phone" value="<?= esc($cfg_phone) ?>">
            <input type="hidden" name="library_hours" value="<?= esc($cfg_hours) ?>">
            <?php if ($cfg_allow_res === 'yes'): ?><input type="hidden" name="allow_reservations" value="yes"><?php endif; ?>

            <div class="form-grid">
              <div class="f-group" style="grid-column:1 / -1;">
                <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:0.9rem;color:var(--navy);">
                  <input type="checkbox" name="popup_enabled" value="yes" <?= $cfg_popup_enabled==='yes'?'checked':'' ?> style="width:auto;">
                  Enable team popup on selected pages
                </label>
              </div>
              <div class="f-group" style="grid-column:1 / -1;">
                <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:0.9rem;color:var(--navy);">
                  <input type="checkbox" name="popup_once" value="yes" <?= $cfg_popup_once==='yes'?'checked':'' ?> style="width:auto;">
                  Show only once per browser session
                </label>
              </div>
              <div class="f-group" style="grid-column:1 / -1;">
                <label>Popup image path</label>
                <input type="text" name="popup_image" value="<?= esc($cfg_popup_image) ?>" placeholder="images/PopUp.png">
                <small style="color:var(--muted);margin-top:4px;">Relative to site root. File: images/PopUp.png</small>
              </div>
              <div class="f-group" style="grid-column:1 / -1;">
                <label>Pages that show the popup (comma-separated filenames)</label>
                <input type="text" name="popup_pages" value="<?= esc($cfg_popup_pages) ?>" placeholder="home.php,about.php,product-list.php">
                <small style="color:var(--muted);margin-top:4px;">Examples: home.php, about.php, product-list.php, product-details.php, index.php — use * for all pages</small>
              </div>
            </div>
            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <button type="submit" class="btn btn-primary">Save Popup Settings</button>
              <a href="<?= esc($cfg_popup_image) ?>" target="_blank" class="btn btn-secondary">Preview image</a>
            </div>
          </form>
          <div style="margin-top:18px;padding:12px;background:#f8fafc;border-radius:10px;border:1px solid var(--border);">
            <div style="font-size:0.8rem;color:var(--muted);margin-bottom:8px;">Preview</div>
            <img src="<?= esc($cfg_popup_image) ?>" alt="Popup preview" style="max-width:100%;max-height:220px;border-radius:8px;object-fit:contain;background:#0a1628;">
          </div>
        </div>
      </div>
    </section>

    <!-- ===== ACTIVITY LOG ===== -->
    <section id="sec-activity" class="d-none">
      <div class="panel" style="margin-top:0;">
        <div class="panel-head"><h4>System Activity &amp; Audit Trail</h4></div>
        <div class="panel-body p-0">
          <table>
            <thead><tr><th>Time</th><th>User</th><th>Module</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
              <?php if (empty($activityLogs)): ?>
                <tr><td colspan="5"><div class="empty-state"><p>No activity logged yet.</p></div></td></tr>
              <?php else: foreach ($activityLogs as $log): ?>
                <tr>
                  <td><small><?= esc($log['Created_At'] ?? '') ?></small></td>
                  <td><?= esc($log['Full_Name'] ?? $log['Username'] ?? 'System') ?></td>
                  <td><span class="badge badge-blue"><?= esc($log['Target_Module'] ?? $log['Action_Type'] ?? 'General') ?></span></td>
                  <td><?= esc($log['Action_Description'] ?? $log['Details'] ?? '') ?></td>
                  <td><small><?= esc($log['IP_Address'] ?? '—') ?></small></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </div>

<div class="main-footer">&copy; 2026 Premier University IT &nbsp;|&nbsp; Developed by <strong>Loop Infinity</strong></div>
</main>

<!-- EDIT AUTHOR MODAL -->
<div class="modal-overlay" id="editAuthorModal">
  <div class="modal">
    <div class="modal-head">
      <h4>Edit Author</h4>
      <button class="modal-close" onclick="closeModal('editAuthorModal')">✕</button>
    </div>
    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="edit_author">
      <input type="hidden" name="author_id" id="ea_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="f-group"><label>First Name *</label><input type="text" name="first_name" id="ea_fn" required></div>
          <div class="f-group"><label>Last Name *</label><input type="text" name="last_name" id="ea_ln" required></div>
          <div class="f-group"><label>Email</label><input type="email" name="email" id="ea_email"></div>
          <div class="f-group"><label>Country</label><input type="text" name="country" id="ea_country"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeModal('editAuthorModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD BOOK MODAL -->
<div class="modal-overlay" id="addBookModal">
  <div class="modal">
    <div class="modal-head">
      <h4>Add New Book</h4>
      <button class="modal-close" onclick="closeModal('addBookModal')">✕</button>
    </div>
    <form method="POST" action="admin.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_book">
      <div class="modal-body">
        <div class="form-grid">
          <div class="f-group"><label>Title *</label><input type="text" name="title" required placeholder="Book title"></div>
          <div class="f-group"><label>Author *</label><input type="text" name="author" required placeholder="Author name"></div>
          <div class="f-group"><label>ISBN</label><input type="text" name="isbn" placeholder="e.g. 978-0132576277"></div>
          <div class="f-group"><label>Category</label>
            <select name="category">
              <option>CSE</option><option>EEE</option><option>Mathematics</option><option>Physics</option><option>Fiction</option><option>Fantasy</option><option>Other</option>
            </select>
          </div>
          <div class="f-group"><label>Quantity</label><input type="number" name="quantity" value="3" min="1"></div>
          <div class="f-group"><label>Attach PDF (optional)</label><input type="file" name="file" accept=".pdf,.doc,.docx"></div>
          <div class="f-group"><label>Cover Image (optional)</label><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeModal('addBookModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Book</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT BOOK MODAL -->
<div class="modal-overlay" id="editBookModal">
  <div class="modal">
    <div class="modal-head">
      <h4>Edit Book</h4>
      <button class="modal-close" onclick="closeModal('editBookModal')">✕</button>
    </div>
    <form method="POST" action="admin.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_book">
      <input type="hidden" name="book_id" id="eb_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="f-group"><label>Title</label><input type="text" name="title" id="eb_title" required></div>
          <div class="f-group"><label>Author</label><input type="text" name="author" id="eb_author"></div>
          <div class="f-group"><label>ISBN</label><input type="text" name="isbn" id="eb_isbn"></div>
          <div class="f-group"><label>Category</label>
            <select name="category" id="eb_cat">
              <option>CSE</option><option>EEE</option><option>Mathematics</option><option>Physics</option><option>Fiction</option><option>Fantasy</option><option>Other</option>
            </select>
          </div>
          <div class="f-group"><label>Total Quantity</label><input type="number" name="quantity" id="eb_qty" min="1"></div>
          <div class="f-group"><label>Available Copies</label><input type="number" name="available" id="eb_avail" min="0"></div>
          <div class="f-group"><label>Attach PDF (optional)</label><input type="file" name="file" accept=".pdf,.doc,.docx"></div>
          <div class="f-group"><label>Cover Image (optional)</label><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeModal('editBookModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal">
    <div class="modal-head">
      <h4>Add New User</h4>
      <button class="modal-close" onclick="closeModal('addUserModal')">✕</button>
    </div>
    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="add_user">
      <div class="modal-body">
        <div class="form-grid">
          <div class="f-group"><label>Username *</label><input type="text" name="username" required placeholder="e.g. cse_std_4601"></div>
          <div class="f-group"><label>Full Name *</label><input type="text" name="full_name" required placeholder="e.g. Tanvir Hasan"></div>
          <div class="f-group"><label>Email *</label><input type="email" name="email" required placeholder="user@puc.ac.bd"></div>
          <div class="f-group"><label>Password *</label><input type="password" name="password" required value="123456"></div>
          <div class="f-group"><label>Role *</label>
            <select name="role">
              <option value="student">Student</option>
              <option value="teacher">Teacher</option>
              <option value="librarian">Librarian</option>
              <option value="admin">Administrator</option>
            </select>
          </div>
          <div class="f-group"><label>Section</label><input type="text" name="section" placeholder="e.g. Section A"></div>
          <div class="f-group"><label>Batch</label><input type="text" name="batch" placeholder="e.g. Batch 46th"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal-overlay" id="editUserModal">
  <div class="modal">
    <div class="modal-head">
      <h4>Edit User Account</h4>
      <button class="modal-close" onclick="closeModal('editUserModal')">✕</button>
    </div>
    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="eu_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="f-group"><label>Username (Read-Only)</label><input type="text" id="eu_user" readonly style="background:#f3f4f6;"></div>
          <div class="f-group"><label>Full Name *</label><input type="text" name="full_name" id="eu_name" required></div>
          <div class="f-group"><label>Email *</label><input type="email" name="email" id="eu_email" required></div>
          <div class="f-group"><label>Role *</label>
            <select name="role" id="eu_role">
              <option value="student">Student</option>
              <option value="teacher">Teacher</option>
              <option value="librarian">Librarian</option>
              <option value="admin">Administrator</option>
            </select>
          </div>
          <div class="f-group"><label>Section</label><input type="text" name="section" id="eu_sec"></div>
          <div class="f-group"><label>Batch</label><input type="text" name="batch" id="eu_batch"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeModal('editUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT FILE MODAL -->
<div class="modal-overlay" id="editFileModal">
  <div class="modal">
    <div class="modal-head">
      <h4>Edit Material Details</h4>
      <button class="modal-close" onclick="closeModal('editFileModal')">✕</button>
    </div>
    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="edit_file">
      <input type="hidden" name="file_id" id="ef_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="f-group"><label>File Title *</label><input type="text" name="title" id="ef_title" required></div>
          <div class="f-group"><label>Category</label>
            <select name="category" id="ef_cat">
              <option>Course Notes</option><option>Lecture Slides</option><option>Assignment</option><option>Lab Manual</option><option>Book PDF</option><option>Other</option>
            </select>
          </div>
          <div class="f-group"><label>Course Code</label><input type="text" name="course" id="ef_course"></div>
          <div class="f-group"><label>Access Level</label>
            <select name="access" id="ef_access">
              <option value="all">All (Students &amp; Teachers)</option>
              <option value="teacher">Teachers Only</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeModal('editFileModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT NOTICE MODAL -->
<div class="modal-overlay" id="editNoticeModal">
  <div class="modal">
    <div class="modal-head">
      <h4>Edit Notice</h4>
      <button class="modal-close" onclick="closeModal('editNoticeModal')">✕</button>
    </div>
    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="edit_notice">
      <input type="hidden" name="notice_id" id="en_id">
      <div class="modal-body">
        <div class="f-group">
          <label>Title *</label>
          <input type="text" name="title" id="en_title" required>
        </div>
        <div class="f-group mt-14">
          <label>Message Content *</label>
          <textarea name="message" id="en_msg" rows="4" required></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeModal('editNoticeModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Notice</button>
      </div>
    </form>
  </div>
</div>

<!-- REPLY FEEDBACK MODAL -->
<div class="modal-overlay" id="replyFeedbackModal">
  <div class="modal">
    <div class="modal-head">
      <h4>Reply to Feedback</h4>
      <button class="modal-close" onclick="closeModal('replyFeedbackModal')">✕</button>
    </div>
    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="reply_feedback">
      <input type="hidden" name="feedback_id" id="rf_id">
      <div class="modal-body">
        <div style="background:#f8f9fb;padding:12px;border-radius:6px;margin-bottom:14px;border:1px solid var(--border);">
          <div id="rf_meta" style="font-size:0.85rem;color:var(--navy);font-weight:600;"></div>
          <div id="rf_subj" style="font-size:0.82rem;color:var(--gold);margin-top:2px;"></div>
          <div id="rf_body" style="font-size:0.85rem;color:#4b5563;margin-top:6px;line-height:1.5;"></div>
        </div>
        <div class="f-group">
          <label>Admin Reply *</label>
          <textarea name="reply" id="rf_reply" rows="4" placeholder="Type your official reply or resolution details…" required></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeModal('replyFeedbackModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Send &amp; Resolve</button>
      </div>
    </form>
  </div>
</div>

<script src="theme.js"></script>
<script>
var sections = ['dashboard', 'books', 'authors', 'issue', 'files', 'users', 'members', 'fines', 'communication', 'notices', 'feedback', 'settings', 'activity'];
var titles = {
  dashboard: 'Dashboard',
  books: 'Book Management',
  authors: 'Authors',
  issue: 'Issue & Return System',
  files: 'File Manager',
  users: 'Registered Users',
  members: 'Library Members',
  fines: 'Fines & Penalties',
  communication: 'Communication Hub',
  notices: 'Notifications',
  feedback: 'Feedback & Inquiries',
  settings: 'Library Settings',
  activity: 'Activity Log'
};

function openEditAuthor(a) {
  document.getElementById('ea_id').value = a.Author_ID || '';
  document.getElementById('ea_fn').value = a.First_Name || '';
  document.getElementById('ea_ln').value = a.Last_Name || '';
  document.getElementById('ea_email').value = a.Email || '';
  document.getElementById('ea_country').value = a.Country || '';
  document.getElementById('editAuthorModal').classList.add('open');
}

function showSection(name) {
  sections.forEach(function(s) {
    var el = document.getElementById('sec-' + s);
    if (el) el.classList.toggle('d-none', s !== name);
  });
  document.querySelectorAll('.nav-item').forEach(function(el) {
    el.classList.toggle('active', el.getAttribute('onclick') && el.getAttribute('onclick').includes("'" + name + "'"));
  });
  document.getElementById('pageTitle').textContent = titles[name] || name;
}

function switchIssueTab(tabId, btn) {
  ['issue-tab', 'return-tab', 'history-tab'].forEach(function(t) {
    var el = document.getElementById(t);
    if (el) el.classList.toggle('d-none', t !== tabId);
  });
  document.querySelectorAll('#sec-issue .sec-tab').forEach(function(b) {
    b.classList.remove('active');
  });
  btn.classList.add('active');
}

function openAddBookModal() {
  document.getElementById('addBookModal').classList.add('open');
}

function openEditBook(book) {
  document.getElementById('eb_id').value = book.Book_ID;
  document.getElementById('eb_title').value = book.Title;
  document.getElementById('eb_author').value = book.Author_Name || '';
  document.getElementById('eb_isbn').value = book.ISBN || '';
  document.getElementById('eb_cat').value = book.Category_Name || 'CSE';
  document.getElementById('eb_qty').value = book.Quantity;
  document.getElementById('eb_avail').value = book.Available_Copies;
  document.getElementById('editBookModal').classList.add('open');
}

function openAddUserModal() {
  document.getElementById('addUserModal').classList.add('open');
}

function openEditUser(user) {
  document.getElementById('eu_id').value = user.User_ID;
  document.getElementById('eu_user').value = user.Username;
  document.getElementById('eu_name').value = user.Full_Name || '';
  document.getElementById('eu_email').value = user.Email || '';
  document.getElementById('eu_role').value = user.Role || 'student';
  document.getElementById('eu_sec').value = user.Section || '';
  document.getElementById('eu_batch').value = user.Batch || '';
  document.getElementById('editUserModal').classList.add('open');
}

function openEditFile(file) {
  document.getElementById('ef_id').value = file.File_ID;
  document.getElementById('ef_title').value = file.Title || '';
  document.getElementById('ef_cat').value = file.Folder_Category || 'Course Notes';
  document.getElementById('ef_course').value = file.Course || '';
  document.getElementById('ef_access').value = file.Access_Level || 'all';
  document.getElementById('editFileModal').classList.add('open');
}

function openEditNotice(notice) {
  document.getElementById('en_id').value = notice.Notification_ID;
  document.getElementById('en_title').value = notice.Title || '';
  document.getElementById('en_msg').value = notice.Message || '';
  document.getElementById('editNoticeModal').classList.add('open');
}

function openReplyFeedback(fb) {
  document.getElementById('rf_id').value = fb.Feedback_ID;
  document.getElementById('rf_meta').textContent = fb.Member_Name + ' (' + fb.Email + ')';
  document.getElementById('rf_subj').textContent = 'Subject: ' + (fb.Subject || 'Inquiry');
  document.getElementById('rf_body').textContent = fb.Message || '';
  document.getElementById('rf_reply').value = fb.Reply || '';
  document.getElementById('replyFeedbackModal').classList.add('open');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

function filterBooks() {
  var search = document.getElementById('bookSearch').value.toLowerCase();
  var cat = document.getElementById('bookCatFilter').value.toLowerCase();
  var status = document.getElementById('bookStatusFilter').value;
  var rows = document.querySelectorAll('#booksBody tr');

  rows.forEach(function(r) {
    var title = r.getAttribute('data-title') || '';
    var author = r.getAttribute('data-author') || '';
    var isbn = r.getAttribute('data-isbn') || '';
    var category = (r.getAttribute('data-category') || '').toLowerCase();
    var avail = parseInt(r.getAttribute('data-avail') || '0');

    var matchSearch = !search || title.includes(search) || author.includes(search) || isbn.includes(search);
    var matchCat = !cat || category === cat;
    var matchStatus = !status || (status === 'available' ? avail > 0 : avail === 0);

    r.style.display = (matchSearch && matchCat && matchStatus) ? '' : 'none';
  });
}

function filterReturns() {
  var q = document.getElementById('returnSearch').value.toLowerCase();
  var rows = document.querySelectorAll('#returnBody tr');
  rows.forEach(function(r) {
    var s = r.getAttribute('data-search') || '';
    r.style.display = (!q || s.includes(q)) ? '' : 'none';
  });
}
</script>
</body>
</html>
