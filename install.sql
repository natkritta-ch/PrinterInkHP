-- สร้างตารางเก็บข้อมูลปริ้นเตอร์
CREATE TABLE IF NOT EXISTS printers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_number VARCHAR(100) UNIQUE NOT NULL,
    brand VARCHAR(50),
    model VARCHAR(100),
    department VARCHAR(100),
    status ENUM('normal', 'repairing', 'broken') DEFAULT 'normal',
    qr_code_id VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- สร้างตารางเก็บข้อมูลสต๊อกหมึก
CREATE TABLE IF NOT EXISTS ink_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('liquid', 'powder', 'laser', 'ribbon') NOT NULL,
    brand VARCHAR(50),
    current_quantity INT DEFAULT 0,
    min_quantity INT DEFAULT 5,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- สร้างตารางบันทึกการเบิกหมึก (เชื่อมโยงกับ Printer)
CREATE TABLE IF NOT EXISTS ink_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ink_id INT,
    printer_id INT,
    type ENUM('in', 'out') NOT NULL,
    quantity INT NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ink_id) REFERENCES ink_stock(id),
    FOREIGN KEY (printer_id) REFERENCES printers(id)
);

-- สร้างตารางบันทึกประวัติการซ่อม (Maintenance)
CREATE TABLE IF NOT EXISTS maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    printer_id INT,
    symptoms TEXT,
    cause TEXT,
    action_taken TEXT,
    repair_date DATE,
    repair_time TIME,
    technician_name VARCHAR(100),
    FOREIGN KEY (printer_id) REFERENCES printers(id)
);
