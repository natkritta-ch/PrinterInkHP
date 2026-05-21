<?php
require_once 'db.php';

echo "<h2>Printer Fix Tool</h2>";

try {
    // 1. ดูโครงสร้างตาราง
    $cols = $pdo->query("DESCRIBE printers")->fetchAll(PDO::FETCH_COLUMN, 0);
    echo "<b>Columns:</b> " . implode(', ', $cols) . "<br><br>";
    
    // 2. นับข้อมูลทั้งหมด
    $total = $pdo->query("SELECT COUNT(*) FROM printers")->fetchColumn();
    echo "<b>Total rows in DB:</b> $total<br>";
    
    // 3. นับที่ deleted_at IS NULL
    $activeNull = $pdo->query("SELECT COUNT(*) FROM printers WHERE deleted_at IS NULL")->fetchColumn();
    echo "<b>deleted_at IS NULL:</b> $activeNull<br>";
    
    // 4. นับที่ deleted_at ไม่ใช่ NULL
    $deleted = $pdo->query("SELECT COUNT(*) FROM printers WHERE deleted_at IS NOT NULL")->fetchColumn();
    echo "<b>deleted_at IS NOT NULL:</b> $deleted<br><br>";
    
    if ($deleted > 0) {
        // แสดงตัวอย่าง
        $samples = $pdo->query("SELECT id, brand, model, deleted_at FROM printers WHERE deleted_at IS NOT NULL LIMIT 5")->fetchAll();
        echo "<b>Sample deleted rows:</b><br>";
        foreach ($samples as $s) {
            echo "ID:{$s['id']} - {$s['brand']} {$s['model']} - deleted_at: '{$s['deleted_at']}'<br>";
        }
        echo "<br>";
    }
    
    // 5. รีเซ็ต deleted_at ทั้งหมด (กู้คืนทุกเครื่อง)
    $fixed = $pdo->exec("UPDATE printers SET deleted_at = NULL WHERE deleted_at IS NOT NULL OR deleted_at = '0000-00-00 00:00:00'");
    echo "<b style='color:green'>Fixed $fixed rows — reset deleted_at to NULL</b><br><br>";
    
    // 6. ยืนยัน
    $nowActive = $pdo->query("SELECT COUNT(*) FROM printers WHERE deleted_at IS NULL")->fetchColumn();
    echo "<b>Active printers now:</b> $nowActive<br><br>";
    
    echo "<a href='index.php' style='background:#6c63ff;color:white;padding:10px 20px;border-radius:8px;text-decoration:none'>กลับหน้าหลัก</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
