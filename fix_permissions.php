<?php
$dirs = [
    __DIR__ . '/assets',
    __DIR__ . '/assets/img',
    __DIR__ . '/assets/img/printers',
];
echo "<style>body{font-family:sans-serif;padding:24px;} code{background:#111;color:#0f0;padding:8px 16px;border-radius:8px;display:block;margin:8px 0;font-size:1rem;}</style>";
echo "<h2>🔧 ตรวจสอบและแก้ไข Permission</h2><pre>";
foreach ($dirs as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0775, true) && print("✅ สร้าง: $dir\n");
    $ok = @chmod($dir, 0775);
    echo ($ok ? "✅" : "❌") . " chmod 0775: $dir (writable=" . (is_writable($dir)?'YES':'NO') . ")\n";
}
$target = __DIR__ . '/assets/img/printers';
$writable = is_writable($target);
echo "\n=== สรุป ===\n";
echo "Upload folder writable: " . ($writable ? "YES ✅ — พร้อมอัปโหลด!" : "NO ❌ — ต้องรันคำสั่งด้านล่าง") . "\n";
echo "PHP upload_max_filesize: " . ini_get('upload_max_filesize') . " (ควรเป็น 20M)\n";
echo "PHP post_max_size: " . ini_get('post_max_size') . "\n";
echo "Web server user: " . (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : get_current_user()) . "\n";
echo "</pre>";

if (!$writable) {
    echo "<hr><h3>⚠️ รันคำสั่งนี้บน SSH Server:</h3>";
    echo "<code>sudo chown -R www-data:www-data " . dirname(__DIR__) . "/PrinterInkHP/assets/<br>sudo chmod -R 775 " . dirname(__DIR__) . "/PrinterInkHP/assets/</code>";
    echo "<p>หรือถ้าไม่รู้ path รัน:</p>";
    echo "<code>sudo chown -R www-data:www-data " . __DIR__ . "/assets/<br>sudo chmod -R 775 " . __DIR__ . "/assets/</code>";
    echo "<br><a href='fix_permissions.php' style='padding:10px 24px;background:#4f46e5;color:white;text-decoration:none;border-radius:8px;'>🔄 รีเฟรชตรวจสอบใหม่</a>";
}
if (ini_get('upload_max_filesize') === '2M') {
    echo "<hr><h3>⚠️ .htaccess ยังไม่ทำงาน</h3>";
    echo "<p>Apache2 ต้องเปิด AllowOverride All ใน VirtualHost config:</p>";
    echo "<code>sudo nano /etc/apache2/sites-available/000-default.conf</code>";
    echo "<p>เพิ่มใน &lt;VirtualHost&gt;:</p>";
    echo "<code>&lt;Directory /var/www/html&gt;<br>&nbsp;&nbsp;AllowOverride All<br>&lt;/Directory&gt;</code>";
    echo "<code>sudo systemctl reload apache2</code>";
}
?>
