<?php
// cleanup_orphans.php — ลบ DB records ที่ไม่มีไฟล์จริงบน disk
require_once 'db.php';

$stmt = $pdo->query("SELECT id, printer_id, filename FROM printer_images ORDER BY id");
$rows = $stmt->fetchAll();

$deleted = 0; $ok = 0;
echo "<h2>🧹 ตรวจสอบรูปภาพใน Database</h2><pre>";

foreach ($rows as $row) {
    $path = __DIR__ . '/assets/img/printers/' . $row['filename'];
    if (!file_exists($path)) {
        echo "❌ ไฟล์ไม่มีบน disk: {$row['filename']} (id={$row['id']}, printer_id={$row['printer_id']})\n";
        $pdo->prepare("DELETE FROM printer_images WHERE id = ?")->execute([$row['id']]);
        $deleted++;
    } else {
        echo "✅ OK: {$row['filename']}\n";
        $ok++;
    }
}

echo "\n=== สรุป ===\n";
echo "ปกติ: $ok รูป\n";
echo "ลบ record เสีย: $deleted รูป\n";
echo "</pre>";
echo "<a href='#' onclick='history.back()' style='padding:10px 24px;background:#4f46e5;color:white;text-decoration:none;border-radius:8px;display:inline-block;margin-top:12px'>กลับ</a>";
?>
