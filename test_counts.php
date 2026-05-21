<?php
require 'db.php';
$stmt = $pdo->query('SELECT COUNT(*) FROM printers');
echo "Printers: " . $stmt->fetchColumn() . "\n";
$stmt = $pdo->query('SELECT COUNT(*) FROM ink_stock');
echo "Inks: " . $stmt->fetchColumn() . "\n";
$stmt = $pdo->query('SELECT COUNT(*) FROM maintenance_logs');
echo "Maint: " . $stmt->fetchColumn() . "\n";
?>
