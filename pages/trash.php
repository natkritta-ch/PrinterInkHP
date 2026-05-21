<?php
require_once '../db.php';

// ดึงข้อมูลในถังขยะ
try {
    $stmt = $pdo->query("SELECT * FROM printers WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $trashPrinters = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT * FROM ink_stock WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $trashInks = $stmt->fetchAll();

    // เตรียม column deleted_at ถ้ายังไม่มี เพื่อป้องกัน error
    try { $pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch (Exception $e) {}

    $stmt = $pdo->query("SELECT ml.*, p.brand, p.model, p.serial_number FROM maintenance_logs ml JOIN printers p ON ml.printer_id = p.id WHERE ml.deleted_at IS NOT NULL ORDER BY ml.deleted_at DESC");
    $trashMaintenance = $stmt->fetchAll();
} catch (Exception $e) {
    $trashPrinters = [];
    $trashInks = [];
    $trashMaintenance = [];
}
?>

<div class="page-header">
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
        <div style="display: flex; align-items: center; gap: 10px; min-width: 0">
            <button onclick="window.loadPage('printers')" style="background: var(--bg-body); border: none; width: 36px; height: 36px; border-radius: 10px; cursor: pointer; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0">⬅️</button>
            <div style="min-width: 0">
                <h1 style="font-size: 1.3rem">ถังขยะ 🗑️</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem">รายการที่ถูกลบจะถูกเก็บไว้ที่นี่เป็นเวลา 30 วัน</p>
            </div>
        </div>
        <?php if (!empty($trashPrinters) || !empty($trashInks) || !empty($trashMaintenance)): ?>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn-secondary" onclick="restoreSelected()" id="btn-restore-selected" style="display: none; border-color: var(--success); color: var(--success);">↺ กู้คืนที่เลือก</button>
            <button class="btn-secondary" onclick="deleteSelected()" id="btn-delete-selected" style="display: none; border-color: var(--danger); color: var(--danger);">ลบที่เลือก</button>
            <button class="btn-primary" onclick="emptyTrash()" style="background: var(--danger); border-color: var(--danger);">🔥 ลบถาวรทั้งหมด</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($trashPrinters) && empty($trashInks) && empty($trashMaintenance)): ?>
    <div class="card" style="text-align: center; padding: 60px">
        <div style="font-size: 4rem; margin-bottom: 20px">✨</div>
        <h3>ถังขยะว่างเปล่า</h3>
        <p style="color: var(--text-muted)">ไม่มีรายการที่ถูกลบในขณะนี้</p>
    </div>
<?php else: ?>
    
    <!-- ปริ้นเตอร์ในถังขยะ -->
    <?php if (!empty($trashPrinters)): ?>
    <div style="margin-bottom: 30px">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h3 style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px; margin: 0">🖨️ ปริ้นเตอร์ที่ถูกลบ</h3>
            <div style="display: flex; gap: 8px;">
                <button id="section-restore-printer" onclick="restoreSection('printer')" style="display:none; padding: 6px 14px; border-radius: 10px; border: 1.5px solid var(--success); color: var(--success); background: white; cursor: pointer; font-size: 0.82rem; font-weight: 600;">↺ กู้คืนที่เลือก</button>
                <button id="section-delete-printer" onclick="deleteSection('printer')" style="display:none; padding: 6px 14px; border-radius: 10px; border: 1.5px solid var(--danger); color: var(--danger); background: white; cursor: pointer; font-size: 0.82rem; font-weight: 600;">ลบที่เลือก</button>
            </div>
        </div>
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 20px;">
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch">
                <table style="width: 100%; border-collapse: collapse; min-width: 600px">
                    <thead>
                        <tr style="text-align: left; background: #f8fafc; border-bottom: 1px solid var(--border);">
                            <th style="padding: 16px 24px; width: 50px;"><input type="checkbox" onclick="toggleAll(this, 'printer')" style="width: 18px; height: 18px; cursor: pointer;"></th>
                            <th style="padding: 16px 24px;">ปริ้นเตอร์</th>
                            <th style="padding: 16px 24px;">หน่วยงาน</th>
                            <th style="padding: 16px 24px;">วันที่ลบ</th>
                            <th style="padding: 16px 24px; text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trashPrinters as $p): ?>
                            <tr style="border-bottom: 1px solid var(--border);" class="trash-row">
                                <td style="padding: 16px 24px;"><input type="checkbox" class="trash-cb trash-cb-printer" data-id="<?php echo $p['id']; ?>" data-type="printer" style="width: 18px; height: 18px; cursor: pointer;" onchange="updateSelectedCount()"></td>
                                <td style="padding: 16px 24px;">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($p['brand'] . ' ' . $p['model']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">SN: <?php echo htmlspecialchars($p['serial_number']); ?></div>
                                </td>
                                <td style="padding: 16px 24px;">
                                    <span style="font-size: 0.9rem;">📍 <?php echo htmlspecialchars($p['department']); ?></span>
                                </td>
                                <td style="padding: 16px 24px;">
                                    <div style="font-size: 0.9rem;"><?php echo thdatetime($p['deleted_at']); ?></div>
                                </td>
                                <td style="padding: 16px 24px; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <button class="btn-primary" onclick="restorePrinter(<?php echo $p['id']; ?>, this)" style="padding: 6px 12px; font-size: 0.8rem; background: var(--success); border-color: var(--success);">↺ กู้คืน</button>
                                        <button class="btn-secondary" onclick="permanentDelete(<?php echo $p['id']; ?>, this)" style="padding: 6px 12px; font-size: 0.8rem; border-color: var(--danger); color: var(--danger);">ลบถาวร</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- หมึกในถังขยะ -->
    <?php if (!empty($trashInks)): ?>
    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h3 style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px; margin: 0">📦 รายการหมึกที่ถูกลบ</h3>
            <div style="display: flex; gap: 8px;">
                <button id="section-restore-ink" onclick="restoreSection('ink')" style="display:none; padding: 6px 14px; border-radius: 10px; border: 1.5px solid var(--success); color: var(--success); background: white; cursor: pointer; font-size: 0.82rem; font-weight: 600;">↺ กู้คืนที่เลือก</button>
                <button id="section-delete-ink" onclick="deleteSection('ink')" style="display:none; padding: 6px 14px; border-radius: 10px; border: 1.5px solid var(--danger); color: var(--danger); background: white; cursor: pointer; font-size: 0.82rem; font-weight: 600;">ลบที่เลือก</button>
            </div>
        </div>
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 20px;">
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch">
                <table style="width: 100%; border-collapse: collapse; min-width: 600px">
                    <thead>
                        <tr style="text-align: left; background: #f8fafc; border-bottom: 1px solid var(--border);">
                            <th style="padding: 16px 24px; width: 50px;"><input type="checkbox" onclick="toggleAll(this, 'ink')" style="width: 18px; height: 18px; cursor: pointer;"></th>
                            <th style="padding: 16px 24px;">บาร์โค้ด / รุ่นหมึก</th>
                            <th style="padding: 16px 24px;">ยี่ห้อ</th>
                            <th style="padding: 16px 24px;">วันที่ลบ</th>
                            <th style="padding: 16px 24px; text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trashInks as $i): ?>
                            <tr style="border-bottom: 1px solid var(--border);" class="trash-row">
                                <td style="padding: 16px 24px;"><input type="checkbox" class="trash-cb trash-cb-ink" data-id="<?php echo $i['id']; ?>" data-type="ink" style="width: 18px; height: 18px; cursor: pointer;" onchange="updateSelectedCount()"></td>
                                <td style="padding: 16px 24px;">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($i['name']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($i['barcode']); ?></div>
                                </td>
                                <td style="padding: 16px 24px;">
                                    <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($i['brand']); ?></span>
                                </td>
                                <td style="padding: 16px 24px;">
                                    <div style="font-size: 0.9rem;"><?php echo thdatetime($i['deleted_at']); ?></div>
                                </td>
                                <td style="padding: 16px 24px; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <button class="btn-primary" onclick="restoreInk(<?php echo $i['id']; ?>, this)" style="padding: 6px 12px; font-size: 0.8rem; background: var(--success); border-color: var(--success);">↺ กู้คืน</button>
                                        <button class="btn-secondary" onclick="permanentDeleteInk(<?php echo $i['id']; ?>, this)" style="padding: 6px 12px; font-size: 0.8rem; border-color: var(--danger); color: var(--danger);">ลบถาวร</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ประวัติซ่อมในถังขยะ -->
    <?php if (!empty($trashMaintenance)): ?>
    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h3 style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px; margin: 0">🔧 ประวัติการซ่อมที่ถูกลบ</h3>
            <div style="display: flex; gap: 8px;">
                <button id="section-restore-maintenance" onclick="restoreSection('maintenance')" style="display:none; padding: 6px 14px; border-radius: 10px; border: 1.5px solid var(--success); color: var(--success); background: white; cursor: pointer; font-size: 0.82rem; font-weight: 600;">↺ กู้คืนที่เลือก</button>
                <button id="section-delete-maintenance" onclick="deleteSection('maintenance')" style="display:none; padding: 6px 14px; border-radius: 10px; border: 1.5px solid var(--danger); color: var(--danger); background: white; cursor: pointer; font-size: 0.82rem; font-weight: 600;">ลบที่เลือก</button>
            </div>
        </div>
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 20px;">
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch">
                <table style="width: 100%; border-collapse: collapse; min-width: 600px">
                    <thead>
                        <tr style="text-align: left; background: #f8fafc; border-bottom: 1px solid var(--border);">
                            <th style="padding: 16px 24px; width: 50px;"><input type="checkbox" onclick="toggleAll(this, 'maintenance')" style="width: 18px; height: 18px; cursor: pointer;"></th>
                            <th style="padding: 16px 24px;">ปริ้นเตอร์ / อาการ</th>
                            <th style="padding: 16px 24px;">ช่างผู้ซ่อม</th>
                            <th style="padding: 16px 24px;">วันที่ลบ</th>
                            <th style="padding: 16px 24px; text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trashMaintenance as $m): ?>
                            <tr style="border-bottom: 1px solid var(--border);" class="trash-row">
                                <td style="padding: 16px 24px;"><input type="checkbox" class="trash-cb trash-cb-maintenance" data-id="<?php echo $m['id']; ?>" data-type="maintenance" style="width: 18px; height: 18px; cursor: pointer;" onchange="updateSelectedCount()"></td>
                                <td style="padding: 16px 24px;">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($m['brand'] . ' ' . $m['model']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">อาการ: <?php echo htmlspecialchars($m['symptoms']); ?></div>
                                </td>
                                <td style="padding: 16px 24px;">
                                    <span style="font-size: 0.9rem;">👷 <?php echo htmlspecialchars($m['technician_name'] ?? '-'); ?></span>
                                </td>
                                <td style="padding: 16px 24px;">
                                    <div style="font-size: 0.9rem;"><?php echo thdatetime($m['deleted_at']); ?></div>
                                </td>
                                <td style="padding: 16px 24px; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <button class="btn-primary" onclick="restoreMaintenance(<?php echo $m['id']; ?>, this)" style="padding: 6px 12px; font-size: 0.8rem; background: var(--success); border-color: var(--success);">↺ กู้คืน</button>
                                        <button class="btn-secondary" onclick="permanentDeleteMaintenance(<?php echo $m['id']; ?>, this)" style="padding: 6px 12px; font-size: 0.8rem; border-color: var(--danger); color: var(--danger);">ลบถาวร</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

<script>
// ลบ row ออกจาก DOM ทันที (ไม่ต้อง reload)
function removeRow(btn) {
    const row = btn.closest('tr');
    if (row) {
        row.style.transition = 'opacity 0.3s, transform 0.3s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(20px)';
        setTimeout(() => {
            row.remove();
            // ถ้าตารางว่าง ซ่อนทั้ง section
            document.querySelectorAll('tbody').forEach(tb => {
                if (tb.querySelectorAll('tr').length === 0) {
                    const section = tb.closest('div[style*="margin-bottom"]') || tb.closest('.card');
                    if (section) section.style.display = 'none';
                }
            });
        }, 300);
    }
}

async function restorePrinter(id, btn) {
    const ok = await showConfirm({title: 'กู้คืนปริ้นเตอร์?', message: 'ยืนยันการกู้คืนข้อมูลปริ้นเตอร์เครื่องนี้?', confirmText: 'กู้คืน', type: 'info'});
    if (!ok) return;
    const formData = new FormData(); formData.append('printer_id', id);
    try {
        const response = await fetch('api.php?action=restore_printer', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) { showToast('กู้คืนเรียบร้อยแล้ว', 'success'); removeRow(btn); }
        else { showToast(result.message, 'error'); }
    } catch (error) { showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error'); }
}

async function permanentDelete(id, btn) {
    const ok = await showConfirm({title: 'ลบถาวร?', message: 'ยืนยันการลบข้อมูลถาวร? การกระทำนี้ไม่สามารถย้อนกลับได้', confirmText: 'ลบถาวร', type: 'danger'});
    if (!ok) return;
    const formData = new FormData(); formData.append('printer_id', id);
    try {
        const response = await fetch('api.php?action=permanent_delete_printer', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) { showToast('ลบถาวรเรียบร้อยแล้ว', 'success'); removeRow(btn); }
        else { showToast(result.message, 'error'); }
    } catch (error) { showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error'); }
}

async function restoreInk(id, btn) {
    const ok = await showConfirm({title: 'กู้คืนหมึก?', message: 'ยืนยันการกู้คืนข้อมูลหมึกรายการนี้?', confirmText: 'กู้คืน', type: 'info'});
    if (!ok) return;
    const formData = new FormData(); formData.append('ink_id', id);
    try {
        const response = await fetch('api.php?action=restore_ink', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) { showToast('กู้คืนหมึกเรียบร้อยแล้ว', 'success'); removeRow(btn); }
        else { showToast(result.message, 'error'); }
    } catch (error) { showToast('เกิดข้อผิดพลาด', 'error'); }
}

async function permanentDeleteInk(id, btn) {
    const ok = await showConfirm({title: 'ลบหมึกถาวร?', message: 'ยืนยันการลบข้อมูลหมึกถาวร?', confirmText: 'ลบถาวร', type: 'danger'});
    if (!ok) return;
    const formData = new FormData(); formData.append('ink_id', id);
    try {
        const response = await fetch('api.php?action=permanent_delete_ink', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) { showToast('ลบหมึกถาวรเรียบร้อยแล้ว', 'success'); removeRow(btn); }
        else { showToast(result.message, 'error'); }
    } catch (error) { showToast('เกิดข้อผิดพลาด', 'error'); }
}

async function restoreMaintenance(id, btn) {
    const ok = await showConfirm({title: 'กู้คืนประวัติซ่อม?', message: 'ยืนยันการกู้คืนประวัติการซ่อมนี้?', confirmText: 'กู้คืน', type: 'info'});
    if (!ok) return;
    const formData = new FormData(); formData.append('log_id', id);
    try {
        const response = await fetch('api.php?action=restore_maintenance_log', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) { showToast('กู้คืนประวัติการซ่อมเรียบร้อยแล้ว', 'success'); removeRow(btn); }
        else { showToast(result.message, 'error'); }
    } catch (error) { showToast('เกิดข้อผิดพลาด', 'error'); }
}

async function permanentDeleteMaintenance(id, btn) {
    const ok = await showConfirm({title: 'ลบประวัติซ่อมถาวร?', message: 'ยืนยันการลบประวัติซ่อมถาวร?', confirmText: 'ลบถาวร', type: 'danger'});
    if (!ok) return;
    const formData = new FormData(); formData.append('log_id', id);
    try {
        const response = await fetch('api.php?action=permanent_delete_maintenance_log', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) { showToast('ลบประวัติซ่อมถาวรเรียบร้อยแล้ว', 'success'); removeRow(btn); }
        else { showToast(result.message, 'error'); }
    } catch (error) { showToast('เกิดข้อผิดพลาด', 'error'); }
}

function toggleAll(source, type) {
    const checkboxes = document.querySelectorAll('.trash-cb-' + type);
    checkboxes.forEach(cb => { cb.checked = source.checked; });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.trash-cb:checked').length;
    const btnDel = document.getElementById('btn-delete-selected');
    const btnRestore = document.getElementById('btn-restore-selected');
    if (btnDel) {
        btnDel.style.display = checked > 0 ? 'block' : 'none';
        if (checked > 0) btnDel.innerHTML = `ลบที่เลือก (${checked})`;
    }
    if (btnRestore) {
        btnRestore.style.display = checked > 0 ? 'block' : 'none';
        if (checked > 0) btnRestore.innerHTML = `↺ กู้คืนที่เลือก (${checked})`;
    }

    // update per-section buttons
    ['printer', 'ink', 'maintenance'].forEach(type => {
        const sectionChecked = document.querySelectorAll(`.trash-cb-${type}:checked`).length;
        const sRestore = document.getElementById(`section-restore-${type}`);
        const sDelete  = document.getElementById(`section-delete-${type}`);
        if (sRestore) {
            sRestore.style.display = sectionChecked > 0 ? 'inline-block' : 'none';
            if (sectionChecked > 0) sRestore.textContent = `↺ กู้คืนที่เลือก (${sectionChecked})`;
        }
        if (sDelete) {
            sDelete.style.display = sectionChecked > 0 ? 'inline-block' : 'none';
            if (sectionChecked > 0) sDelete.textContent = `ลบที่เลือก (${sectionChecked})`;
        }
    });
}

async function restoreSection(type) {
    const checked = document.querySelectorAll(`.trash-cb-${type}:checked`);
    if (checked.length === 0) return;
    const ok = await showConfirm({ title: 'กู้คืนที่เลือก?', message: `ยืนยันกู้คืน ${checked.length} รายการ`, confirmText: '↺ กู้คืน', type: 'info' });
    if (!ok) return;
    await processBulkRestore(Array.from(checked).map(cb => ({ id: cb.dataset.id, type: cb.dataset.type })));
}

async function deleteSection(type) {
    const checked = document.querySelectorAll(`.trash-cb-${type}:checked`);
    if (checked.length === 0) return;
    const ok = await showConfirm({ title: 'ลบที่เลือกถาวร?', message: `ยืนยันลบถาวร ${checked.length} รายการ`, confirmText: 'ลบถาวร', type: 'danger' });
    if (!ok) return;
    await processBulkDelete(Array.from(checked).map(cb => ({ id: cb.dataset.id, type: cb.dataset.type })));
}

async function processBulkDelete(items) {
    let successCount = 0;
    showToast('กำลังลบข้อมูล...', 'info');
    for (let item of items) {
        const formData = new FormData();
        let action = '';
        if (item.type === 'printer') { action = 'permanent_delete_printer'; formData.append('printer_id', item.id); }
        else if (item.type === 'ink') { action = 'permanent_delete_ink'; formData.append('ink_id', item.id); }
        else if (item.type === 'maintenance') { action = 'permanent_delete_maintenance_log'; formData.append('log_id', item.id); }
        
        if (action) {
            try {
                const response = await fetch(`api.php?action=${action}`, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    successCount++;
                    // Remove from DOM safely
                    const cb = document.querySelector(`.trash-cb[data-id="${item.id}"][data-type="${item.type}"]`);
                    if (cb) removeRow(cb);
                }
            } catch (e) { console.error('Error deleting', item, e); }
        }
    }
    showToast(`ลบสำเร็จ ${successCount} รายการ`, 'success');
    updateSelectedCount();
}

async function restoreSelected() {
    const checked = document.querySelectorAll('.trash-cb:checked');
    if (checked.length === 0) return;
    
    const ok = await showConfirm({
        title: 'กู้คืนรายการที่เลือก?', 
        message: `ยืนยันการกู้คืนทั้ง ${checked.length} รายการใช่หรือไม่?`, 
        confirmText: '↺ กู้คืน', 
        type: 'info'
    });
    if (!ok) return;

    const items = Array.from(checked).map(cb => ({ id: cb.dataset.id, type: cb.dataset.type }));
    await processBulkRestore(items);
}

async function processBulkRestore(items) {
    let successCount = 0;
    showToast('กำลังกู้คืนข้อมูล...', 'info');
    for (let item of items) {
        const formData = new FormData();
        let action = '';
        if (item.type === 'printer') { action = 'restore_printer'; formData.append('printer_id', item.id); }
        else if (item.type === 'ink') { action = 'restore_ink'; formData.append('ink_id', item.id); }
        else if (item.type === 'maintenance') { action = 'restore_maintenance_log'; formData.append('log_id', item.id); }
        
        if (action) {
            try {
                const response = await fetch(`api.php?action=${action}`, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    successCount++;
                    const cb = document.querySelector(`.trash-cb[data-id="${item.id}"][data-type="${item.type}"]`);
                    if (cb) removeRow(cb);
                }
            } catch (e) { console.error('Error restoring', item, e); }
        }
    }
    showToast(`กู้คืนสำเร็จ ${successCount} รายการ`, 'success');
    updateSelectedCount();
}

async function deleteSelected() {
    const checked = document.querySelectorAll('.trash-cb:checked');
    if (checked.length === 0) return;
    
    const ok = await showConfirm({
        title: 'ลบรายการที่เลือก?', 
        message: `คุณต้องการลบถาวรทั้ง ${checked.length} รายการใช่หรือไม่?`, 
        confirmText: 'ลบถาวร', 
        type: 'danger'
    });
    if (!ok) return;

    const items = Array.from(checked).map(cb => ({ id: cb.dataset.id, type: cb.dataset.type }));
    await processBulkDelete(items);
}

async function emptyTrash() {
    const allItems = document.querySelectorAll('.trash-cb');
    if (allItems.length === 0) return;

    const ok = await showConfirm({
        title: 'ล้างถังขยะทั้งหมด?', 
        message: `คุณต้องการลบถาวรทั้ง ${allItems.length} รายการในถังขยะใช่หรือไม่? (การกระทำนี้ไม่สามารถย้อนกลับได้)`, 
        confirmText: '🔥 ล้างถังขยะ', 
        type: 'danger'
    });
    if (!ok) return;

    const items = Array.from(allItems).map(cb => ({ id: cb.dataset.id, type: cb.dataset.type }));
    await processBulkDelete(items);
}
</script>
