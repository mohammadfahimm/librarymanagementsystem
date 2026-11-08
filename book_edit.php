<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    set_flash('danger', 'Not found');
    header('Location: books.php');
    exit;
}

$statement = $db->prepare("SELECT * FROM book WHERE Book_ID = ?");
$statement->execute([$id]);
$book = $statement->fetch();
if (!$book) {
    set_flash('danger', 'Not found');
    header('Location: books.php');
    exit;
}

$cats = $db->query("SELECT Category_ID, Category_Name FROM category ORDER BY Category_Name")->fetchAll();
$errors = [];
$form = [
    'title' => (string)$book['Title'],
    'isbn' => (string)$book['ISBN'],
    'edition' => (string)($book['Edition'] ?? ''),
    'year' => (string)($book['Publication_Year'] ?? date('Y')),
    'quantity' => (string)($book['Quantity'] ?? 1),
    'available' => (string)($book['Available_Copies'] ?? 0),
    'category_id' => (string)($book['Category_ID'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator();
    foreach (['title', 'isbn', 'edition'] as $field) {
        if (array_key_exists($field, $_POST) && !is_string($_POST[$field])) {
            $validator->fail(ucfirst($field) . ' must be text.');
        } else {
            $form[$field] = trim($_POST[$field] ?? '');
        }
    }

    foreach (['year', 'quantity', 'available', 'category_id'] as $field) {
        if (array_key_exists($field, $_POST) && !is_string($_POST[$field])) {
            $validator->fail(ucfirst(str_replace('_', ' ', $field)) . ' must be a whole number.');
            $form[$field] = '';
        } elseif (array_key_exists($field, $_POST)) {
            $form[$field] = trim($_POST[$field]);
        } else {
            $form[$field] = '';
        }
    }

    $validator->required($form['title'], 'Title')
        ->length($form['title'], 'Title', 1, 200)
        ->required($form['isbn'], 'ISBN')
        ->length($form['isbn'], 'ISBN', 1, 20)
        ->isbn($form['isbn'], false)
        ->length($form['edition'], 'Edition', 0, 20)
        ->intRange($form['year'], 'Year', 1000, (int)date('Y') + 1)
        ->intRange($form['quantity'], 'Quantity', 1, 500)
        ->intRange($form['available'], 'Available copies', 0, 500);

    if ($form['available'] !== '' && $form['quantity'] !== '') {
        $available = filter_var($form['available'], FILTER_VALIDATE_INT);
        $quantity = filter_var($form['quantity'], FILTER_VALIDATE_INT);
        if ($available !== false && $quantity !== false && $available > $quantity) {
            $validator->fail('Available copies cannot exceed total quantity.');
        }
    }

    $categoryId = null;
    if ($form['category_id'] !== '') {
        $validator->intRange($form['category_id'], 'Category', 1, 2147483647);
        $categoryId = filter_var($form['category_id'], FILTER_VALIDATE_INT);
        if ($categoryId !== false && !in_array($categoryId, array_column($cats, 'Category_ID'), false)) {
            $validator->fail('Selected category does not exist.');
        }
    }

    $errors = $validator->errors();
    if (!$errors) {
        try {
            $db->prepare(
                "UPDATE book
                 SET Title = ?, ISBN = ?, Edition = ?, Publication_Year = ?, Category_ID = ?, Quantity = ?, Available_Copies = ?
                 WHERE Book_ID = ?"
            )->execute([
                $form['title'],
                $form['isbn'],
                $form['edition'],
                (int)$form['year'],
                $categoryId,
                (int)$form['quantity'],
                (int)$form['available'],
                $id,
            ]);

            logActivity($user['id'], 'Books', "Updated {$form['title']}");
            set_flash('success', 'Book updated.');
            header('Location: books.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || $e->getCode() === '19') {
                $errors[] = 'ISBN already exists or a selected record is no longer available.';
            } else {
                error_log('Book update failed: ' . $e->getMessage());
                $errors[] = 'Book could not be updated. Please try again.';
            }
        }
    }
}

portal_header('Edit Book', $user, 'books');
?>
<div class="panel" style="max-width:600px">
  <div class="panel-head"><h4>Edit Book #<?= (int)$id ?></h4><a href="books.php" class="btn btn-sm">Back</a></div>
  <div class="panel-body">
    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error" style="display:block"><?= esc($error) ?></div>
    <?php endforeach; ?>
    <form method="POST">
      <div class="f-group"><label>Title *</label><input name="title" maxlength="200" value="<?= esc($form['title']) ?>" required></div>
      <div class="form-grid">
        <div class="f-group"><label>ISBN *</label><input name="isbn" maxlength="20" value="<?= esc($form['isbn']) ?>" required></div>
        <div class="f-group"><label>Edition</label><input name="edition" maxlength="20" value="<?= esc($form['edition']) ?>"></div>
        <div class="f-group"><label>Year</label><input type="number" name="year" min="1000" max="<?= (int)date('Y') + 1 ?>" value="<?= esc($form['year']) ?>" required></div>
        <div class="f-group"><label>Quantity</label><input type="number" name="quantity" min="1" max="500" value="<?= esc($form['quantity']) ?>" required></div>
        <div class="f-group"><label>Available</label><input type="number" name="available" min="0" max="500" value="<?= esc($form['available']) ?>" required></div>
        <div class="f-group"><label>Category</label>
          <select name="category_id">
            <option value="">—</option>
            <?php foreach ($cats as $category): ?>
              <option value="<?= (int)$category['Category_ID'] ?>" <?= $form['category_id'] === (string)$category['Category_ID'] ? 'selected' : '' ?>><?= esc($category['Category_Name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button class="btn btn-primary mt-16" type="submit">Update</button>
    </form>
  </div>
</div>
<?php portal_footer(); ?>
