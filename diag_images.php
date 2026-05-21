<?php
require_once 'db.php';
header('Content-Type: text/html; charset=utf-8');

$rows = $pdo->query("SELECT pi.*, p.brand, p.model FROM printer_images pi LEFT JOIN printers p ON p.id = pi.printer_id ORDER BY pi.printer_id, pi.sort_order")->fetchAll();
$uploadDir = __DIR__ . '/assets/img/printers/';

echo "<style>body{font-family:sans-serif;padding:20px} table{border-collapse:collapse;width:100%} td,th{border:1px solid #ddd;padding:8px;text-align:left} tr:nth-child(even){background:#f5f5f5} .ok{color:green} .err{color:red}</style>";
echo "<h2>🔍 ตรวจสอบ printer_images ทั้งหมด</h2>";
echo "<table><tr><th>id</th><th>printer_id</th><th>ปริ้นเตอร์</th><th>filename</th><th>sort_order</th><th>ไฟล์บน disk</th><th>URL</th></tr>";
foreach ($rows as $r) {
    $path = $uploadDir . $r['filename'];
    $exists = file_exists($path);
    $url = 'assets/img/printers/' . $r['filename'];
    echo "<tr>
        <td>{$r['id']}</td>
        <td>{$r['printer_id']}</td>
        <td>{$r['brand']} {$r['model']}</td>
        <td>{$r['filename']}</td>
        <td>{$r['sort_order']}</td>
        <td class='" . ($exists ? 'ok' : 'err') . "'>" . ($exists ? '✅ มี' : '❌ ไม่มี') . "</td>
        <td>" . ($exists ? "<img src='$url' style='height:50px'>" : '—') . "</td>
    </tr>";
}
echo "</table>";

echo "<br><h3>ทดสอบ API get_printer_images</h3>";
$pids = $pdo->query("SELECT DISTINCT printer_id FROM printer_images")->fetchAll(PDO::FETCH_COLUMN);
foreach ($pids as $pid) {
    echo "<p><a href='api.php?action=get_printer_images&printer_id=$pid' target='_blank'>api.php?printer_id=$pid</a></p>";
}
?>
