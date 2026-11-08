<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth();
$db = getDB();
$folder = trim($_GET['folder']??'');

if (is_staff() && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='upload') {
  require_csrf();
  $title = trim($_POST['title']??'');
  $cat = trim($_POST['folder']??'Course Notes');
  $course = trim($_POST['course']??'');
  if ($title && !empty($_FILES['file']['name'])) {
    $dir = __DIR__.'/uploads/'; if (!is_dir($dir)) mkdir($dir,0777,true);
    $name = preg_replace('/[^a-zA-Z0-9._-]/','_', basename($_FILES['file']['name']));
    $dest = $dir.uniqid('f_').'_'.$name;
    if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
      $db->prepare("INSERT INTO digital_files (Title,Folder_Category,Course,File_Path,File_Name,File_Type,File_Size,Uploaded_By,Access_Level) VALUES (?,?,?,?,?,?,?,?,'all')")
         ->execute([$title,$cat,$course,'uploads/'.basename($dest),$name,strtoupper(pathinfo($name,PATHINFO_EXTENSION)),(int)$_FILES['file']['size'],$user['name']]);
      set_flash('success','File uploaded.');
    }
  }
  header('Location: digital_resources.php'); exit;
}
if (is_staff() && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
  require_csrf();
  $fid=(int)($_POST['file_id']??0);
  $st=$db->prepare("SELECT File_Path FROM digital_files WHERE File_ID=?"); $st->execute([$fid]); $fp=$st->fetchColumn();
  if ($fp && file_exists(__DIR__.'/'.$fp)) @unlink(__DIR__.'/'.$fp);
  $db->prepare("DELETE FROM digital_files WHERE File_ID=?")->execute([$fid]);
  set_flash('success','File deleted.');
  header('Location: digital_resources.php'); exit;
}

$sql = "SELECT * FROM digital_files WHERE 1=1";
$params=[];
if ($folder!=='') { $sql.=" AND Folder_Category=?"; $params[]=$folder; }
if (!is_staff()) $sql .= " AND (Access_Level='all' OR Access_Level IS NULL)";
$sql .= " ORDER BY File_ID DESC";
$st=$db->prepare($sql); $st->execute($params); $files=$st->fetchAll();
$folders=[];
try { $folders=$db->query("SELECT DISTINCT Folder_Category FROM digital_files ORDER BY Folder_Category")->fetchAll(PDO::FETCH_COLUMN); } catch(Throwable $e){}
portal_header('Digital Resources', $user, 'digital');
?>
<?php if (is_staff()): ?>
<div class="panel">
  <div class="panel-head"><h4>Upload Material</h4></div>
  <div class="panel-body">
    <form method="POST" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="upload">
      <div class="form-grid">
        <div class="f-group"><label>Title *</label><input name="title" required></div>
        <div class="f-group"><label>Folder</label><select name="folder"><option>Course Notes</option><option>Lecture Slides</option><option>Lab Manual</option><option>Assignment</option><option>Book PDF</option></select></div>
        <div class="f-group"><label>Course</label><input name="course" placeholder="CSE 101"></div>
        <div class="f-group"><label>File *</label><input type="file" name="file" required></div>
      </div>
      <button class="btn btn-primary mt-16" type="submit">Upload</button>
    </form>
  </div>
</div>
<?php endif; ?>
<div class="panel">
  <div class="panel-head"><h4>Repository</h4>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a class="btn btn-sm <?= $folder===''?'btn-primary':'' ?>" href="digital_resources.php">All</a>
      <?php foreach ($folders as $f): ?>
        <a class="btn btn-sm <?= $folder===$f?'btn-gold':'' ?>" href="digital_resources.php?folder=<?= urlencode($f) ?>"><?= esc($f) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel-body p-0">
    <table>
      <thead><tr><th>Title</th><th>Type</th><th>Course</th><th>By</th><th>Download</th><?php if(is_staff()):?><th>Del</th><?php endif;?></tr></thead>
      <tbody>
      <?php if (!$files): ?><tr><td colspan="6"><div class="empty-state"><p>No files.</p></div></td></tr>
      <?php else: foreach ($files as $f): ?>
        <tr>
          <td><strong><?= esc($f['Title']) ?></strong></td>
          <td><span class="badge badge-blue"><?= esc($f['Folder_Category']??'') ?></span></td>
          <td><?= esc($f['Course']??'—') ?></td>
          <td><?= esc($f['Uploaded_By']??'') ?></td>
          <td><?php if (!empty($f['File_Path'])): ?><a class="btn btn-sm" href="<?= esc($f['File_Path']) ?>" download>⬇ Download</a><?php else: ?>—<?php endif; ?></td>
          <?php if (is_staff()): ?>
          <td><form method="POST" onsubmit="return confirm('Delete?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="file_id" value="<?= (int)$f['File_ID'] ?>"><button class="btn btn-sm btn-danger" type="submit">🗑</button></form></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php portal_footer(); ?>
