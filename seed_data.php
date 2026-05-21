<?php
require_once 'db.php';

$departments = ["OPD กุมารเวชกรรม", "กลุ่มงานการแพทย์", "ตึกหญิง", "ตึกชาย"];
$brands = ["HP", "Canon", "Brother", "Epson", "Samsung"];
$models = ["LaserJet Pro M404n", "Pixma G3010", "HL-L2370DW", "L3150", "Xpress M2020"];
$statuses = ["normal", "normal", "normal", "repairing", "broken"];

try {
    foreach ($departments as $dept) {
        for ($i = 1; $i <= 5; $i++) {
            $brand = $brands[array_rand($brands)];
            $model = $models[array_rand($models)];
            $serial = strtoupper(substr(md5(uniqid()), 0, 10));
            $status = $statuses[array_rand($statuses)];
            $qr_id = 'PRN-' . strtoupper(substr(md5(time() . $serial . $i), 0, 8));

            $stmt = $pdo->prepare("INSERT INTO printers (brand, model, serial_number, department, qr_code_id, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$brand, $model, $serial, $dept, $qr_id, $status]);
        }
    }
    echo "Dummy data inserted successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
