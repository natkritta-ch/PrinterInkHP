<?php
// test_upload.php — ทดสอบ upload โดยตรง
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><title>Test Upload</title></head>
<body>
<h2>ทดสอบ Upload รูปภาพ</h2>
<form method="POST" action="api.php?action=upload_printer_image" enctype="multipart/form-data">
    <input type="hidden" name="printer_id" value="1">
    <input type="file" name="image" accept="image/*"><br><br>
    <button type="submit">Upload ทดสอบ</button>
</form>
<hr>
<h3>PHP Info:</h3>
<pre><?php
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "GD Extension: " . (function_exists('imagecreatefromjpeg') ? 'YES ✅' : 'NO ❌') . "\n";
echo "Upload dir writable: " . (is_writable(__DIR__ . '/assets/img/printers/') ? 'YES ✅' : 'NO ❌ — ' . __DIR__ . '/assets/img/printers/') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
?></pre>
</body>
</html>
