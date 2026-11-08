<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth(['admin', 'librarian']);
$db = getDB();
$cats = $db->query("SELECT Category_ID, Category_Name FROM category ORDER BY Category_Name")->fetchAll();
$authors = $db->query("SELECT Author_ID, First_Name, Last_Name FROM author ORDER BY Last_Name, First_Name")->fetchAll();
$errors = [];
$form = [
    'title' => '',
    'isbn' => '',
    'edition' => '1st',
    'year' => (string)date('Y'),
    'quantity' => '1',
    'category_id' => '',
    'author_id' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator();
    foreach (['title', 'isbn', 'edition'] as $field) {
        if (array_key_exists($field, $_POST) && !is_string($_POST[$field])) {
            $validator->fail(ucfirst($field) . ' must be text.');
        } else {
            $form[$field] = trim($_POST[$field] ?? ($form[$field] ?? ''));
        }
    }

    foreach (['year', 'quantity', 'category_id', 'author_id'] as $field) {
        if (array_key_exists($field, $_POST) && !is_string($_POST[$field])) {
            $validator->fail(ucfirst(str_replace('_', ' ', $field)) . ' must be a whole number.');
            $form[$field] = '';
        } elseif (array_key_exists($field, $_POST)) {
            $form[$field] = trim($_POST[$field]);
        }
    }

    $validator->required($form['title'], 'Title')
        ->length($form['title'], 'Title', 1, 200)
        ->required($form['isbn'], 'ISBN')
        ->length($form['isbn'], 'ISBN', 1, 20)
        ->isbn($form['isbn'], false)
        ->length($form['edition'], 'Edition', 0, 20)
        ->intRange($form['year'], 'Year', 1000, (int)date('Y') + 1)
        ->intRange($form['quantity'], 'Quantity', 1, 500);

    $categoryId = null;
    if ($form['category_id'] !== '') {
        $validator->intRange($form['category_id'], 'Category', 1, 2147483647);
        $categoryId = filter_var($form['category_id'], FILTER_VALIDATE_INT);
        if ($categoryId !== false && !in_array($categoryId, array_column($cats, 'Category_ID'), false)) {
            $validator->fail('Selected category does not exist.');
        }
    }

    $authorId = null;
    if ($form['author_id'] !== '') {
        $validator->intRange($form['author_id'], 'Author', 1, 2147483647);
        $authorId = filter_var($form['author_id'], FILTER_VALIDATE_INT);
        if ($authorId !== false && !in_array($authorId, array_column($authors, 'Author_ID'), false)) {
            $validator->fail('Selected author does not exist.');
        }
    }

    $errors = $validator->errors();
    if (!$errors) {
        try {
            $db->beginTransaction();
            $db->prepare(
                "INSERT INTO book (Title, ISBN, Edition, Publication_Year, Category_ID, Quantity, Available_Copies)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $form['title'],
                $form['isbn'],
                $form['edition'],
                (int)$form['year'],
                $categoryId,
                (int)$form['quantity'],
                (int)$form['quantity'],
            ]);

            $bookId = (int)$db->lastInsertId();
            if ($authorId !== null && $authorId !== false) {
                $db->prepare("INSERT INTO writes (Author_ID, Book_ID) VALUES (?, ?)")->execute([$authorId, $bookId]);
            }
            $db->commit();

            logActivity($user['id'], 'Books', "Added {$form['title']}");
            set_flash('success', "Book '{$form['title']}' added.");
            header('Location: books.php');
            exit;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e->getCode() === '23000' || $e->getCode() === '19') {
                $errors[] = 'ISBN already exists or a selected record is no longer available.';
            } else {
                error_log('Book creation failed: ' . $e->getMessage());
                $errors[] = 'Book could not be saved. Please try again.';
            }
        }
    }
}

portal_header('Add Book', $user, 'books');
?>
<div class="panel" style="max-width:600px">
  <div class="panel-head"><h4>Add New Book</h4><a href="books.php" class="btn btn-sm">Back</a></div>
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
        <div class="f-group"><label>Category</label>
          <select name="category_id">
            <option value="">—</option>
            <?php foreach ($cats as $category): ?>
              <option value="<?= (int)$category['Category_ID'] ?>" <?= $form['category_id'] === (string)$category['Category_ID'] ? 'selected' : '' ?>><?= esc($category['Category_Name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="f-group"><label>Author</label>
          <select name="author_id">
            <option value="">—</option>
            <?php foreach ($authors as $author): ?>
              <option value="<?= (int)$author['Author_ID'] ?>" <?= $form['author_id'] === (string)$author['Author_ID'] ? 'selected' : '' ?>>
                <?= esc(trim($author['First_Name'] . ' ' . $author['Last_Name'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button class="btn btn-primary mt-16" type="submit">Save Book</button>
    </form>
  </div>
</div>
<?php portal_footer(); ?>
