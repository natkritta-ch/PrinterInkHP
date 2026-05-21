<?php
require_once '../db.php';

$qr_id = $_GET['qr_id'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);

try {
    // ดึงข้อมูลปริ้นเตอร์
    $stmt = $pdo->prepare("SELECT * FROM printers WHERE qr_code_id = ?");
    $stmt->execute([$qr_id]);
    $printer = $stmt->fetch();

    if (!$printer) {
        echo "<div class='card'><h3>ไม่พบข้อมูล</h3><p>ไม่พบปริ้นเตอร์ที่ตรงกับ QR Code นี้ในระบบ</p></div>";
        exit;
    }

    // ดึงสถิติเบื้องต้น
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ink_transactions WHERE printer_id = ? AND type = 'out'");
    $stmt->execute([$printer['id']]);
    $inkCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_logs WHERE printer_id = ?");
    $stmt->execute([$printer['id']]);
    $repairCount = $stmt->fetchColumn();

    // ดึงประวัติการเบิกหมึก
    $stmt = $pdo->prepare("
        SELECT it.*, is.name as ink_name 
        FROM ink_transactions it 
        JOIN ink_stock `is` ON it.ink_id = `is`.id 
        WHERE it.printer_id = ? 
        ORDER BY it.transaction_date DESC LIMIT 10
    ");
    $stmt->execute([$printer['id']]);
    $ink_history = $stmt->fetchAll();

    // ดึงประวัติการซ่อม
    $stmt = $pdo->prepare("SELECT * FROM maintenance_logs WHERE printer_id = ? ORDER BY repair_date DESC, repair_time DESC LIMIT 10");
    $stmt->execute([$printer['id']]);
    $maintenance_history = $stmt->fetchAll();

    // ดึงประวัติการย้ายแผนก
    $stmt = $pdo->prepare("SELECT * FROM printer_movements WHERE printer_id = ? ORDER BY moved_at DESC");
    $stmt->execute([$printer['id']]);
    $movement_history = $stmt->fetchAll();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>

<div class="detail-header" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:10px;min-width:0">
            <?php if($isLoggedIn): ?>
            <button onclick="window.location.hash='printers';"
                style="background:var(--bg-body);border:none;width:36px;height:36px;border-radius:10px;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">⬅️</button>
            <?php endif; ?>
            <h1 style="margin:0;font-size:1.2rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">รายละเอียดปริ้นเตอร์</h1>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0">
            <button onclick="printLabel()" style="padding:8px 14px;font-size:0.85rem;border:1.5px solid var(--primary);color:var(--primary);background:white;border-radius:12px;cursor:pointer;font-weight:600">🖨️ พิมพ์ป้าย</button>
            <?php if($isLoggedIn && $_SESSION['role'] === 'admin'): ?>
            <button id="retire-printer-btn" style="padding:8px 14px;font-size:0.85rem;border:1.5px solid #dc2626;color:#dc2626;background:white;border-radius:12px;cursor:pointer;font-weight:600">⛔ แทงจำหน่าย</button>
            <?php endif; ?>
            <button class="btn-secondary" id="edit-printer-btn" style="padding:8px 14px;font-size:0.85rem">✏️ แก้ไข</button>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
    <!-- Card ข้อมูลเครื่อง -->
    <div class="card">
        <!-- QR + Info row on mobile -->
        <div style="display:flex;gap:16px;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap">
            <div style="flex-shrink:0">
                <div id="detail-qr" style="display:inline-block;padding:10px;background:white;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.06)"></div>
                <p style="margin-top:6px;font-weight:700;color:var(--primary);font-size:0.72rem;text-align:center"><?php echo $printer['qr_code_id']; ?></p>
            </div>
            <div style="flex:1;min-width:140px">
                <span class="badge badge-<?php echo $printer['status']; ?>" <?php echo $printer['status']=='retired' ? 'style="background:#f1f5f9;color:#64748b;border-color:#cbd5e1;"' : ''; ?>>
                    <?php echo ($printer['status']=='normal'?'ปกติ':($printer['status']=='repairing'?'กำลังซ่อม':($printer['status']=='retired'?'จำหน่ายออก':'พัง'))); ?>
                </span>
                <h2 style="margin-top:10px;font-size:1.05rem"><?php echo htmlspecialchars($printer['brand'].' '.$printer['model']); ?></h2>
                <p style="color:var(--text-muted);font-size:0.82rem">SN: <?php echo htmlspecialchars($printer['serial_number']); ?></p>
            </div>
        </div>

        <div style="background:rgba(0,0,0,0.02);padding:12px 14px;border-radius:14px;margin-bottom:16px">
            <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:2px">📍 หน่วยงานที่รับผิดชอบ</p>
            <p style="font-weight:600;font-size:0.95rem"><?php echo htmlspecialchars($printer['department']); ?></p>
            <?php if(!empty($printer['location'])): ?>
            <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:2px;margin-top:10px">🏢 สถานที่วางเครื่อง</p>
            <p style="font-weight:600;font-size:0.95rem"><?php echo htmlspecialchars($printer['location']); ?></p>
            <?php endif; ?>
        </div>

        <!-- Photo Gallery Strip -->
        <?php
        try {
            $stmt = $pdo->prepare("SELECT id, filename FROM printer_images WHERE printer_id = ? ORDER BY sort_order ASC LIMIT 6");
            $stmt->execute([$printer['id']]);
            $detailImages = $stmt->fetchAll();
        } catch(Exception $e) { $detailImages = []; }
        ?>
        <div style="margin-bottom: 20px">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px">
                <span style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px">รูปภาพสภาพเครื่อง</span>
                <button onclick="openDetailPhotoManager(<?= $printer['id'] ?>, '<?= htmlspecialchars(addslashes($printer['brand'].' '.$printer['model'])) ?>')"
                    style="font-size:0.78rem; font-weight:600; color:var(--primary); background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2); padding:4px 12px; border-radius:20px; cursor:pointer; transition:all 0.2s"
                    onmouseenter="this.style.background='rgba(99,102,241,0.15)'"
                    onmouseleave="this.style.background='rgba(99,102,241,0.08)'">
                    📷 จัดการรูป
                </button>
            </div>
            <?php if (empty($detailImages)): ?>
                <div style="text-align:center; padding:20px; background:#f8fafc; border-radius:14px; border:1.5px dashed var(--border)">
                    <div style="font-size:2rem; margin-bottom:6px">📷</div>
                    <div style="font-size:0.8rem; color:var(--text-muted)">ยังไม่มีรูปภาพ — กด "จัดการรูป" เพื่อเพิ่ม</div>
                </div>
            <?php else: ?>
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:6px; border-radius:14px; overflow:hidden">
                    <?php foreach($detailImages as $idx => $dimg): ?>
                        <div style="position:relative; aspect-ratio:1/1; background:#f1f5f9; overflow:hidden; <?= $idx===0 ? 'border-radius:10px 0 0 10px' : '' ?> <?= $idx===2||$idx===5 ? 'border-radius:0 10px 10px 0' : '' ?>">
                            <img src="assets/img/printers/<?= htmlspecialchars($dimg['filename']) ?>"
                                style="width:100%; height:100%; object-fit:cover; cursor:pointer"
                                onclick="openDetailLightbox('assets/img/printers/<?= htmlspecialchars($dimg['filename']) ?>')"
                                alt="printer photo">
                            <?php if($idx===0): ?>
                                <div style="position:absolute;top:4px;left:4px;background:var(--primary);color:white;font-size:0.55rem;font-weight:700;padding:2px 6px;border-radius:8px">หน้าปก</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if(count($detailImages) === 6): ?>
                    <div style="text-align:center; margin-top:6px; font-size:0.75rem; color:var(--text-muted)">แสดง 6 รูปล่าสุด — กด "จัดการรูป" เพื่อดูทั้งหมด</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px">
            <button class="btn-primary" id="withdraw-btn" style="justify-content:center;background:var(--success);box-shadow:0 4px 12px rgba(16,185,129,0.3)">
                📦 เบิกหมึก
            </button>
            <button class="btn-primary" id="maintenance-btn" style="justify-content:center;background:var(--warning);box-shadow:0 4px 12px rgba(245,158,11,0.3)">
                🔧 แจ้งซ่อม
            </button>
        </div>
    </div>

    <!-- Modal สำหรับเบิกหมึก -->
    <div id="withdraw-modal" class="modal">
        <div class="modal-content" style="max-width: 450px">
            <div class="modal-header">
                <h3>ทำรายการเบิกหมึก</h3>
                <button type="button" class="close-withdraw-btn" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
            </div>
            <form id="withdraw-form">
                <input type="hidden" name="printer_id" value="<?php echo $printer['id']; ?>">
                <input type="hidden" name="ink_id" id="withdraw-ink-id">
                <div class="modal-body" style="padding: 24px">
                    <!-- Barcode Input -->
                    <div style="margin-bottom: 20px">
                        <label style="display:block; margin-bottom: 8px; font-weight: 600">แสกนบาร์โค้ดหมึก</label>
                        <div style="display: flex; gap: 10px; align-items: center">
                            <input type="text" id="withdraw-barcode-input" placeholder="แสกนหรือพิมพ์บาร์โค้ด..." 
                                autocomplete="off"
                                style="flex: 1; padding: 14px 16px; border: 2px solid var(--primary); border-radius: 14px; outline: none; font-size: 1rem; font-family: inherit">
                            <button type="button" id="withdraw-scan-btn" class="scan-trigger" 
                                style="background: var(--primary); color: white; border: none; padding: 14px 18px; border-radius: 14px; font-size: 1.3rem; cursor: pointer">
                                📷
                            </button>
                        </div>
                    </div>

                    <!-- Ink Info Card (shown after scan) -->
                    <div id="withdraw-ink-info" style="display: none; margin-bottom: 20px; padding: 16px; background: rgba(99,102,241,0.06); border-radius: 14px; border: 1px solid rgba(99,102,241,0.2)">
                        <div style="display: flex; justify-content: space-between; align-items: center">
                            <div>
                                <div style="font-weight: 700; font-size: 1rem" id="withdraw-ink-name">—</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted)" id="withdraw-ink-brand">—</div>
                            </div>
                            <div style="text-align: right">
                                <div style="font-size: 0.8rem; color: var(--text-muted)">คงเหลือในสต๊อก</div>
                                <div style="font-size: 1.4rem; font-weight: 700; color: var(--success)" id="withdraw-ink-qty">—</div>
                            </div>
                        </div>
                    </div>
                    <div id="withdraw-not-found" style="display: none; color: var(--danger); font-size: 0.9rem; margin-bottom: 16px; padding: 12px; background: rgba(239,68,68,0.06); border-radius: 10px">
                        ⚠️ ไม่พบหมึกบาร์โค้ดนี้ในระบบ
                    </div>

                    <div style="margin-bottom: 24px">
                        <label style="display:block; margin-bottom: 8px; font-weight: 500">จำนวนที่เบิก</label>
                        <input type="number" name="quantity" value="1" min="1" required 
                            style="width:100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; outline: none; text-align: center; font-size: 1.2rem; font-weight: 700">
                    </div>
                    <button type="submit" id="withdraw-submit-btn" class="btn-primary" disabled
                        style="width: 100%; justify-content: center; height: 50px; font-size: 1.1rem; opacity: 0.5">
                        ยืนยันการเบิกใช้
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal สำหรับแจ้งซ่อม (Maintenance) -->
    <div id="maintenance-modal" class="modal">
        <div class="modal-content" style="max-width: 550px">
            <div class="modal-header">
                <h3>บันทึกประวัติการซ่อมบำรุง</h3>
                <button type="button" class="close-maintenance-btn" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
            </div>
            <form id="maintenance-form">
                <input type="hidden" name="printer_id" value="<?php echo $printer['id']; ?>">
                <div class="modal-body" style="padding: 24px">
                    <style>
                        .datetime-labels { display: flex; margin-bottom: 8px; }
                        .datetime-labels label { flex: 1; text-align: center; font-weight: 600; font-size: 0.95rem; }
                        .datetime-container { display: flex; background: #e2e8f0; border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
                        .datetime-container input { flex: 1; width: 100%; background: transparent !important; border: none !important; text-align: center; padding: 14px 10px; font-weight: 500; font-size: 1rem; color: var(--text-main); outline: none; box-shadow: none !important; cursor: pointer; }
                        .datetime-divider { width: 1px; background: rgba(0,0,0,0.1); margin: 10px 0; }
                    </style>
                    <div class="datetime-labels">
                        <label>วันที่ซ่อม</label>
                        <label>เวลาที่ซ่อม</label>
                    </div>
                    <div class="datetime-container">
                        <input type="text" id="repair_date_picker" name="repair_date" value="<?php echo date('Y-m-d'); ?>" required>
                        <div class="datetime-divider"></div>
                        <input type="text" id="repair_time_picker" name="repair_time" value="<?php echo date('H:i'); ?>" required>
                    </div>
                    <div style="margin-bottom: 16px">
                        <label style="display:block; margin-bottom: 8px; font-weight: 500">อาการเสีย/ปัญหา</label>
                        <textarea name="symptoms" rows="2" placeholder="เช่น กระดาษติดบ่อย, พิมพ์ไม่ชัด" required style="width:100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; outline: none; font-family: inherit"></textarea>
                    </div>
                    <div style="margin-bottom: 16px">
                        <label style="display:block; margin-bottom: 8px; font-weight: 500">สาเหตุที่พบ</label>
                        <textarea name="cause" rows="2" placeholder="ระบุสาเหตุถ้าทราบ" style="width:100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; outline: none; font-family: inherit"></textarea>
                    </div>
                    <div style="margin-bottom: 16px">
                        <label style="display:block; margin-bottom: 8px; font-weight: 500">วิธีแก้ไข/การดำเนินการ</label>
                        <textarea name="action_taken" rows="2" placeholder="ระบุการดำเนินการ เช่น เปลี่ยนดรัม, ทำความสะอาดหัวพิมพ์" style="width:100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; outline: none; font-family: inherit"></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px">
                        <div>
                            <label style="display:block; margin-bottom: 8px; font-weight: 500">ชื่อช่างผู้ซ่อม</label>
                            <input type="text" name="technician_name" placeholder="ชื่อผู้ซ่อม" style="width:100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom: 8px; font-weight: 500">สถานะหลังซ่อม</label>
                            <select name="status" style="width:100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px">
                                <option value="normal" <?php echo ($printer['status'] == 'normal' ? 'selected' : ''); ?>>ปกติ (พร้อมใช้)</option>
                                <option value="repairing" <?php echo ($printer['status'] == 'repairing' ? 'selected' : ''); ?>>กำลังซ่อม (ยังไม่เสร็จ)</option>
                                <option value="broken" <?php echo ($printer['status'] == 'broken' ? 'selected' : ''); ?>>พัง (ปิดใช้งาน)</option>
                                <option value="retired" <?php echo ($printer['status'] == 'retired' ? 'selected' : ''); ?>>จำหน่ายออก (Retired)</option>
                            </select>
                        </div>
                    </div>
                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 50px; font-size: 1.1rem; background: var(--warning)">
                        บันทึกประวัติการซ่อม
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal สำหรับแทงจำหน่าย (Write-off) -->
    <div id="retire-printer-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--border);">
                <h3 style="color: #dc2626;">⛔ แทงจำหน่ายปริ้นเตอร์</h3>
                <button type="button" class="close-retire-btn" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
            </div>
            <form id="retire-printer-form">
                <input type="hidden" name="printer_id" value="<?php echo $printer['id']; ?>">
                <div class="modal-body" style="padding: 24px">
                    <div style="text-align:center; margin-bottom: 20px;">
                        <div style="font-size:3rem; margin-bottom:10px;">🗑️</div>
                        <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5;">
                            การแทงจำหน่าย (Write-off) จะเปลี่ยนสถานะเครื่องเป็น <strong style="color:#0f172a;">"จำหน่ายออก"</strong><br>และเก็บประวัติไว้ว่าไม่สามารถใช้งานได้อีก
                        </p>
                    </div>
                    <div style="margin-bottom: 20px">
                        <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">สาเหตุที่แทงจำหน่าย</label>
                        <textarea name="reason" rows="3" required placeholder="ระบุสาเหตุที่แทงจำหน่าย เช่น บอร์ดพังซ่อมไม่คุ้ม, หมดอายุการใช้งาน..." style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit; resize: vertical;"></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 50px; font-size: 1.1rem; background: #dc2626; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);">
                        ยืนยันการแทงจำหน่าย
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal สำหรับแก้ไขปริ้นเตอร์ -->
    <div id="edit-printer-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>แก้ไขข้อมูลปริ้นเตอร์ ✏️</h3>
                <button type="button" class="close-edit-btn" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
            </div>
            <form id="edit-printer-form" style="display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden;">
                <input type="hidden" name="printer_id" value="<?php echo $printer['id']; ?>">
                <div class="modal-body" style="padding: 30px">
                    <div style="margin-bottom: 20px">
                        <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">ยี่ห้อ (Brand)</label>
                        <?php 
                        $is_other_brand = !in_array($printer['brand'], ['Epson','Canon','Cannon','Brother','HP']);
                        ?>
                        <select id="edit-brand-select" <?php echo $is_other_brand ? '' : 'name="brand"'; ?> required style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit; background: white; cursor: pointer;" onchange="
                            if(this.value === 'อื่นๆ') {
                                this.removeAttribute('name');
                                document.getElementById('edit-brand-input').setAttribute('name', 'brand');
                                document.getElementById('edit-brand-input').style.display = 'block';
                                document.getElementById('edit-brand-input').required = true;
                                document.getElementById('edit-brand-input').focus();
                            } else {
                                this.setAttribute('name', 'brand');
                                document.getElementById('edit-brand-input').removeAttribute('name');
                                document.getElementById('edit-brand-input').style.display = 'none';
                                document.getElementById('edit-brand-input').required = false;
                            }
                        ">
                            <option value="Epson" <?php echo $printer['brand']=='Epson'?'selected':''; ?>>Epson</option>
                            <option value="Canon" <?php echo ($printer['brand']=='Canon' || $printer['brand']=='Cannon')?'selected':''; ?>>Canon</option>
                            <option value="Brother" <?php echo $printer['brand']=='Brother'?'selected':''; ?>>Brother</option>
                            <option value="HP" <?php echo $printer['brand']=='HP'?'selected':''; ?>>HP</option>
                            <option value="อื่นๆ" <?php echo $is_other_brand?'selected':''; ?>>อื่นๆ (โปรดระบุ)</option>
                        </select>
                        <input type="text" id="edit-brand-input" <?php echo $is_other_brand ? 'name="brand"' : ''; ?> value="<?php echo htmlspecialchars($printer['brand']); ?>" placeholder="โปรดระบุยี่ห้อ..." style="display:<?php echo $is_other_brand ? 'block' : 'none'; ?>; width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit; margin-top: 10px;" <?php echo $is_other_brand ? 'required' : ''; ?>>
                    </div>
                    <div style="margin-bottom: 20px">
                        <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">รุ่น (Model)</label>
                        <input type="text" name="model" value="<?php echo htmlspecialchars($printer['model']); ?>" required style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit">
                    </div>
                    <div style="margin-bottom: 20px">
                        <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">เลขเครื่อง (Serial Number)</label>
                        <input type="text" name="serial_number" value="<?php echo htmlspecialchars($printer['serial_number']); ?>" placeholder="กรอกเลขรหัสประจำเครื่อง" required style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit">
                    </div>
                    <div style="margin-bottom: 20px; position: relative;">
                        <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">หน่วยงาน/กลุ่มงาน</label>
                        <div class="searchable-select" id="edit-dept-wrapper">
                            <input type="text" id="edit-dept-search" placeholder="พิมพ์เพื่อค้นหาหน่วยงาน..." autocomplete="off"
                                value="<?php echo htmlspecialchars($printer['department']); ?>"
                                style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit">
                            <input type="hidden" name="department" id="edit-dept-value" value="<?php echo htmlspecialchars($printer['department']); ?>">
                            <div class="select-dropdown" id="edit-dept-dropdown" style="display:none; position:absolute; left:0; right:0; top:calc(100% + 4px); background:white; border:1px solid var(--border); border-radius:14px; max-height:240px; overflow-y:auto; z-index:9999; box-shadow:0 8px 24px rgba(0,0,0,0.1);">
                                <?php
                                $stmtDept = $pdo->query("SELECT group_name, sub_name FROM departments ORDER BY group_name ASC, sub_name ASC");
                                $rowsDept = $stmtDept->fetchAll();
                                $editDeptGroups = [];
                                foreach ($rowsDept as $r) {
                                    $grp = $r['group_name'];
                                    if (!isset($editDeptGroups[$grp])) $editDeptGroups[$grp] = [];
                                    if (!empty($r['sub_name'])) {
                                        $editDeptGroups[$grp][] = $r['sub_name'];
                                    }
                                }
                                foreach ($editDeptGroups as $gName => $subs): ?>
                                    <div class="select-item select-group-header" data-value="<?= htmlspecialchars($gName) ?>"
                                        style="font-weight:700; color:var(--primary-dark); background:rgba(99,102,241,0.05); padding:10px 14px; font-size:0.88rem; border-bottom:1px solid var(--border); cursor:pointer;">
                                        🏢 <?= htmlspecialchars($gName) ?>
                                    </div>
                                    <?php foreach ($subs as $sub): ?>
                                        <div class="select-item" data-value="<?= htmlspecialchars($sub) ?>" style="padding-left:32px; font-size:0.88rem; padding:10px 14px 10px 32px; cursor:pointer;">
                                            └ <?= htmlspecialchars($sub) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <div class="select-item" data-value="ไม่ทราบ" style="margin-top:4px; border-top:1px solid var(--border); color:var(--text-muted); padding:10px 14px; cursor:pointer;">❓ ไม่ทราบ</div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px">
                        <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">สถานที่วางเครื่อง (Location)</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($printer['location'] ?? ''); ?>" placeholder="เช่น ชั้น 2 ห้องการเงิน" style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit">
                    </div>

                    <!-- ปี พ.ศ. -->
                    <div style="margin-bottom: 20px;">
                        <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">ปี พ.ศ. <span style="color:var(--text-muted);font-weight:400;font-size:0.82rem">(ที่ได้รับ)</span></label>
                        <input type="text" name="year_be"
                            value="<?php echo htmlspecialchars($printer['year_be'] ?? ''); ?>"
                            placeholder="<?php echo (date('Y') + 543); ?>" maxlength="4"
                            style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit; font-size:1rem">
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 56px; font-size: 1.1rem">
                        บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 2 (แดชบอร์ด + ประวัติเบิกหมึก) -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <!-- Card สถิติและประวัติ -->
        <div class="card" style="margin-bottom: 0;">
            <h3>แดชบอร์ดสรุป</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 20px">
                <div style="text-align: center; padding: 16px; background: rgba(99, 102, 241, 0.05); border-radius: 16px">
                    <p style="font-size: 0.8rem; color: var(--text-muted)">เบิกหมึกไปแล้ว</p>
                    <h2 style="color: var(--primary)"><?php echo $inkCount; ?> <span style="font-size: 0.9rem; font-weight: 400">ครั้ง</span></h2>
                </div>
                <div style="text-align: center; padding: 16px; background: rgba(245, 158, 11, 0.05); border-radius: 16px">
                    <p style="font-size: 0.8rem; color: var(--text-muted)">ซ่อมบำรุงไปแล้ว</p>
                    <h2 style="color: var(--warning)"><?php echo $repairCount; ?> <span style="font-size: 0.9rem; font-weight: 400">ครั้ง</span></h2>
                </div>
            </div>
        </div>

        <!-- Card ประวัติเบิกหมึก -->
        <div class="card" style="margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px">
                <h3>ประวัติการเบิกหมึก</h3>
                <a href="#ink" onclick="if(window.loadPage) { window.loadPage('ink'); return false; }" style="font-size: 0.8rem; color: var(--primary)">ดูคลังหมึก</a>
            </div>
            <?php if (empty($ink_history)): ?>
                <p style="text-align: center; padding: 20px; color: var(--text-muted)">ไม่มีประวัติการเบิก</p>
            <?php else: ?>
                <div class="history-list" style="max-height: 280px; overflow-y: auto; padding-right: 8px;">
                    <?php foreach ($ink_history as $h): ?>
                        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border)">
                            <div>
                                <p style="font-weight: 600; font-size: 0.9rem"><?php echo htmlspecialchars($h['ink_name']); ?></p>
                                <p style="font-size: 0.75rem; color: var(--text-muted)"><?php echo thdatetime($h['transaction_date']); ?></p>
                            </div>
                            <div style="font-weight: 700; color: var(--accent)">-<?php echo $h['quantity']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Column 3 (ประวัติซ่อม + ประวัติย้าย) -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <!-- Card ประวัติการซ่อม -->
        <div class="card" style="margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px">
                <h3>ประวัติการซ่อมบำรุง</h3>
                <a href="#maintenance" onclick="if(window.loadPage) { window.loadPage('maintenance'); return false; }" style="font-size: 0.8rem; color: var(--primary)">ดูประวัติทั้งหมด</a>
            </div>
            <?php if (empty($maintenance_history)): ?>
                <p style="text-align: center; padding: 20px; color: var(--text-muted)">ไม่มีประวัติการซ่อม</p>
            <?php else: ?>
                <div class="history-list" style="max-height: 280px; overflow-y: auto; padding-right: 8px;">
                    <?php foreach ($maintenance_history as $m): ?>
                        <div style="padding: 12px 0; border-bottom: 1px solid var(--border)">
                            <p style="font-weight: 600; font-size: 0.9rem"><?php echo htmlspecialchars($m['symptoms']); ?></p>
                            <p style="font-size: 0.75rem; color: var(--text-muted)">
                                <?php echo thdate($m['repair_date']); ?> | ช่าง: <?php echo htmlspecialchars($m['technician_name']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Card ประวัติการย้ายแผนก/สถานที่ -->
        <div class="card" style="margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px">
                <h3>ประวัติการย้ายแผนก/สถานที่</h3>
            </div>
            <?php if (empty($movement_history)): ?>
                <p style="text-align: center; padding: 20px; color: var(--text-muted)">ไม่มีประวัติการย้าย</p>
            <?php else: ?>
                <div class="history-list" style="max-height: 280px; overflow-y: auto; padding-right: 8px;">
                    <?php foreach ($movement_history as $move): ?>
                        <div style="padding: 12px 0; border-bottom: 1px solid var(--border)">
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">
                                <?php echo thdatetime($move['moved_at']); ?>
                            </p>
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <?php if ($move['old_department'] !== $move['new_department']): ?>
                                    <div style="font-size:0.9rem;">
                                        🏢 <span style="text-decoration:line-through; color:var(--danger)"><?php echo htmlspecialchars($move['old_department'] ?: 'ไม่ระบุ'); ?></span>
                                        ➔ <strong style="color:var(--success)"><?php echo htmlspecialchars($move['new_department'] ?: 'ไม่ระบุ'); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($move['old_location'] !== $move['new_location']): ?>
                                    <div style="font-size:0.9rem;">
                                        📍 <span style="text-decoration:line-through; color:var(--danger)"><?php echo htmlspecialchars($move['old_location'] ?: 'ไม่ระบุ'); ?></span>
                                        ➔ <strong style="color:var(--success)"><?php echo htmlspecialchars($move['new_location'] ?: 'ไม่ระบุ'); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== Print Label (hidden, shown only on print) ===== -->
<div id="print-label-area" style="display:none">
    <div class="print-label">
        <div class="print-qr-zone">
            <div id="print-qr-canvas"></div>
        </div>
        <div class="print-info-zone">
            <div class="print-info-row">
                <span class="print-label-key">ยี่ห้อ/รุ่น:</span>
                <span class="print-label-val"><?php echo htmlspecialchars($printer['brand'].' '.$printer['model']); ?></span>
            </div>
            <div class="print-info-row">
                <span class="print-label-key">S/N:</span>
                <span class="print-label-val"><?php echo htmlspecialchars($printer['serial_number']); ?></span>
            </div>
            <div class="print-info-row">
                <span class="print-label-key">หน่วยงาน:</span>
                <span class="print-label-val"><?php echo htmlspecialchars($printer['department']); ?></span>
            </div>
            <div class="print-info-row">
                <span class="print-label-key">ID:</span>
                <span class="print-label-val print-qr-id"><?php echo htmlspecialchars($printer['qr_code_id']); ?></span>
            </div>
        </div>
    </div>
</div>

<script>
    // สร้าง QR Code ขนาดใหญ่ในหน้ารายละเอียด (Full URL)
    var baseUrl = window.location.origin + window.location.pathname;
    if (!window.location.hostname.includes('localhost') && !window.location.hostname.includes('127.0.0.1')) {
        baseUrl = baseUrl.replace(/^http:\/\//i, 'https://');
    }
    new QRCode(document.getElementById("detail-qr"), {
        text: baseUrl + "#printer_details?qr_id=<?php echo $printer['qr_code_id']; ?>",
        width: 150,
        height: 150,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.L
    });

    // ฟังก์ชันพิมพ์ฉลาก QR (Native Canvas Rendering)
    function printLabel() {
        const brand  = <?php echo json_encode($printer['brand'].' '.$printer['model']); ?>;
        const dept   = <?php echo json_encode($printer['department']); ?>;
        const yearBE = <?php echo json_encode(!empty($printer['year_be']) ? $printer['year_be'] : '—'); ?>;
        const sn     = <?php echo json_encode($printer['serial_number']); ?>;
        const qrText = "<?php echo addslashes($printer['qr_code_id']); ?>";
        const locationStr = <?php echo json_encode(!empty($printer['location']) ? $printer['location'] : 'ไม่ระบุ'); ?>;

        // สร้าง Modal เปล่าๆ ขึ้นมารอ
        let modal = document.getElementById('label-export-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'label-export-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 480px; text-align: center; padding: 0;">
                    <div class="modal-header">
                        <h3>🖨️ ฉลากปริ้นเตอร์</h3>
                        <button onclick="document.getElementById('label-export-modal').style.display='none'" class="close-modal-btn">✕</button>
                    </div>
                    <div class="modal-body" style="padding: 24px; background: #f8fafc;">
                        <p style="margin-bottom:15px; font-size:14px; color:#475569;">
                            <b>สำหรับมือถือ:</b> แตะค้างที่รูปภาพด้านล่างแล้วเลือก<br>
                            "บันทึก (Save Image)" หรือ "แชร์ (Share)"<br>ไปยังแอป <b>Brother iPrint&Label</b>
                        </p>
                        <img id="label-preview-img" src="" style="max-width: 100%; border: 1px dashed #94a3b8; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: block; margin: 0 auto;">
                        <div style="margin-top: 24px; display: flex; gap: 10px; justify-content: center;">
                            <button onclick="printLabelFromImage()" class="btn-primary" style="flex: 1; justify-content: center; background: #22c55e;"><span style="font-size: 1.2rem;">🖨️</span> พิมพ์ (คอม)</button>
                            <button onclick="document.getElementById('label-export-modal').style.display='none'" class="btn-secondary" style="flex: 1; justify-content: center; color: var(--danger); border-color: var(--danger);">ปิด</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // สร้าง QR Data URL ก่อนจาก canvas ชั่วคราว
        const tempDiv = document.createElement('div');
        tempDiv.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
        document.body.appendChild(tempDiv);
        
        let fullQrUrl = window.location.origin + window.location.pathname + '#printer_details?qr_id=' + qrText;
        if (!window.location.hostname.includes('localhost') && !window.location.hostname.includes('127.0.0.1')) {
            fullQrUrl = fullQrUrl.replace(/^http:\/\//i, 'https://');
        }
        
        new QRCode(tempDiv, {
            text: fullQrUrl,
            width: 182, height: 182,
            colorDark: '#000000', colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.L // ใช้ L เพื่อให้จุดมันใหญ่ที่สุดเท่าที่จะทำได้สำหรับลิงก์ยาวๆ
        });

        setTimeout(() => {
            const qrCanvas = tempDiv.querySelector('canvas');
            const qrDataUrl = qrCanvas ? qrCanvas.toDataURL('image/png') : '';
            document.body.removeChild(tempDiv);

            // ใช้ Canvas วาดฉลากขนาด 650x212 pixels (เทียบเท่า 55mm x 18mm ที่ ~300 DPI)
            const cvs = document.createElement('canvas');
            cvs.width = 650;
            cvs.height = 212;
            const ctx = cvs.getContext('2d');

            // พื้นหลังสีขาว
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, cvs.width, cvs.height);
            
            // ปิด anti-aliasing (การทำขอบเนียน) เพื่อให้ QR โค้ดคมกริบ ไม่เบลอเวลาปรินต์
            ctx.imageSmoothingEnabled = false;

            // โหลด QR Code ลงมาวาด
            const qrImg = new Image();
            qrImg.onload = () => {
                // วาด QR ฝั่งซ้าย
                ctx.drawImage(qrImg, 15, 15, 182, 182);

                // ตั้งค่าฟอนต์
                ctx.fillStyle = '#000000';
                ctx.textBaseline = 'top';

                // วาดข้อความ หน่วยงาน (ปรับขนาดอัตโนมัติตามความยาว)
                let deptFontSize = 32;
                ctx.font = 'bold ' + deptFontSize + 'px Tahoma, Arial, sans-serif';
                while (ctx.measureText('หน่วยงาน: ' + dept).width > 420 && deptFontSize > 20) {
                    deptFontSize -= 2;
                    ctx.font = 'bold ' + deptFontSize + 'px Tahoma, Arial, sans-serif';
                }
                ctx.fillText('หน่วยงาน: ' + dept, 215, 15 + (32 - deptFontSize) / 2, 420);

                // S/N และ ปี
                ctx.font = 'bold 26px Tahoma, Arial, sans-serif';
                ctx.fillText('S/N: ' + sn, 215, 65, 275);
                ctx.fillText('ปี: ' + yearBE, 500, 65, 135);

                // รุ่น (ปรับขนาดอัตโนมัติตามความยาว)
                let brandFontSize = 26;
                ctx.font = 'bold ' + brandFontSize + 'px Tahoma, Arial, sans-serif';
                while (ctx.measureText('รุ่น: ' + brand).width > 420 && brandFontSize > 18) {
                    brandFontSize -= 2;
                    ctx.font = 'bold ' + brandFontSize + 'px Tahoma, Arial, sans-serif';
                }
                ctx.fillText('รุ่น: ' + brand, 215, 115 + (26 - brandFontSize) / 2, 420);

                // สถานที่วาง (บรรทัดใหม่)
                let locFontSize = 26;
                ctx.font = 'bold ' + locFontSize + 'px Tahoma, Arial, sans-serif';
                while (ctx.measureText('ที่วาง: ' + locationStr).width > 420 && locFontSize > 18) {
                    locFontSize -= 2;
                    ctx.font = 'bold ' + locFontSize + 'px Tahoma, Arial, sans-serif';
                }
                ctx.fillText('ที่วาง: ' + locationStr, 215, 165 + (26 - locFontSize) / 2, 420);

                // นำรูปไปแสดงใน Modal
                const finalImgDataUrl = cvs.toDataURL('image/png');
                document.getElementById('label-preview-img').src = finalImgDataUrl;
                document.getElementById('label-export-modal').style.display = 'flex';
            };
            qrImg.src = qrDataUrl;
        }, 300);
    }

    // ฟังก์ชันสำหรับพิมพ์จากคอมพิวเตอร์
    window.printLabelFromImage = function() {
        const imgSrc = document.getElementById('label-preview-img').src;
        const win = window.open('', '_blank', 'width=420,height=320,menubar=no,toolbar=no');
        if (!win) {
            showToast('กรุณาอนุญาต Popup สำหรับเว็บไซต์นี้', 'warning');
            return;
        }
        win.document.write(`<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>พิมพ์ฉลาก</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  @page { size: 55mm 18mm; margin: 0; }
  html, body { width: 55mm; height: 18mm; overflow: hidden; background: #fff; }
  img { width: 55mm; height: 18mm; display: block; }
  .no-print { position: fixed; bottom: 0; left: 0; right: 0; background: #1e293b; padding: 8px; text-align: center; }
  .no-print button { padding: 6px 18px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: bold; }
  .btn-print { background: #22c55e; color: #fff; margin-right: 8px; }
  .btn-close { background: #ef4444; color: #fff; }
  @media print { .no-print { display: none !important; } }
</style>
</head>
<body>
  <img src="${imgSrc}" alt="Label">
  <div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ พิมพ์เลย</button>
    <button class="btn-close" onclick="window.close()">✖ ปิด</button>
  </div>
</body>
</html>`);
        win.document.close();
    };

    // ส่วนจัดการเบิกหมึก
    (function() {
        const modal = document.getElementById('withdraw-modal');
        if (!modal) return; // guard: ป้องกันกรณีโหลดหน้าอื่นที่ไม่มี element นี้
        const btn = document.getElementById('withdraw-btn');
        const closeBtn = modal.querySelector('.close-withdraw-btn');
        const form = document.getElementById('withdraw-form');
        const barcodeInput = document.getElementById('withdraw-barcode-input');
        const infoCard = document.getElementById('withdraw-ink-info');
        const notFound = document.getElementById('withdraw-not-found');
        const submitBtn = document.getElementById('withdraw-submit-btn');

        // เปิด Modal และรีเซ็ตค่า
        if(btn) {
            btn.onclick = () => {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                resetWithdraw();
                setTimeout(() => barcodeInput && barcodeInput.focus(), 300);
            };
        }
        const closeWithdraw = () => { modal.style.display = 'none'; document.body.style.overflow = ''; };
        if(closeBtn) closeBtn.onclick = closeWithdraw;
        modal.addEventListener('click', (e) => { if (e.target === modal) closeWithdraw(); });

        function resetWithdraw() {
            barcodeInput.value = '';
            document.getElementById('withdraw-ink-id').value = '';
            infoCard.style.display = 'none';
            notFound.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
        }

        // ค้นหาหมึกจากบาร์โค้ด
        async function lookupBarcode(barcode) {
            if (!barcode.trim()) return;
            infoCard.style.display = 'none';
            notFound.style.display = 'none';
            try {
                const res = await fetch(`api.php?action=get_ink_details&barcode=${encodeURIComponent(barcode)}`);
                const result = await res.json();
                if (result.success && result.exists) {
                    const ink = result.data;
                    document.getElementById('withdraw-ink-id').value = ink.id;
                    document.getElementById('withdraw-ink-name').textContent = ink.name;
                    document.getElementById('withdraw-ink-brand').textContent = ink.brand || 'ไม่ระบุยี่ห้อ';
                    document.getElementById('withdraw-ink-qty').textContent = ink.current_quantity + ' กล่อง';
                    infoCard.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                } else {
                    notFound.style.display = 'block';
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                }
            } catch(e) { console.error(e); }
        }

        // กด Enter ในช่องบาร์โค้ด → ค้นหาทันที
        if(barcodeInput) {
            barcodeInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); lookupBarcode(barcodeInput.value); }
            });
            // รองรับบาร์โค้ดสแกนเนอร์ที่ส่ง input เร็ว (auto-lookup หลังหยุดพิมพ์ 500ms)
            let debounceTimer;
            barcodeInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    if (barcodeInput.value.length >= 4) lookupBarcode(barcodeInput.value);
                }, 500);
            });
        }

        // รองรับ QR Scanner ที่ใช้งานในแอปหลัก
        window.onBarcodeScanned = (barcode) => {
            if (modal.style.display === 'block') {
                barcodeInput.value = barcode;
                lookupBarcode(barcode);
            }
        };

        form.onsubmit = async (e) => {
            e.preventDefault();
            if (!document.getElementById('withdraw-ink-id').value) {
                showToast('กรุณาแสกนบาร์โค้ดหมึกก่อน', 'warning', 'ยังไม่ได้เลือกหมึก');
                return;
            }
            const formData = new FormData(form);
            try {
                const response = await fetch('api.php?action=withdraw_ink', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) {
                    showToast('เบิกหมึกเรียบร้อยแล้ว', 'success');
                    modal.style.display = 'none';
                    if(window.loadPage) window.loadPage('printer_details?qr_id=<?php echo htmlspecialchars($qr_id); ?>'); else location.reload();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) { console.error(error); }
        };
    })();

    // ส่วนจัดการแจ้งซ่อม
    (function() {
        const modal = document.getElementById('maintenance-modal');
        if (!modal) return;
        const btn = document.getElementById('maintenance-btn');
        const closeBtn = modal.querySelector('.close-maintenance-btn');
        const form = document.getElementById('maintenance-form');

        // Flatpickr Initialization — เก็บ instance ไว้ใน window เพื่อ destroy ได้เมื่อ navigate
        if (typeof flatpickr !== 'undefined') {
            const dateEl = document.getElementById('repair_date_picker');
            const timeEl = document.getElementById('repair_time_picker');
            if (dateEl) {
                if (window._fpDateInstance) { try { window._fpDateInstance.destroy(); } catch(e) {} }
                window._fpDateInstance = flatpickr(dateEl, {
                    disableMobile: true
                });
            }
            if (timeEl) {
                if (window._fpTimeInstance) { try { window._fpTimeInstance.destroy(); } catch(e) {} }
                window._fpTimeInstance = flatpickr(timeEl, {
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: "H:i",
                    time_24hr: true,
                    disableMobile: true
                });
            }
        }

        if(btn) btn.onclick = () => { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; };
        const closeMaint = () => { modal.style.display = 'none'; document.body.style.overflow = ''; };
        if(closeBtn) closeBtn.onclick = closeMaint;
        modal.addEventListener('click', (e) => { if (e.target === modal) closeMaint(); });

        form.onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            try {
                const response = await fetch('api.php?action=add_maintenance_log', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if(result.success) {
                    showToast('บันทึกประวัติการซ่อมเรียบร้อยแล้ว', 'success');
                    if(window.loadPage) window.loadPage('printer_details?qr_id=<?php echo htmlspecialchars($qr_id); ?>'); else location.reload();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) { console.error(error); }
        };
    })();

    // ส่วนจัดการแก้ไข, แทงจำหน่าย และลบ
    (function() {
        const retireModal = document.getElementById('retire-printer-modal');
        const retireBtn = document.getElementById('retire-printer-btn');
        if (retireModal && retireBtn) {
            const closeRetireBtn = retireModal.querySelector('.close-retire-btn');
            const retireForm = document.getElementById('retire-printer-form');
            
            retireBtn.onclick = () => { retireModal.style.display = 'block'; document.body.style.overflow = 'hidden'; };
            closeRetireBtn.onclick = () => { retireModal.style.display = 'none'; document.body.style.overflow = ''; };
            retireModal.addEventListener('click', (e) => { if (e.target === retireModal) closeRetireBtn.onclick(); });

            retireForm.onsubmit = async (e) => {
                e.preventDefault();
                const confirmed = await window.showConfirm({
                    title: 'ยืนยันแทงจำหน่าย?',
                    message: 'คุณแน่ใจหรือไม่ที่จะแทงจำหน่ายปริ้นเตอร์เครื่องนี้?',
                    confirmText: 'ยืนยัน',
                    type: 'danger'
                });
                if (!confirmed) return;

                const formData = new FormData(retireForm);
                try {
                    const response = await fetch('api.php?action=retire_printer', { method: 'POST', body: formData });
                    const result = await response.json();
                    if(result.success) {
                        showToast(result.message, 'success');
                        if(window.loadPage) window.loadPage('printer_details?qr_id=<?php echo htmlspecialchars($qr_id); ?>'); else location.reload();
                    } else {
                        showToast(result.message, 'error');
                    }
                } catch (error) { console.error(error); }
            };
        }

        const editModal = document.getElementById('edit-printer-modal');
        if (!editModal) return;
        const editBtn = document.getElementById('edit-printer-btn');
        const closeEditBtn = editModal.querySelector('.close-edit-btn');
        const editForm = document.getElementById('edit-printer-form');

        if(editBtn) editBtn.onclick = () => { editModal.style.display = 'block'; document.body.style.overflow = 'hidden'; };
        if(closeEditBtn) closeEditBtn.onclick = () => { editModal.style.display = 'none'; document.body.style.overflow = ''; };
        // ปิดเมื่อคลิก backdrop
        editModal.addEventListener('click', (e) => { if (e.target === editModal) { editModal.style.display = 'none'; document.body.style.overflow = ''; } });

        // === Searchable dept dropdown สำหรับ Edit Modal ===
        const editDeptSearch   = document.getElementById('edit-dept-search');
        const editDeptValue    = document.getElementById('edit-dept-value');
        const editDeptDropdown = document.getElementById('edit-dept-dropdown');
        if (editDeptSearch && editDeptDropdown) {
            const editItems = editDeptDropdown.querySelectorAll('.select-item');

            editDeptSearch.onfocus = () => {
                editItems.forEach(item => {
                    if (item.classList.contains('select-group-header')) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
                editDeptDropdown.style.display = 'block';
            };
            editDeptSearch.oninput = (e) => {
                const val = e.target.value.toLowerCase().trim();
                editDeptValue.value = '';

                if (!val) {
                    editItems.forEach(item => {
                        item.style.display = item.classList.contains('select-group-header') ? '' : 'none';
                    });
                    editDeptDropdown.style.display = 'block';
                    return;
                }

                let hasMatch = false;
                const matchedHeaders = new Set();
                editItems.forEach(item => {
                    if (item.classList.contains('select-group-header') && item.textContent.toLowerCase().includes(val)) {
                        matchedHeaders.add(item);
                    }
                });
                let currentHeader = null;
                editItems.forEach(item => {
                    if (item.classList.contains('select-group-header')) currentHeader = item;
                    const selfMatch = item.textContent.toLowerCase().includes(val);
                    const parentMatch = currentHeader && matchedHeaders.has(currentHeader) && !item.classList.contains('select-group-header');
                    if (selfMatch || parentMatch) {
                        item.style.display = '';
                        hasMatch = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
                editDeptDropdown.style.display = hasMatch ? 'block' : 'none';
            };
            editItems.forEach(item => {
                item.addEventListener('mouseenter', () => { item.style.background = 'rgba(99,102,241,0.07)'; });
                item.addEventListener('mouseleave', () => { item.style.background = item.classList.contains('select-group-header') ? 'rgba(99,102,241,0.05)' : ''; });
                item.onclick = () => {
                    const val = item.getAttribute('data-value');
                    editDeptSearch.value = val;
                    editDeptValue.value = val;
                    editDeptDropdown.style.display = 'none';
                };
            });
            document.addEventListener('click', (e) => {
                // ทำงานเฉพาะตอนที่ edit modal เปิดอยู่เท่านั้น
                if (editModal.style.display !== 'block') return;
                if (!e.target.closest('#edit-dept-wrapper')) {
                    editDeptDropdown.style.display = 'none';
                }
            });
        }

        if(editForm) {
            editForm.onsubmit = async (e) => {
                e.preventDefault();
                const saveBtn = editForm.querySelector('[type="submit"]');
                if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'กำลังบันทึก...'; }
                const formData = new FormData(editForm);
                try {
                    const response = await fetch('api.php?action=edit_printer', { method: 'POST', body: formData });
                    const result = await response.json();
                    if(result.success) {
                        showToast('บันทึกการแก้ไขเรียบร้อยแล้ว', 'success');
                        editModal.style.display = 'none';
                        document.body.style.overflow = '';
                        if(window.loadPage) window.loadPage('printer_details?qr_id=<?php echo htmlspecialchars($qr_id); ?>'); else location.reload();
                    } else {
                        showToast(result.message, 'error');
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'บันทึกการแก้ไข'; }
                    }
                } catch (error) { console.error(error); if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'บันทึกการแก้ไข'; } }
            };
        }
    })();

    // ฟังก์ชันลบปริ้นเตอร์แบบ Global เพื่อให้เรียกใช้ได้เสมอ
    window.deletePrinter = async () => {
        const confirmed = await window.showConfirm({
            title: 'ย้ายไปถังขยะ?',
            message: 'ข้อมูลปริ้นเตอร์จะถูกเก็บไว้ 30 วันก่อนถูกลบถาวร',
            confirmText: '🗑️ ย้ายไปถังขยะ',
            cancelText: 'ยกเลิก',
            type: 'danger'
        });
        
        if (confirmed) {
            // ป้องกันผู้ใช้กดซ้ำหรือกดกลับก่อนเสร็จ
            const btn = document.getElementById('delete-printer-btn');
            if(btn) {
                btn.disabled = true;
                btn.innerHTML = '⏳ กำลังลบ...';
            }
            showToast('⏳ กำลังย้ายไปถังขยะ กรุณารอสักครู่...', 'info', 'กำลังประมวลผล', 2000);
            
            try {
                const formData = new FormData();
                formData.append('printer_id', '<?php echo $printer['id']; ?>');
                
                const response = await fetch('api.php?action=delete_printer', { 
                    method: 'POST', 
                    body: formData
                });
                const result = await response.json();
                
                if(result.success) {
                    showToast('ย้ายปริ้นเตอร์ไปถังขยะแล้ว', 'success', 'ลบข้อมูลสำเร็จ');
                    // เปลี่ยน URL และบังคับโหลดหน้าจอใหม่เสมอ
                    window.location.hash = 'printers';
                    if (typeof window.loadPage === 'function') {
                        setTimeout(() => window.loadPage('printers'), 100);
                    } else {
                        location.reload();
                    }
                } else {
                    showToast(result.message, 'error');
                    if(btn) {
                        btn.disabled = false;
                        btn.innerHTML = '🗑️';
                    }
                }
            } catch (error) { 
                console.error(error);
                showToast('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error');
                if(btn) {
                    btn.disabled = false;
                    btn.innerHTML = '🗑️';
                }
            }
        }
    };
</script>

<!-- ===== Photo Manager (reused in details page) ===== -->
<div id="detail-photo-modal" class="modal">
    <div class="modal-content" style="max-width: 600px">
        <div class="modal-header">
            <div>
                <h3 style="margin:0; font-size:1.1rem">📷 จัดการรูปภาพ</h3>
                <div id="dpm-name" style="font-size:0.82rem; color:var(--text-muted); margin-top:2px"></div>
            </div>
            <button onclick="closeDetailPhotoManager()" class="close-modal-btn">&times;</button>
        </div>
        <div class="modal-body">
            <label for="dpm-file" id="dpm-zone"
                style="display:flex; flex-direction:column; align-items:center; gap:6px; border:2px dashed var(--border); border-radius:16px; padding:20px; cursor:pointer; background:#fafafa; margin-bottom:16px; transition:all 0.2s">
                <span style="font-size:1.8rem">📤</span>
                <span style="font-size:0.85rem; font-weight:600; color:var(--primary)">เลือกหรือลากรูปมาวาง</span>
                <span style="font-size:0.72rem; color:var(--text-muted)">JPG, PNG, WEBP</span>
            </label>
            <input type="file" id="dpm-file" accept="image/*" multiple style="display:none">
            <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:12px">🖱 ลากเรียงลำดับ — รูปแรก <b style="color:var(--primary)">= หน้าปก</b></div>
            <div id="dpm-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); gap:10px; min-height:50px"></div>
            <div id="dpm-uploading" style="display:none; text-align:center; padding:12px; color:var(--primary); font-weight:600; font-size: 0.9rem">⏳ กำลังอัปโหลด...</div>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div id="detail-lightbox" onclick="this.style.display='none'" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.88); z-index:10000; align-items:center; justify-content:center; cursor:zoom-out">
    <img id="detail-lightbox-img" style="max-width:92%; max-height:92vh; border-radius:16px; object-fit:contain; box-shadow:0 8px 40px rgba(0,0,0,0.5)" src="" alt="">
</div>

<script>
(function() {
    // ===== Photo Manager (printer_details) — window scope เพื่อกัน SPA re-inject =====
    window._dpmId  = window._dpmId  || null;
    window._dpmSrc = window._dpmSrc || null;

    window.openDetailPhotoManager = function(id, name) {
        window._dpmId = id;
        document.getElementById('dpm-name').textContent = name;
        document.getElementById('detail-photo-modal').style.display = 'flex';
        window._dpmLoad();
    };
    window.closeDetailPhotoManager = function() {
        document.getElementById('detail-photo-modal').style.display = 'none';
        if (window.loadPage) window.loadPage(location.hash.replace('#','') || 'printer_details?qr_id=<?= $printer['qr_code_id'] ?>');
        else location.reload();
    };
    window.openDetailLightbox = function(url) {
        const lb = document.getElementById('detail-lightbox');
        document.getElementById('detail-lightbox-img').src = url + '?t=' + Date.now();
        lb.style.display = 'flex';
    };
    window._dpmLoad = async function() {
        const grid = document.getElementById('dpm-grid');
        if (!grid) return;
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:16px">⏳ โหลด...</div>';
        try {
            const res = await fetch(`api.php?action=get_printer_images&printer_id=${window._dpmId}`);
            const raw = await res.text();
            let data;
            try { data = JSON.parse(raw); } catch(e) {
                grid.innerHTML = '<div style="grid-column:1/-1;padding:12px;color:red">❌ Server Error — กรุณาลองใหม่</div>';
                return;
            }
            window._dpmRender(data.images || []);
        } catch(e) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--danger);padding:16px">เกิดข้อผิดพลาด</div>';
        }
    };
    window._dpmRender = function(images) {
        const grid = document.getElementById('dpm-grid');
        if (!grid) return;
        grid.innerHTML = '';
        if (!images.length) { grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:24px">ยังไม่มีรูป</div>'; return; }
        images.forEach((img, idx) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'pm-wrapper';
            wrapper.style.position = 'relative';
            wrapper.style.aspectRatio = '1/1';
            wrapper.dataset.id = img.id;

            const el = document.createElement('div');
            el.className = 'pm-img-item';
            el.draggable = true;
            el.style.width = '100%';
            el.style.height = '100%';
            el.innerHTML = `<img src="${img.url}?t=${Date.now()}" alt=""> ${idx===0?'<div class="pm-badge-first">หน้าปก</div>':''}`;
            
            const btn = document.createElement('button');
            btn.type = 'button'; 
            btn.className = 'pm-del-btn';
            btn.innerHTML = '✕';
            btn.onclick = () => window._dpmDelete(img.id);

            wrapper.appendChild(el);
            wrapper.appendChild(btn);

            el.addEventListener('dragstart', function(e) { window._dpmSrc = wrapper; el.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', img.id); });
            el.addEventListener('dragenter', function(e) { e.preventDefault(); });
            el.addEventListener('dragover',  function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; document.querySelectorAll('.pm-img-item').forEach(x => x.classList.remove('drag-over')); el.classList.add('drag-over'); });
            el.addEventListener('dragend',   function(e) { document.querySelectorAll('.pm-img-item').forEach(x => { x.classList.remove('dragging'); x.classList.remove('drag-over'); }); });
            el.addEventListener('drop', function(e) {
                e.preventDefault(); e.stopPropagation();
                if (!window._dpmSrc || window._dpmSrc === wrapper) return;
                const its = [...grid.querySelectorAll('.pm-wrapper')];
                const si = its.indexOf(window._dpmSrc), di = its.indexOf(wrapper);
                if (si < di) grid.insertBefore(window._dpmSrc, wrapper.nextSibling); else grid.insertBefore(window._dpmSrc, wrapper);
                window._dpmSaveOrder();
            });
            grid.appendChild(wrapper);
        });
    };
    window._dpmSaveOrder = async function() {
        const wrappers = document.querySelectorAll('#dpm-grid .pm-wrapper');
        const order = [...wrappers].map((el, i) => ({ id: +el.dataset.id, sort_order: i }));
        wrappers.forEach((el, i) => { const old = el.querySelector('.pm-badge-first'); if (old) old.remove(); if (i === 0) { const b = document.createElement('div'); b.className = 'pm-badge-first'; b.textContent = 'หน้าปก'; el.querySelector('.pm-img-item').appendChild(b); } });
        const fd = new FormData(); fd.append('order', JSON.stringify(order));
        await fetch('api.php?action=reorder_printer_images', { method: 'POST', body: fd });
    };
    window._dpmDelete = async function(imgId) {
        if (typeof window.showConfirm !== 'function') {
            if (!document.getElementById('confirm-overlay')) {
                const div = document.createElement('div');
                div.innerHTML = `<div id="confirm-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); backdrop-filter:blur(8px); z-index:999998; align-items:center; justify-content:center; padding:20px;"><div id="confirm-box" style="background:white; border-radius:24px; padding:32px 28px; max-width:380px; width:100%; box-shadow:0 24px 60px rgba(0,0,0,0.22); text-align:center;"><div id="confirm-icon" style="font-size:2.8rem; margin-bottom:12px; line-height:1"></div><h3 id="confirm-title" style="margin:0 0 8px; font-size:1.1rem; color:#0f172a"></h3><p id="confirm-msg" style="margin:0 0 28px; font-size:0.9rem; color:#64748b; line-height:1.55"></p><div style="display:flex; gap:12px; justify-content:center"><button id="confirm-cancel" style="flex:1; padding:13px; border:1.5px solid #e2e8f0; border-radius:14px; background:white; font-size:0.9rem; font-weight:600; cursor:pointer; color:#64748b;">ยกเลิก</button><button id="confirm-ok" style="flex:1; padding:13px; border:none; border-radius:14px; font-size:0.9rem; font-weight:700; cursor:pointer; color:white;"></button></div></div></div>`;
                document.body.appendChild(div.firstElementChild);
            }
            window.showConfirm = function({ title='ยืนยัน?', message='', confirmText='ยืนยัน', cancelText='ยกเลิก', type='warning' } = {}) {
                return new Promise((resolve) => {
                    const overlay = document.getElementById('confirm-overlay');
                    if (!overlay) { resolve(confirm(title + '\\n' + message)); return; }
                    const icons   = { warning:'⚠️', danger:'🗑️', info:'💡', success:'✅' };
                    const colors  = { warning:'#f59e0b', danger:'#ef4444', info:'#6366f1', success:'#10b981' };
                    document.getElementById('confirm-icon').textContent  = icons[type]  || icons.warning;
                    document.getElementById('confirm-title').textContent = title;
                    document.getElementById('confirm-msg').textContent   = message;
                    const okBtn = document.getElementById('confirm-ok');
                    okBtn.textContent = confirmText;
                    okBtn.style.background = `linear-gradient(135deg, ${colors[type]||colors.warning}, ${colors[type]||colors.warning}cc)`;
                    overlay.style.display = 'flex';
                    const cleanup = (val) => { overlay.style.display = 'none'; okBtn.onclick = null; document.getElementById('confirm-cancel').onclick = null; resolve(val); };
                    okBtn.onclick = () => cleanup(true);
                    document.getElementById('confirm-cancel').onclick = () => cleanup(false);
                    overlay.onclick = (e) => { if (e.target === overlay) cleanup(false); };
                });
            };
        }

        const confirmed = await window.showConfirm({
            title: 'ลบรูปภาพ?',
            message: 'รูปนี้จะถูกลบถาวร ไม่สามารถกู้คืนได้',
            confirmText: '🗑️ ลบถาวร',
            cancelText: 'ยกเลิก',
            type: 'danger'
        });
        if (!confirmed) return;
        const fd = new FormData(); fd.append('img_id', imgId);
        const res = await fetch('api.php?action=delete_printer_image', { method: 'POST', body: fd });
        const d = await res.json();
        if (d.success) window._dpmLoad(); else showToast(d.message, 'error');
    };
    window._pmCompressImage = window._pmCompressImage || function(file) {
        return new Promise((resolve) => {
            if (!file.type.match(/image.*/)) return resolve(file);
            const img = new Image(); const objectUrl = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(objectUrl);
                let w = img.width, h = img.height; const max = 1200;
                if (w > max || h > max) { if (w > h) { h = Math.round(h * (max / w)); w = max; } else { w = Math.round(w * (max / h)); h = max; } }
                const canvas = document.createElement('canvas'); canvas.width = w; canvas.height = h;
                const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0, w, h);
                canvas.toBlob((blob) => {
                    if (blob) resolve(new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", { type: 'image/jpeg', lastModified: Date.now() }));
                    else resolve(file);
                }, 'image/jpeg', 0.85);
            };
            img.onerror = () => { URL.revokeObjectURL(objectUrl); resolve(file); };
            img.src = objectUrl;
        });
    };

    window._dpmUpload = async function(files) {
        const up = document.getElementById('dpm-uploading'); const errors = [];
        for (let f of files) {
            up.textContent = `⏳ กำลังย่อขนาดรูป: ${f.name}...`; up.style.display = 'block';
            try { f = await window._pmCompressImage(f); } catch(e) {}
            up.textContent = `⏳ อัปโหลด: ${f.name} (${(f.size/1024/1024).toFixed(1)} MB)...`;
            try {
                const fd = new FormData(); fd.append('image', f); fd.append('printer_id', window._dpmId);
                const res = await fetch('api.php?action=upload_printer_image', { method: 'POST', body: fd });
                const raw = await res.text(); let data;
                try { data = JSON.parse(raw); } catch(pe) { errors.push(`${f.name}: Server error — ${raw.substring(0,150)}`); continue; }
                if (!data.success) errors.push(`${f.name}: ${data.message}`);
            } catch(ne) { errors.push(`${f.name}: เชื่อมต่อไม่ได้`); }
        }
        up.style.display = 'none'; up.textContent = '⏳ กำลังอัปโหลด...';
        if (errors.length) showToast(errors.join(' | '), 'error', `อัปโหลดล้มเหลว ${errors.length} ไฟล์`);
        else showToast('อัปโหลดรูปภาพสำเร็จ', 'success');
        window._dpmLoad();
    };

    if (!window._dpmBound) {
        window._dpmBound = true;
        document.addEventListener('change', async function(e) {
            if (e.target && e.target.id === 'dpm-file') {
                const files = [...e.target.files]; e.target.value = '';
                if (files.length) await window._dpmUpload(files);
            }
        });
        document.addEventListener('click', function(e) {
            const lb = document.getElementById('detail-lightbox');
            if (lb && e.target === lb) lb.style.display = 'none';
            const m = document.getElementById('detail-photo-modal');
            if (m && e.target === m) window.closeDetailPhotoManager();
        });
        document.addEventListener('dragover', function(e) { if (e.target && e.target.id === 'dpm-zone') { e.preventDefault(); e.target.style.borderColor = 'var(--primary)'; } });
        document.addEventListener('dragleave', function(e) { if (e.target && e.target.id === 'dpm-zone') e.target.style.borderColor = 'var(--border)'; });
        document.addEventListener('drop', async function(e) {
            if (e.target && e.target.id === 'dpm-zone') {
                e.preventDefault(); e.target.style.borderColor = 'var(--border)';
                const files = [...e.dataTransfer.files].filter(f => f.type.startsWith('image/'));
                if (files.length) await window._dpmUpload(files);
            }
        });
    }
})();
</script>

