<?php
// จับ output ทั้งหมดเพื่อป้องกัน PHP warning ปน JSON
ob_start();

// ขยาย upload limit ผ่าน ini_set เพื่อรองรับภาพจากมือถือ
@ini_set('upload_max_filesize', '30M');
@ini_set('post_max_size', '35M');
@ini_set('memory_limit', '512M');

// ปิด display_errors ใน api.php เพื่อไม่ให้ PHP warning/notice ปน JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'login') {
    ob_clean(); // ล้าง output ก่อนหน้า
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, username, full_name, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $remember = ($_POST['remember'] ?? '0') === '1';
        
        if ($remember) {
            // ตั้งค่า cookie session ให้หมดอายุใน 24 ชั่วโมง (86400 วินาที)
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), time() + 86400, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง']);
    }
    exit;
}

if ($action === 'logout') {
    ob_clean();
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

if (in_array($action, ['add_user', 'edit_user', 'delete_user'])) {
    ob_clean();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์จัดการผู้ใช้']);
        exit;
    }

    if ($action === 'add_user') {
        $username = $_POST['username'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
        $role = $_POST['role'] ?? 'user';
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, full_name, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $full_name, $password, $role]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้งานซ้ำหรือเกิดข้อผิดพลาด']);
        }
    } elseif ($action === 'edit_user') {
        $id = $_POST['user_id'] ?? 0;
        $username = $_POST['username'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $password = $_POST['password'] ?? '';
        try {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, password=?, role=? WHERE id=?");
                $stmt->execute([$username, $full_name, $hash, $role, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, role=? WHERE id=?");
                $stmt->execute([$username, $full_name, $role, $id]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้งานซ้ำหรือเกิดข้อผิดพลาด']);
        }
    } elseif ($action === 'delete_user') {
        $id = $_POST['user_id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบ']);
        }
    }
    exit;
}

// Check auth for write operations
$write_actions = [
    'add_printer', 'edit_printer', 'delete_printer', 'restore_printer', 'permanent_delete_printer',
    'add_maintenance_log', 'edit_maintenance_log', 'delete_maintenance_log', 'restore_maintenance_log', 'permanent_delete_maintenance_log',
    'add_ink', 'edit_ink', 'delete_ink', 'restore_ink', 'permanent_delete_ink', 'update_stock', 'withdraw_ink',
    'add_group', 'edit_group', 'delete_group', 'add_sub', 'edit_sub', 'delete_sub'
];
if (in_array($action, $write_actions) && !isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบเพื่อทำรายการนี้']);
    exit;
}

// Migration: เพิ่มคอลัมน์ deleted_at ถ้ายังไม่มี
try { 
    $pdo->exec("ALTER TABLE printers ADD COLUMN deleted_at DATETIME DEFAULT NULL");
} catch (Exception $e) {}

// Migration: เพิ่มคอลัมน์ model ในตาราง ink_stock ถ้ายังไม่มี
try {
    $pdo->exec("ALTER TABLE ink_stock ADD COLUMN model VARCHAR(100) DEFAULT NULL");
} catch (Exception $e) {}

// Migration: เพิ่มคอลัมน์ created_at ในตาราง ink_stock ถ้ายังไม่มี
try {
    $pdo->exec("ALTER TABLE ink_stock ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
} catch (Exception $e) {}

// Migration: สร้างตาราง printer_images
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS printer_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            printer_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (printer_id) REFERENCES printers(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {}

// Migration: เพิ่มคอลัมน์ year_be ถ้ายังไม่มี
try { $pdo->exec("ALTER TABLE printers ADD COLUMN year_be VARCHAR(10) DEFAULT NULL"); } catch (Exception $e) {}
// Migration: เพิ่มคอลัมน์ location ถ้ายังไม่มี
try { $pdo->exec("ALTER TABLE printers ADD COLUMN location VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
// Migration: ลบคอลัมน์ printer_number ถ้ายังมีอยู่
try { $pdo->exec("ALTER TABLE printers DROP COLUMN printer_number"); } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'export_printers_csv') {
        ob_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="printers_export_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        // Add BOM for Excel UTF-8
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, ['ID', 'QR Code', 'Serial Number', 'Brand', 'Model', 'Department', 'Location', 'Year', 'Status']);
        
        $stmt = $pdo->query("SELECT * FROM printers WHERE deleted_at IS NULL ORDER BY department ASC, brand ASC");
        while ($row = $stmt->fetch()) {
            $statusMap = ['normal' => 'ปกติ', 'repairing' => 'กำลังซ่อม', 'broken' => 'พัง', 'retired' => 'จำหน่ายออก'];
            $statusTh = $statusMap[$row['status']] ?? $row['status'];
            fputcsv($output, [
                $row['id'],
                $row['qr_code_id'],
                $row['serial_number'],
                $row['brand'],
                $row['model'],
                $row['department'],
                $row['location'],
                $row['year_be'],
                $statusTh
            ]);
        }
        fclose($output);
        exit;
    }

    if ($action === 'export_low_ink_csv') {
        ob_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ink_reorder_request_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        // Add BOM for Excel UTF-8
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, ['ID', 'Barcode', 'ประเภท', 'ยี่ห้อ', 'รุ่น', 'ชื่อหมึก', 'คงเหลือ', 'จุดสั่งซื้อ', 'สถานะ']);
        
        $stmt = $pdo->query("SELECT * FROM ink_stock WHERE deleted_at IS NULL AND current_quantity <= min_quantity ORDER BY type ASC, brand ASC");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['barcode'],
                $row['type'],
                $row['brand'],
                $row['model'],
                $row['name'],
                $row['current_quantity'],
                $row['min_quantity'],
                ($row['current_quantity'] == 0 ? 'หมดสต๊อก' : 'ใกล้หมด')
            ]);
        }
        fclose($output);
        exit;
    }

    if ($action === 'backup_db') {
        ob_clean();
        header('Content-Type: text/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="backup_' . date('Ymd_His') . '.sql"');
        
        $output = fopen('php://output', 'w');
        fputs($output, "-- Database Backup\n-- Generated at " . date('Y-m-d H:i:s') . "\n\n");
        
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            fputs($output, $createTable['Create Table'] . ";\n\n");
            
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $vals = array_map(function($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote($v);
                }, array_values($row));
                
                fputs($output, "INSERT INTO `$table` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n");
            }
            fputs($output, "\n\n");
        }
        fclose($output);
        exit;
    }

    if ($action === 'get_printer_details') {
        try {
            $qr_id = $_GET['qr_id'] ?? '';
            
            // ดึงข้อมูลปริ้นเตอร์
            $stmt = $pdo->prepare("SELECT * FROM printers WHERE qr_code_id = ? AND deleted_at IS NULL");
            $stmt->execute([$qr_id]);
            $printer = $stmt->fetch();

            if (!$printer) {
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลปริ้นเตอร์']);
                exit;
            }

            // ดึงประวัติการเบิกหมึก
            $stmt = $pdo->prepare("
                SELECT it.*, is.name as ink_name 
                FROM ink_transactions it 
                JOIN ink_stock `is` ON it.ink_id = `is`.id 
                WHERE it.printer_id = ? 
                ORDER BY it.transaction_date DESC
            ");
            $stmt->execute([$printer['id']]);
            $ink_history = $stmt->fetchAll();

            // ดึงประวัติการซ่อม
            $stmt = $pdo->prepare("SELECT * FROM maintenance_logs WHERE printer_id = ? ORDER BY repair_date DESC, repair_time DESC");
            $stmt->execute([$printer['id']]);
            $maintenance_history = $stmt->fetchAll();

            echo json_encode([
                'success' => true, 
                'printer' => $printer,
                'ink_history' => $ink_history,
                'maintenance_history' => $maintenance_history
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_ink_details') {
        try {
            $barcode = $_GET['barcode'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM ink_stock WHERE barcode = ?");
            $stmt->execute([$barcode]);
            $ink = $stmt->fetch();

            if ($ink) {
                echo json_encode(['success' => true, 'exists' => true, 'data' => $ink]);
            } else {
                echo json_encode(['success' => true, 'exists' => false]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_available_ink') {
        try {
            $stmt = $pdo->query("SELECT id, name, current_quantity, barcode FROM ink_stock WHERE current_quantity > 0 ORDER BY name ASC");
            $inks = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $inks]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_budget_analysis') {
        try {
            $year_be = (int)($_GET['year'] ?? (date('Y') + 543));
            $year_gregorian = $year_be - 543;
            
            $start_year = $year_gregorian - 1;
            $end_year = $year_gregorian;

            $quarters = [
                ['name' => 'Q1 (ต.ค.-ธ.ค.)', 'start' => "$start_year-10-01", 'end' => "$start_year-12-31"],
                ['name' => 'Q2 (ม.ค.-มี.ค.)', 'start' => "$end_year-01-01", 'end' => "$end_year-03-31"],
                ['name' => 'Q3 (เม.ย.-มิ.ย.)', 'start' => "$end_year-04-01", 'end' => "$end_year-06-30"],
                ['name' => 'Q4 (ก.ค.-ก.ย.)', 'start' => "$end_year-07-01", 'end' => "$end_year-09-30"],
            ];

            $analysis_data = [];

            foreach ($quarters as $q) {
                $stmt = $pdo->prepare("
                    SELECT SUM(it.quantity) as total_used 
                    FROM ink_transactions it
                    JOIN printers p ON it.printer_id = p.id
                    WHERE it.type = 'out' 
                    AND p.deleted_at IS NULL
                    AND it.transaction_date BETWEEN ? AND ?
                ");
                $stmt->execute([$q['start'] . ' 00:00:00', $q['end'] . ' 23:59:59']);
                $total = $stmt->fetchColumn() ?: 0;
                
                $analysis_data[] = [
                    'label' => $q['name'],
                    'value' => (int)$total
                ];
            }

            // ดึงข้อมูลการเบิกแยกตามประเภทหมึก เพื่อดูว่าอะไรถูกเบิกเยอะสุด
            $stmt = $pdo->prepare("
                SELECT `is`.id, `is`.name, SUM(it.quantity) as used 
                FROM ink_transactions it 
                JOIN ink_stock `is` ON it.ink_id = `is`.id 
                JOIN printers p ON it.printer_id = p.id
                WHERE it.type = 'out' 
                AND p.deleted_at IS NULL
                AND it.transaction_date BETWEEN ? AND ? 
                GROUP BY it.ink_id 
                ORDER BY used DESC 
                LIMIT 5
            ");
            $stmt->execute(["$start_year-10-01 00:00:00", "$end_year-09-30 23:59:59"]);
            $top_items = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'fiscal_year' => $year_be,
                'quarterly' => $analysis_data,
                'top_items' => $top_items
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_monthly_analysis') {
        try {
            $year_be = (int)($_GET['year'] ?? (date('Y') + 543));
            $year = $year_be - 543;
            $monthly_data = [];
            
            $months_th = [
                1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 
                5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 
                9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
            ];

            for ($m = 1; $m <= 12; $m++) {
                $start = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01 00:00:00";
                $end = date("Y-m-t 23:59:59", strtotime($start));
                
                $stmt = $pdo->prepare("
                    SELECT SUM(it.quantity)
                    FROM ink_transactions it
                    JOIN printers p ON it.printer_id = p.id
                    WHERE it.type = 'out'
                    AND p.deleted_at IS NULL
                    AND it.transaction_date BETWEEN ? AND ?
                ");
                $stmt->execute([$start, $end]);
                $total = $stmt->fetchColumn() ?: 0;
                
                $monthly_data[] = [
                    'label' => $months_th[$m],
                    'value' => (int)$total
                ];
            }

            echo json_encode(['success' => true, 'data' => $monthly_data]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_monthly_ink_usage') {
        try {
            $year_be = (int)($_GET['year'] ?? (date('Y') + 543));
            $year = $year_be - 543;
            $month = $_GET['month'] ?? date('m');
            $start = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01 00:00:00";
            $end = date("Y-m-t 23:59:59", strtotime($start));

            $stmt = $pdo->prepare("
                SELECT `is`.id, `is`.name, SUM(it.quantity) as total_used
                FROM ink_transactions it
                JOIN ink_stock `is` ON it.ink_id = `is`.id
                JOIN printers p ON it.printer_id = p.id
                WHERE it.type = 'out'
                AND p.deleted_at IS NULL
                AND it.transaction_date BETWEEN ? AND ?
                GROUP BY it.ink_id
                ORDER BY total_used DESC
            ");
            $stmt->execute([$start, $end]);
            $inks = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $inks]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_ink_usage_details') {
        try {
            $ink_id = $_GET['ink_id'] ?? 0;
            $year_be = (int)($_GET['year'] ?? (date('Y') + 543));
            $year = $year_be - 543;
            $start_date = ($year - 1) . "-10-01 00:00:00";
            $end_date = "$year-09-30 23:59:59";

            $stmt = $pdo->prepare("
                SELECT p.department, SUM(it.quantity) as total_used, MAX(it.transaction_date) as last_withdrawn
                FROM ink_transactions it
                JOIN printers p ON it.printer_id = p.id
                WHERE it.ink_id = ? AND it.type = 'out'
                AND p.deleted_at IS NULL
                AND it.transaction_date BETWEEN ? AND ?
                GROUP BY p.department
                ORDER BY total_used DESC
            ");
            $stmt->execute([$ink_id, $start_date, $end_date]);
            $details = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $details]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_ink_history') {
        try {
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            
            $start = $start . ' 00:00:00';
            $end = $end . ' 23:59:59';

            // Query for Table
            $stmt = $pdo->prepare("
                SELECT it.id, it.transaction_date, p.department, `is`.name as ink_name, `is`.brand as ink_brand, p.model as printer_model, it.quantity
                FROM ink_transactions it
                JOIN printers p ON it.printer_id = p.id
                JOIN ink_stock `is` ON it.ink_id = `is`.id
                WHERE it.type = 'out'
                AND p.deleted_at IS NULL
                AND it.transaction_date BETWEEN ? AND ?
                ORDER BY it.transaction_date DESC
            ");
            $stmt->execute([$start, $end]);
            $history = $stmt->fetchAll();

            // Total quantity
            $total_quantity = array_sum(array_column($history, 'quantity'));

            // Top Department
            $stmt = $pdo->prepare("
                SELECT p.department, SUM(it.quantity) as total
                FROM ink_transactions it
                JOIN printers p ON it.printer_id = p.id
                WHERE it.type = 'out'
                AND p.deleted_at IS NULL
                AND it.transaction_date BETWEEN ? AND ?
                GROUP BY p.department
                ORDER BY total DESC
            ");
            $stmt->execute([$start, $end]);
            $top_departments = $stmt->fetchAll();

            // Top Ink
            $stmt = $pdo->prepare("
                SELECT `is`.name, SUM(it.quantity) as total
                FROM ink_transactions it
                JOIN printers p ON it.printer_id = p.id
                JOIN ink_stock `is` ON it.ink_id = `is`.id
                WHERE it.type = 'out'
                AND p.deleted_at IS NULL
                AND it.transaction_date BETWEEN ? AND ?
                GROUP BY it.ink_id
                ORDER BY total DESC
            ");
            $stmt->execute([$start, $end]);
            $top_inks = $stmt->fetchAll();

            echo json_encode([
                'success' => true, 
                'data' => [
                    'history' => $history,
                    'summary' => [
                        'total' => $total_quantity,
                        'departments' => $top_departments,
                        'inks' => $top_inks
                    ]
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_departments') {
        try {
            $stmt = $pdo->query("SELECT * FROM departments ORDER BY group_name ASC, sub_name ASC");
            $rows = $stmt->fetchAll();
            $data = [];
            foreach ($rows as $r) {
                $grp = $r['group_name'];
                if (!isset($data[$grp])) $data[$grp] = [];
                if (!empty($r['sub_name'])) {
                    $data[$grp][] = ['id' => $r['id'], 'name' => $r['sub_name']];
                } else {
                    // Check if there is an ID for the group itself (where sub_name IS NULL)
                    $data[$grp]['_group_id'] = $r['id'];
                }
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// get_printer_images — ใช้ GET ดึงรูปของปริ้นเตอร์
if ($action === 'get_printer_images') {
    try {
        $printer_id = (int)($_GET['printer_id'] ?? 0);
        if (!$printer_id) throw new Exception('ไม่ระบุ printer_id');
        $stmt = $pdo->prepare("SELECT id, filename, sort_order FROM printer_images WHERE printer_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$printer_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($images as &$img) {
            $img['url'] = 'assets/img/printers/' . $img['filename'];
        }
        ob_clean();
        echo json_encode(['success' => true, 'images' => $images]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'withdraw_ink') {
        try {
            $ink_id = $_POST['ink_id'] ?? 0;
            $printer_id = $_POST['printer_id'] ?? 0;
            $quantity = (int)($_POST['quantity'] ?? 1);

            // 1. เช็คสต๊อกก่อนว่าพอไหม
            $stmt = $pdo->prepare("SELECT current_quantity, name FROM ink_stock WHERE id = ?");
            $stmt->execute([$ink_id]);
            $ink = $stmt->fetch();

            if (!$ink || $ink['current_quantity'] < $quantity) {
                echo json_encode(['success' => false, 'message' => 'หมึกไม่พอในสต๊อก (คงเหลือ: ' . ($ink['current_quantity'] ?? 0) . ')']);
                exit;
            }

            // 2. หักสต๊อก
            $stmt = $pdo->prepare("UPDATE ink_stock SET current_quantity = current_quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $ink_id]);

            $fiscal_year = getFiscalYear(date('Y-m-d'));
            // 3. บันทึกประวัติการเบิก
            $stmt = $pdo->prepare("INSERT INTO ink_transactions (ink_id, printer_id, type, quantity, fiscal_year) VALUES (?, ?, 'out', ?, ?)");
            $stmt->execute([$ink_id, $printer_id, $quantity, $fiscal_year]);

            echo json_encode(['success' => true, 'message' => 'เบิกหมึกสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'add_maintenance_log') {
        try {
            $printer_id = $_POST['printer_id'] ?? 0;
            $symptoms = $_POST['symptoms'] ?? '';
            $cause = $_POST['cause'] ?? '';
            $action_taken = $_POST['action_taken'] ?? '';
            $repair_date = $_POST['repair_date'] ?? date('Y-m-d');
            $repair_time = $_POST['repair_time'] ?? date('H:i');
            $technician = $_POST['technician_name'] ?? '';
            $status = $_POST['status'] ?? 'normal';

            // ออโต้เพิ่มคอลัมน์ status_after_repair (เผื่อยังไม่มีในฐานข้อมูล)
            try { $pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN status_after_repair VARCHAR(20) DEFAULT NULL"); } catch (Exception $e) {}

            // 1. บันทึกประวัติการซ่อม
            $fiscal_year = getFiscalYear($repair_date);
            $stmt = $pdo->prepare("INSERT INTO maintenance_logs (printer_id, symptoms, cause, action_taken, repair_date, repair_time, technician_name, status_after_repair, fiscal_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$printer_id, $symptoms, $cause, $action_taken, $repair_date, $repair_time, $technician, $status, $fiscal_year]);

            // 2. อัปเดตสถานะของปริ้นเตอร์เครื่องนั้น
            if ($status === 'retired') {
                $stmt = $pdo->prepare("UPDATE printers SET status = ?, retired_at = NOW(), retired_reason = ? WHERE id = ?");
                $stmt->execute([$status, $cause, $printer_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE printers SET status = ? WHERE id = ?");
                $stmt->execute([$status, $printer_id]);
            }

            echo json_encode(['success' => true, 'message' => 'บันทึกประวัติการซ่อมสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'edit_maintenance_log') {
        try {
            $log_id = $_POST['log_id'] ?? 0;
            $symptoms = $_POST['symptoms'] ?? '';
            $action_taken = $_POST['action_taken'] ?? '';
            $technician = $_POST['technician_name'] ?? '';
            $status = $_POST['status'] ?? 'normal';

            $stmt = $pdo->prepare("UPDATE maintenance_logs SET symptoms = ?, action_taken = ?, technician_name = ?, status_after_repair = ? WHERE id = ?");
            $stmt->execute([$symptoms, $action_taken, $technician, $status, $log_id]);

            // อัปเดตสถานะล่าสุดของปริ้นเตอร์ด้วย (อ้างอิงจากรายการล่าสุดที่เพิ่งแก้)
            $stmt = $pdo->prepare("SELECT printer_id FROM maintenance_logs WHERE id = ?");
            $stmt->execute([$log_id]);
            $printer_id = $stmt->fetchColumn();

            if ($printer_id) {
                if ($status === 'retired') {
                    $stmt = $pdo->prepare("UPDATE printers SET status = ?, retired_at = NOW(), retired_reason = ? WHERE id = ?");
                    $stmt->execute([$status, $cause, $printer_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE printers SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $printer_id]);
                }
            }

            echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete_maintenance_log') {
        try {
            $log_id = $_POST['log_id'] ?? 0;
            if (!$log_id) {
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลประวัติการซ่อม']);
                exit;
            }

            // เตรียม column deleted_at ถ้ายังไม่มี
            try { $pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch (Exception $e) {}

            $stmt = $pdo->prepare("UPDATE maintenance_logs SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$log_id]);

            echo json_encode(['success' => true, 'message' => 'ย้ายประวัติการซ่อมไปถังขยะสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'restore_maintenance_log') {
        try {
            $log_id = $_POST['log_id'] ?? 0;
            $stmt = $pdo->prepare("UPDATE maintenance_logs SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$log_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'permanent_delete_maintenance_log') {
        try {
            $log_id = $_POST['log_id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM maintenance_logs WHERE id = ?");
            $stmt->execute([$log_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'add_ink') {
        try {
            $barcode = $_POST['barcode'] ?? '';
            $name = $_POST['name'] ?? '';
            $type = $_POST['type'] ?? 'liquid';
            $brand = $_POST['brand'] ?? '';
            $model = $_POST['model'] ?? '';
            $qty = $_POST['current_quantity'] ?? 0;
            $min = $_POST['min_quantity'] ?? 5;

            $stmt = $pdo->prepare("INSERT INTO ink_stock (barcode, name, type, brand, model, current_quantity, min_quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$barcode, $name, $type, $brand, $model, $qty, $min]);

            echo json_encode(['success' => true, 'message' => 'ลงทะเบียนหมึกใหม่สำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_stock') {
        try {
            $barcode = $_POST['barcode'] ?? '';
            $quantity = (int)($_POST['quantity'] ?? 0);
            
            // อัปเดตจำนวนในสต๊อกหลัก
            $stmt = $pdo->prepare("UPDATE ink_stock SET current_quantity = current_quantity + ? WHERE barcode = ?");
            $stmt->execute([$quantity, $barcode]);

            // บันทึกประวัติการรับเข้า (ไม่ระบุ printer_id เพราะเป็นการรับเข้าคลัง)
            $stmt = $pdo->prepare("SELECT id FROM ink_stock WHERE barcode = ?");
            $stmt->execute([$barcode]);
            $ink_id = $stmt->fetchColumn();

            $fiscal_year = getFiscalYear(date('Y-m-d'));
            $stmt = $pdo->prepare("INSERT INTO ink_transactions (ink_id, type, quantity, fiscal_year) VALUES (?, 'in', ?, ?)");
            $stmt->execute([$ink_id, $quantity, $fiscal_year]);

            echo json_encode(['success' => true, 'message' => 'เพิ่มสต๊อกสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'edit_ink') {
        try {
            $ink_id = $_POST['ink_id'] ?? 0;
            $name = $_POST['name'] ?? '';
            $type = $_POST['type'] ?? 'laser';
            $brand = $_POST['brand'] ?? '';
            $qty = $_POST['current_quantity'] ?? 0;
            $min = $_POST['min_quantity'] ?? 5;

            $stmt = $pdo->prepare("UPDATE ink_stock SET name = ?, type = ?, brand = ?, current_quantity = ?, min_quantity = ? WHERE id = ?");
            $stmt->execute([$name, $type, $brand, $qty, $min, $ink_id]);

            echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete_ink') {
        try {
            $ink_id = $_POST['ink_id'] ?? 0;
            
            // เปลี่ยนเป็น Soft Delete
            $stmt = $pdo->prepare("UPDATE ink_stock SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$ink_id]);

            echo json_encode(['success' => true, 'message' => 'ย้ายรายการหมึกไปถังขยะเรียบร้อยแล้ว']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'add_printer') {
        try {
            $brand          = $_POST['brand'] ?? '';
            $model          = $_POST['model'] ?? '';
            $serial         = $_POST['serial_number'] ?? '';
            $department     = $_POST['department'] ?? '';
            $location       = $_POST['location'] ?? '';
            $year_be        = $_POST['year_be'] ?? '';
            
            // สร้าง QR Code ID แบบสุ่ม
            $qr_id = 'PRN-' . strtoupper(substr(md5(time() . $serial), 0, 8));

            $stmt = $pdo->prepare("INSERT INTO printers (brand, model, serial_number, department, location, qr_code_id, year_be) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$brand, $model, $serial, $department, $location, $qr_id, $year_be]);

            echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลปริ้นเตอร์สำเร็จ', 'qr_id' => $qr_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'edit_printer') {
        try {
            $printer_id     = $_POST['printer_id'] ?? 0;
            $brand          = $_POST['brand'] ?? '';
            $model          = $_POST['model'] ?? '';
            $serial         = $_POST['serial_number'] ?? '';
            $department     = $_POST['department'] ?? '';
            $location       = $_POST['location'] ?? '';
            $year_be        = $_POST['year_be'] ?? '';

            $stmt = $pdo->prepare("SELECT department, location FROM printers WHERE id = ?");
            $stmt->execute([$printer_id]);
            $current = $stmt->fetch();

            if ($current && ($current['department'] !== $department || $current['location'] !== $location)) {
                $stmtMove = $pdo->prepare("INSERT INTO printer_movements (printer_id, old_department, new_department, old_location, new_location) VALUES (?, ?, ?, ?, ?)");
                $stmtMove->execute([$printer_id, $current['department'], $department, $current['location'], $location]);
            }

            $stmt = $pdo->prepare("UPDATE printers SET brand = ?, model = ?, serial_number = ?, department = ?, location = ?, year_be = ? WHERE id = ?");
            $stmt->execute([$brand, $model, $serial, $department, $location, $year_be, $printer_id]);

            echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'retire_printer') {
        try {
            $printer_id = $_POST['printer_id'] ?? 0;
            $reason = $_POST['reason'] ?? '';
            $stmt = $pdo->prepare("UPDATE printers SET status = 'retired', retired_at = NOW(), retired_reason = ? WHERE id = ?");
            $stmt->execute([$reason, $printer_id]);
            echo json_encode(['success' => true, 'message' => 'แทงจำหน่ายปริ้นเตอร์เรียบร้อยแล้ว']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }


    if ($action === 'delete_printer') {
        try {
            $printer_id = $_POST['printer_id'] ?? 0;
            // Soft Delete ปริ้นเตอร์
            $stmt = $pdo->prepare("UPDATE printers SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$printer_id]);

            // Soft Delete ประวัติซ่อมของปริ้นเตอร์นี้ด้วย (เพื่อให้กู้คืนกลับมาพร้อมกัน)
            try { $pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch (Exception $e) {}
            $stmt = $pdo->prepare("UPDATE maintenance_logs SET deleted_at = NOW() WHERE printer_id = ? AND deleted_at IS NULL");
            $stmt->execute([$printer_id]);

            echo json_encode(['success' => true, 'message' => 'ย้ายไปถังขยะเรียบร้อยแล้ว']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'restore_printer') {
        try {
            $printer_id = $_POST['printer_id'] ?? 0;
            // กู้คืนปริ้นเตอร์
            $stmt = $pdo->prepare("UPDATE printers SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$printer_id]);

            // กู้คืนประวัติซ่อมที่ถูก soft-delete พร้อมกับปริ้นเตอร์ด้วย
            $stmt = $pdo->prepare("UPDATE maintenance_logs SET deleted_at = NULL WHERE printer_id = ? AND deleted_at IS NOT NULL");
            $stmt->execute([$printer_id]);

            echo json_encode(['success' => true, 'message' => 'กู้คืนข้อมูลสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'permanent_delete_printer') {
        try {
            $printer_id = $_POST['printer_id'] ?? 0;

            // ลบข้อมูลที่เกี่ยวข้อง
            $stmt = $pdo->prepare("DELETE FROM maintenance_logs WHERE printer_id = ?");
            $stmt->execute([$printer_id]);

            $stmt = $pdo->prepare("DELETE FROM ink_transactions WHERE printer_id = ?");
            $stmt->execute([$printer_id]);

            $stmt = $pdo->prepare("DELETE FROM printers WHERE id = ?");
            $stmt->execute([$printer_id]);

            echo json_encode(['success' => true, 'message' => 'ลบข้อมูลถาวรสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'restore_ink') {
        try {
            $ink_id = $_POST['ink_id'] ?? 0;
            $stmt = $pdo->prepare("UPDATE ink_stock SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$ink_id]);
            echo json_encode(['success' => true, 'message' => 'กู้คืนข้อมูลหมึกสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'permanent_delete_ink') {
        try {
            $ink_id = $_POST['ink_id'] ?? 0;
            // ลบประวัติการทำรายการหมึกด้วย
            $stmt = $pdo->prepare("DELETE FROM ink_transactions WHERE ink_id = ?");
            $stmt->execute([$ink_id]);
            // ลบตัวหมึก
            $stmt = $pdo->prepare("DELETE FROM ink_stock WHERE id = ?");
            $stmt->execute([$ink_id]);
            echo json_encode(['success' => true, 'message' => 'ลบข้อมูลหมึกถาวรสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ===== DEPARTMENTS / GROUPS API =====
    if ($action === 'add_group') {
        try {
            $group_name = trim($_POST['group_name'] ?? '');
            if (!$group_name) throw new Exception("ชื่อกลุ่มงานห้ามว่าง");
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE group_name = ?");
            $stmt->execute([$group_name]);
            if ($stmt->fetchColumn() > 0) throw new Exception("มีกลุ่มงานนี้อยู่แล้ว");

            $stmt = $pdo->prepare("INSERT INTO departments (group_name, sub_name) VALUES (?, NULL)");
            $stmt->execute([$group_name]);
            echo json_encode(['success' => true, 'message' => 'เพิ่มกลุ่มงานสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'edit_group') {
        try {
            $old_name = trim($_POST['old_name'] ?? '');
            $new_name = trim($_POST['new_name'] ?? '');
            if (!$old_name || !$new_name) throw new Exception("ข้อมูลไม่ครบถ้วน");

            $stmt = $pdo->prepare("UPDATE departments SET group_name = ? WHERE group_name = ?");
            $stmt->execute([$new_name, $old_name]);
            
            // Cascade update to printers that use this group name
            $stmt = $pdo->prepare("UPDATE printers SET department = ? WHERE department = ?");
            $stmt->execute([$new_name, $old_name]);

            echo json_encode(['success' => true, 'message' => 'แก้ไขชื่อกลุ่มงานสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'delete_group') {
        try {
            $group_name = trim($_POST['group_name'] ?? '');
            if (!$group_name) throw new Exception("ไม่ระบุชื่อกลุ่มงาน");
            
            // Get all sub names under this group
            $stmt = $pdo->prepare("SELECT sub_name FROM departments WHERE group_name = ? AND sub_name IS NOT NULL");
            $stmt->execute([$group_name]);
            $subs = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $names = $subs;
            $names[] = $group_name;

            $stmt = $pdo->prepare("DELETE FROM departments WHERE group_name = ?");
            $stmt->execute([$group_name]);

            // Set printers to 'ไม่ระบุ'
            if (!empty($names)) {
                $placeholders = implode(',', array_fill(0, count($names), '?'));
                $stmt = $pdo->prepare("UPDATE printers SET department = 'ไม่ระบุ' WHERE department IN ($placeholders)");
                $stmt->execute($names);
            }

            echo json_encode(['success' => true, 'message' => 'ลบกลุ่มงานและหน่วยงานย่อยทั้งหมดสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'add_sub') {
        try {
            $group_name = trim($_POST['group_name'] ?? '');
            $sub_name = trim($_POST['sub_name'] ?? '');
            if (!$group_name || !$sub_name) throw new Exception("ข้อมูลไม่ครบถ้วน");
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE group_name = ? AND sub_name = ?");
            $stmt->execute([$group_name, $sub_name]);
            if ($stmt->fetchColumn() > 0) throw new Exception("มีหน่วยงานนี้อยู่แล้วในกลุ่มงานดังกล่าว");

            $stmt = $pdo->prepare("INSERT INTO departments (group_name, sub_name) VALUES (?, ?)");
            $stmt->execute([$group_name, $sub_name]);
            echo json_encode(['success' => true, 'message' => 'เพิ่มหน่วยงานสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'edit_sub') {
        try {
            $id = $_POST['id'] ?? 0;
            $sub_name = trim($_POST['sub_name'] ?? '');
            if (!$id || !$sub_name) throw new Exception("ข้อมูลไม่ครบถ้วน");
            
            $stmt = $pdo->prepare("SELECT sub_name FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $old_sub_name = $stmt->fetchColumn();

            $stmt = $pdo->prepare("UPDATE departments SET sub_name = ? WHERE id = ?");
            $stmt->execute([$sub_name, $id]);

            if ($old_sub_name && $old_sub_name !== $sub_name) {
                $stmt = $pdo->prepare("UPDATE printers SET department = ? WHERE department = ?");
                $stmt->execute([$sub_name, $old_sub_name]);
            }

            echo json_encode(['success' => true, 'message' => 'แก้ไขหน่วยงานสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'delete_sub') {
        try {
            $id = $_POST['id'] ?? 0;
            if (!$id) throw new Exception("ไม่ระบุหน่วยงาน");

            $stmt = $pdo->prepare("SELECT sub_name FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $old_sub_name = $stmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$id]);

            if ($old_sub_name) {
                $stmt = $pdo->prepare("UPDATE printers SET department = 'ไม่ระบุ' WHERE department = ?");
                $stmt->execute([$old_sub_name]);
            }

            echo json_encode(['success' => true, 'message' => 'ลบหน่วยงานสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ===== PRINTER IMAGES API =====

    if ($action === 'upload_printer_image') {
        try {
            $printer_id = (int)($_POST['printer_id'] ?? 0);
            if (!$printer_id) throw new Exception('ไม่ระบุ printer_id');

            $uploadDir = __DIR__ . '/assets/img/printers/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $file = $_FILES['image'] ?? null;
            if (!$file) throw new Exception('ไม่ได้รับไฟล์');

            // แปล PHP upload error code เป็นภาษาไทย
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $phpErrors = [
                    UPLOAD_ERR_INI_SIZE   => 'ไฟล์ใหญ่เกิน upload_max_filesize ที่ PHP กำหนด (ติดต่อแอดมิน)',
                    UPLOAD_ERR_FORM_SIZE  => 'ไฟล์ใหญ่เกิน MAX_FILE_SIZE ที่ form กำหนด',
                    UPLOAD_ERR_PARTIAL    => 'อัปโหลดไม่สมบูรณ์ กรุณาลองใหม่',
                    UPLOAD_ERR_NO_FILE    => 'ไม่พบไฟล์ที่อัปโหลด',
                    UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบ temporary folder (ติดต่อแอดมิน)',
                    UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ได้ (ตรวจสอบ permission)',
                    UPLOAD_ERR_EXTENSION  => 'PHP extension บล็อกการอัปโหลด',
                ];
                $msg = $phpErrors[$file['error']] ?? 'เกิดข้อผิดพลาด code: ' . $file['error'];
                throw new Exception($msg);
            }

            // ตรวจสอบขนาดสูงสุด 30MB
            $maxBytes = 30 * 1024 * 1024;
            if ($file['size'] > $maxBytes) {
                throw new Exception('ไฟล์ใหญ่เกิน 30MB (' . round($file['size']/1024/1024, 1) . 'MB) กรุณาย่อขนาดก่อนอัปโหลด');
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new Exception('รองรับเฉพาะ JPG, PNG, WEBP, GIF เท่านั้น');
            }

            $filename = 'p' . $printer_id . '_' . uniqid() . '.jpg';
            $destPath = $uploadDir . $filename;

            // ตรวจสอบ permission ก่อนเขียนไฟล์
            if (!is_writable($uploadDir)) {
                throw new Exception('ไม่มีสิทธิ์เขียนไฟล์ในโฟลเดอร์ — รัน: sudo chown -R www-data:www-data ' . $uploadDir);
            }

            // Auto compress & resize ด้วย GD
            $saved = false;
            if (function_exists('imagecreatefromjpeg')) {
                $info = @getimagesize($file['tmp_name']);
                if ($info) {
                    $mime = $info['mime'];
                    $srcImg = null;
                    if ($mime === 'image/jpeg') $srcImg = @imagecreatefromjpeg($file['tmp_name']);
                    elseif ($mime === 'image/png')  $srcImg = @imagecreatefrompng($file['tmp_name']);
                    elseif ($mime === 'image/webp') $srcImg = @imagecreatefromwebp($file['tmp_name']);
                    elseif ($mime === 'image/gif')  $srcImg = @imagecreatefromgif($file['tmp_name']);

                    if ($srcImg) {
                        $origW = imagesx($srcImg); $origH = imagesy($srcImg);
                        $maxDim = 1920;
                        if ($origW > $maxDim || $origH > $maxDim) {
                            if ($origW >= $origH) { $newW = $maxDim; $newH = (int)($origH * $maxDim / $origW); }
                            else { $newH = $maxDim; $newW = (int)($origW * $maxDim / $origH); }
                            $dst = imagecreatetruecolor($newW, $newH);
                            imagealphablending($dst, false); imagesavealpha($dst, true);
                            imagecopyresampled($dst, $srcImg, 0,0,0,0, $newW,$newH, $origW,$origH);
                            imagedestroy($srcImg); $srcImg = $dst;
                        }
                        if (@imagejpeg($srcImg, $destPath, 85)) $saved = true;
                        imagedestroy($srcImg);
                    }
                }
            }
            // Fallback: move_uploaded_file ถ้า GD ไม่ได้ทำ
            if (!$saved) {
                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    throw new Exception('บันทึกไฟล์ไม่สำเร็จ — Permission denied หรือ disk full');
                }
            }

            // ตรวจสอบว่าไฟล์ถูกสร้างจริง
            if (!file_exists($destPath)) {
                throw new Exception('ไฟล์ไม่ถูกสร้าง — ตรวจสอบ permission: ' . $uploadDir);
            }

            // sort_order = max + 1
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM printer_images WHERE printer_id = ?");
            $stmt->execute([$printer_id]);
            $nextOrder = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO printer_images (printer_id, filename, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$printer_id, $filename, $nextOrder]);
            $imgId = $pdo->lastInsertId();

            ob_clean(); // ล้าง warning ที่อาจเกิดขึ้นก่อน JSON
            echo json_encode(['success' => true, 'id' => $imgId, 'filename' => $filename, 'url' => 'assets/img/printers/' . $filename]);
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete_printer_image') {
        try {
            $img_id = (int)($_POST['img_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT filename, printer_id FROM printer_images WHERE id = ?");
            $stmt->execute([$img_id]);
            $img = $stmt->fetch();
            if (!$img) throw new Exception('ไม่พบรูป');

            $filePath = __DIR__ . '/assets/img/printers/' . $img['filename'];
            if (file_exists($filePath)) unlink($filePath);

            $pdo->prepare("DELETE FROM printer_images WHERE id = ?")->execute([$img_id]);

            // เรียง sort_order ใหม่
            $stmt = $pdo->prepare("SELECT id FROM printer_images WHERE printer_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$img['printer_id']]);
            $remaining = $stmt->fetchAll();
            foreach ($remaining as $i => $r) {
                $pdo->prepare("UPDATE printer_images SET sort_order = ? WHERE id = ?")->execute([$i, $r['id']]);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'reorder_printer_images') {
        try {
            $order = json_decode($_POST['order'] ?? '[]', true); // [{id,sort_order},...]
            $stmt = $pdo->prepare("UPDATE printer_images SET sort_order = ? WHERE id = ?");
            foreach ($order as $item) {
                $stmt->execute([(int)$item['sort_order'], (int)$item['id']]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    }

// Auto Cleanup: ลบข้อมูลในถังขยะที่เกิน 30 วัน
try {
    $pdo->exec("DELETE FROM printers WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $pdo->exec("DELETE FROM ink_stock WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
} catch (Exception $e) {}

echo json_encode(['success' => false, 'message' => 'Invalid Request']);
?>
