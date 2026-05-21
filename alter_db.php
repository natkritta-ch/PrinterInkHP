<?php
require 'db.php';
try {
    $pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN status_after_repair VARCHAR(20) DEFAULT NULL");
    echo "Column added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