<style>
/* Reuse pm-img-item styles (defined in printers.php global scope) */
.pm-img-item { position:relative;border-radius:12px;overflow:hidden;aspect-ratio:1/1;cursor:grab;border:2.5px solid transparent;transition:border-color .2s,transform .15s,box-shadow .15s;background:#f1f5f9;user-select:none; }
.pm-img-item:first-child { border-color:var(--primary)!important; }
.pm-img-item.dragging { opacity:.35;transform:scale(.95);cursor:grabbing; }
.pm-img-item.drag-over { border-color:var(--primary);box-shadow:0 0 0 3px rgba(99,102,241,.3); }
.pm-img-item img { width:100%;height:100%;object-fit:cover;display:block;pointer-events:none; }
.pm-del-btn { position:absolute;top:6px;right:6px;background:rgba(239,68,68,.88);color:white;border:none;border-radius:8px;width:28px;height:28px;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;transition:background .2s; }
.pm-del-btn:hover { background:#ef4444; }
.pm-badge-first { position:absolute;bottom:6px;left:6px;background:var(--primary);color:white;font-size:.62rem;font-weight:700;padding:2px 8px;border-radius:10px;pointer-events:none; }

/* ===== Print Label Styles ===== */
@media print {
    /* ซ่อนทุกอย่าง ยกเว้น print-label-area */
    body > * { display: none !important; }
    #app { display: none !important; }
    #print-label-area { display: block !important; position: fixed; top: 0; left: 0; }

    @page {
        /* กระดาษแนวนอน ขนาด label 80mm x 20mm */
        size: 80mm 20mm landscape;
        margin: 0;
    }
}

/* Layout ของป้าย */
.print-label {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    width: 80mm;
    height: 20mm;
    border: 0.3mm solid #333;
    background: #fff;
    font-family: 'Sarabun', 'Outfit', Arial, sans-serif;
    overflow: hidden;
    box-sizing: border-box;
}

/* ฝั่ง QR (18mm x 18mm) */
.print-qr-zone {
    flex-shrink: 0;
    width: 18mm;
    height: 20mm;
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 0.3mm solid #ccc;
    background: #fff;
    padding: 0.5mm;
    box-sizing: border-box;
}
.print-qr-zone img,
.print-qr-zone canvas {
    width: 16mm !important;
    height: 16mm !important;
    display: block;
}

/* ฝั่งข้อมูล */
.print-info-zone {
    flex: 1;
    padding: 1mm 2mm;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.4mm;
    overflow: hidden;
    box-sizing: border-box;
}
.print-info-row {
    display: flex;
    align-items: baseline;
    gap: 1mm;
    line-height: 1.25;
}
.print-label-key {
    font-size: 5pt;
    font-weight: 600;
    color: #555;
    white-space: nowrap;
    flex-shrink: 0;
    min-width: 14mm;
}
.print-label-val {
    font-size: 5.5pt;
    font-weight: 700;
    color: #111;
    word-break: break-all;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.print-qr-id {
    font-size: 4.5pt;
    font-family: monospace;
    color: #666;
    font-weight: 400;
}
</style>
