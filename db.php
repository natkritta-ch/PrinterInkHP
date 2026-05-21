<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = '127.0.0.1';
$db   = 'printerink_hp';
$user = 'admin';
$pass = 'Ch_14112547';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// Migration: ตารางประวัติการย้ายแผนก (Transfer History)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS printer_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        printer_id INT NOT NULL,
        old_department VARCHAR(255),
        new_department VARCHAR(255),
        old_location VARCHAR(255),
        new_location VARCHAR(255),
        moved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (printer_id) REFERENCES printers(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Exception $e) {}

// ========================================
// Thai Buddhist Era date helpers
// ========================================

/**
 * แสดงวันที่แบบ พ.ศ.  เช่น 28/04/2569
 * @param string $dateStr  ค่าวันที่จากฐานข้อมูล (Y-m-d หรือ Y-m-d H:i:s)
 * @param bool   $withTime เพิ่มเวลาด้วยไหม
 */
function thdate(string $dateStr, bool $withTime = false): string {
    if (!$dateStr || $dateStr === '0000-00-00') return '—';
    $ts = strtotime($dateStr);
    if ($ts === false) return '—';
    $be = (int)date('Y', $ts) + 543;
    $fmt = date('d/m/', $ts) . $be;
    if ($withTime) $fmt .= ' ' . date('H:i', $ts);
    return $fmt;
}

/**
 * แสดงวันที่แบบ พ.ศ. พร้อมเวลา เช่น 28/04/2569 14:30
 */
function thdatetime(string $dateStr): string {
    return thdate($dateStr, true);
}

/**
 * คืนค่าปี พ.ศ. ปัจจุบัน
 */
function currentBEYear(): int {
    return (int)date('Y') + 543;
}

/**
 * คำนวณปีงบประมาณจากวันที่ (เริ่ม 1 ต.ค.)
 * @param string $dateStr (Y-m-d)
 * @return string ปีงบประมาณ (พ.ศ.)
 */
function getFiscalYear(string $dateStr): string {
    $ts = strtotime($dateStr);
    $year = (int)date('Y', $ts) + 543;
    $month = (int)date('m', $ts);
    if ($month >= 10) {
        $year += 1;
    }
    return (string)$year;
}

