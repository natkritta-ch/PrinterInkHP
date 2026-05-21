<?php
require_once 'db.php';
try {
    echo "<h1>Database Debug</h1>";
    
    // Check column info
    $stmt = $pdo->query("DESCRIBE printers");
    echo "<h3>Table Structure: printers</h3><pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";
    
    // Check data
    $stmt = $pdo->query("SELECT id, brand, model, deleted_at FROM printers LIMIT 10");
    echo "<h3>Sample Data (printers)</h3><pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