// ========================================
// Auto Migrations (รันทุกครั้งที่โหลดหน้า)
// ========================================
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
    
    // Migration: ขยายขนาดคอลัมน์เพื่อให้รองรับภาษาไทยยาวๆ (แก้ปัญหา Data truncated)
    $pdo->exec("ALTER TABLE ink_stock MODIFY COLUMN type VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE ink_stock MODIFY COLUMN brand VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Migration: เพิ่มคอลัมน์ deleted_at ถ้ายังไม่มี
    try { $pdo->exec("ALTER TABLE ink_stock ADD COLUMN deleted_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}

    // Migration: แปลงข้อมูลเก่าให้เป็นรูปแบบใหม่เพื่อรองรับตัวกรอง
    try {
        $pdo->exec("UPDATE ink_stock SET type = 'เลเซอร์' WHERE type = 'laser'");
        $pdo->exec("UPDATE ink_stock SET type = 'น้ำหมึกInkjet' WHERE type = 'liquid'");
    } catch (Exception $e) {}

    // Migration: เพิ่มคอลัมน์ location ใน printers
    try { $pdo->exec("ALTER TABLE printers ADD COLUMN location VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}

    // Migration: ลบข้อจำกัด UNIQUE ของ serial_number ออก เพื่อให้สามารถใส่ '-' หรือรหัสชั่วคราวซ้ำกันได้
    try { $pdo->exec("ALTER TABLE printers DROP INDEX serial_number"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE printers DROP INDEX uk_serial_number"); } catch (Exception $e) {}

    // Migration: ย้ายข้อมูลหน่วยงานที่เปลี่ยนชื่อ (ลบออกแล้ว — ไม่ต้อง force-rename กลุ่มงานหลักอีกต่อไป)
    // หมายเหตุ: เดิมมี migration ที่บังคับเปลี่ยน 'กลุ่มงานบริการด้านปฐมภูมิ' → 'คลินิกพิเศษ NCD'
    // ซึ่งทำให้ปริ้นเตอร์ที่ register ตรงกับกลุ่มงานหลักถูกเปลี่ยนทุกครั้งที่โหลดหน้า
    try {
        $pdo->exec("UPDATE printers SET department = 'คลินิกพิเศษ NCD' WHERE department = 'คลินิกพิเศษ'");
    } catch (Exception $e) {}

    // Migration: สร้างตารางระบบผู้ใช้
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) DEFAULT NULL,
            role ENUM('admin', 'user') DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Migration: สร้างตาราง departments
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_name VARCHAR(255) NOT NULL,
            sub_name VARCHAR(255) DEFAULT NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    
    // ตรวจสอบว่าตาราง departments ว่างเปล่าหรือไม่
    $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
    if ($stmt->fetchColumn() == 0) {
        $hierMap = [
            'กลุ่มงานสุขภาพดิจิทัล' => ['งานเทคโนโลยีสารสนเทศและพัฒนาระบบสุขภาพดิจิทัล','งานเวชระเบียนและข้อมูลทางการแพทย์','งานประกัน'],
            'กลุ่มงานบริหารทั่วไป' => ['ธุรการ','พัสดุ','การเงินและบัญชี','แผนกซ่อมบำรุง','ซักฟอก','งานยานพาหนะ','ดูแลสวน'],
            'กลุ่มงานการพยาบาล' => ['ตึกหญิง','ตึกชาย','ตึกศัลยกรรม','ตึกหญิง 2','แผนกอุบัติเหตุและฉุกเฉิน ER','ตึกสูตินรีเวช LR','หน้าห้องตรวจ OPD','ห้องผ่าตัด OR','ไตเทียม','พนักงานต้อนรับ','หน่วยจ่ายกลาง','งานประชาสัมพันธ์','งานพัฒนาคุณภาพ','OPD ศัลยกรรม','OPD กุมารเวชกรรม','IPD กุมารเวชกรรม','ป้องกันและควบคุมการติดเชื้อ IC','ฉีดยาทำแผล'],
            'กลุ่มงานรังสีวิทยา' => [],
            'กลุ่มงานทันตกรรม' => [],
            'กลุ่มงานโภชนศาสตร์' => ['โภชนาการ','ศูนย์เสริมสุขร่มไทร'],
            'กลุ่มงานการแพทย์' => ['แพทย์ทั่วไป','วิสัญญีแพทย์','อายุรแพทย์','สูตินรีแพทย์','กุมารแพทย์','เวชศาสตร์ชุมชนและครอบครัว','ศัลยแพทย์'],
            'กลุ่มงานเวชกรรมฟื้นฟู' => [],
            'กลุ่มงานการแพทย์แผนไทย' => [],
            'กลุ่มงานจิตเวชและยาเสพติด' => [],
            'กลุ่มงานพัฒนาคุณภาพ' => [],
            'กลุ่มงานเทคนิคการแพทย์' => [],
            'กลุ่มงานเภสัชกรรมและคุ้มครองผู้บริโภค' => ['ห้องจ่ายยาผู้ป่วยใน','ห้องจ่ายยาผู้ป่วยนอก','คลังยา','งานคุ้มครองผู้บริโภค'],
            'กลุ่มงานประกันสุขภาพยุทธศาสตร์' => [],
            'กลุ่มงานบริการด้านปฐมภูมิ' => ['สุขาภิบาลและสิ่งแวดล้อม','เวชปฏิบัติครอบครัวและชุมชน','งานสุขศึกษา','ใจประสานใจ','PCU ม่วงคำ','คลินิกพิเศษ NCD','เตาเผา'],
        ];
        $insertDept = $pdo->prepare("INSERT INTO departments (group_name, sub_name) VALUES (?, ?)");
        foreach ($hierMap as $group => $subs) {
            if (empty($subs)) {
                $insertDept->execute([$group, null]);
            } else {
                foreach ($subs as $sub) {
                    $insertDept->execute([$group, $sub]);
                }
            }
        }
    }

    // Migration: เพิ่มคอลัมน์ full_name ถ้าตารางมีอยู่แล้วแต่ยังไม่มีคอลัมน์นี้
    try { $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) DEFAULT NULL AFTER password"); } catch (Exception $e) {}

    // ตรวจสอบว่ามีผู้ใช้อย่างน้อย 1 คนหรือไม่ ถ้าไม่มีให้สร้างแอดมินเริ่มต้น
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $defaultPassword = password_hash('0000', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, full_name, role) VALUES ('admin', '$defaultPassword', 'ผู้ดูแลระบบ', 'admin')");
    }

    // Migration: ปรับปรุงตาราง printers สำหรับ "แทงจำหน่าย" (Retired)
    try { $pdo->exec("ALTER TABLE printers MODIFY COLUMN status ENUM('normal', 'repairing', 'broken', 'retired') DEFAULT 'normal'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE printers ADD COLUMN retired_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE printers ADD COLUMN retired_reason TEXT DEFAULT NULL"); } catch (Exception $e) {}

    // Migration: ปรับปรุงตารางสำหรับการคำนวณปีงบประมาณอัตโนมัติ
    try { $pdo->exec("ALTER TABLE ink_transactions ADD COLUMN fiscal_year VARCHAR(10) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN fiscal_year VARCHAR(10) DEFAULT NULL"); } catch (Exception $e) {}

} catch (Exception $e) {}
?>
