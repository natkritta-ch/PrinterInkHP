<?php
require_once '../db.php';
$userRole = $_SESSION['role'] ?? '';

if ($userRole !== 'admin') {
    echo '<div class="card" style="text-align:center; padding: 40px; color: var(--danger);"><h3>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h3></div>';
    exit;
}

try {
    $stmt = $pdo->query("SELECT id, username, full_name, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1>จัดการผู้ใช้ 👥</h1>
            <p style="color: var(--text-muted);">จัดการรายชื่อผู้ใช้ที่สามารถเข้าถึงระบบ</p>
        </div>
        <button class="btn-primary" onclick="document.getElementById('add-user-modal').style.display='block'">
            <span class="icon">➕</span> เพิ่มผู้ใช้
        </button>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 500px;">
            <thead>
                <tr style="text-align: left; background: #f8fafc; border-bottom: 2px solid var(--border);">
                    <th style="padding: 16px 24px;">ชื่อผู้ใช้งาน</th>
                    <th style="padding: 16px 24px;">ชื่อ-นามสกุล</th>
                    <th style="padding: 16px 24px;">บทบาท</th>
                    <th style="padding: 16px 24px;">วันที่เพิ่ม</th>
                    <th style="padding: 16px 24px; text-align: center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 16px 24px; font-weight: 600;"><?= htmlspecialchars($u['username']) ?></td>
                        <td style="padding: 16px 24px;"><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>
                        <td style="padding: 16px 24px;">
                            <?php if($u['role'] === 'admin'): ?>
                                <span class="badge badge-normal">Admin</span>
                            <?php else: ?>
                                <span class="badge" style="background:#f1f5f9; color:#475569;">User</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px 24px; color: var(--text-muted); font-size: 0.9rem;">
                            <?= thdate($u['created_at']) ?>
                        </td>
                        <td style="padding: 16px 24px; text-align: center;">
                            <div style="display: flex; gap: 6px; justify-content: center;">
                                <button onclick="openEditUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', '<?= htmlspecialchars(addslashes($u['full_name'] ?? '')) ?>', '<?= $u['role'] ?>')" 
                                    style="background: none; border: 1px solid var(--primary); color: var(--primary); padding: 4px 8px; border-radius: 6px; cursor: pointer;">✏️ แก้ไข</button>
                                <?php if($u['id'] !== $_SESSION['user_id']): ?>
                                    <button onclick="deleteUser(<?= $u['id'] ?>, this)" 
                                        style="background: none; border: 1px solid var(--danger); color: var(--danger); padding: 4px 8px; border-radius: 6px; cursor: pointer;">🗑️ ลบ</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal เพิ่มผู้ใช้ -->
<div id="add-user-modal" class="modal">
    <div class="modal-content" style="max-width: 400px">
        <div class="modal-header">
            <h3>เพิ่มผู้ใช้ใหม่</h3>
            <button onclick="document.getElementById('add-user-modal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>
        <form id="add-user-form" onsubmit="handleAddUser(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">ชื่อผู้ใช้งาน</label>
                    <input type="text" name="username" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" name="full_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">รหัสผ่าน</label>
                    <div style="position: relative;">
                        <input type="password" id="add-user-password" name="password" class="form-input" style="padding-right: 40px;" required>
                        <button type="button" onclick="togglePassword('add-user-password', this)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2rem; color: var(--text-muted); padding: 0;">👁️</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">บทบาท</label>
                    <select name="role" class="form-input">
                        <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        <option value="user">User (ผู้ใช้งานทั่วไป)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary" style="width: 100%">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal แก้ไขผู้ใช้ -->
<div id="edit-user-modal" class="modal">
    <div class="modal-content" style="max-width: 400px">
        <div class="modal-header">
            <h3>แก้ไขผู้ใช้</h3>
            <button onclick="document.getElementById('edit-user-modal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>
        <form id="edit-user-form" onsubmit="handleEditUser(event)">
            <input type="hidden" id="edit-user-id" name="user_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">ชื่อผู้ใช้งาน</label>
                    <input type="text" id="edit-user-username" name="username" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" id="edit-user-fullname" name="full_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">รหัสผ่านใหม่ (ปล่อยว่างถ้าไม่เปลี่ยน)</label>
                    <div style="position: relative;">
                        <input type="password" id="edit-user-password" name="password" class="form-input" style="padding-right: 40px;">
                        <button type="button" onclick="togglePassword('edit-user-password', this)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2rem; color: var(--text-muted); padding: 0;">👁️</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">บทบาท</label>
                    <select id="edit-user-role" name="role" class="form-input">
                        <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        <option value="user">User (ผู้ใช้งานทั่วไป)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary" style="width: 100%">อัปเดต</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openEditUser = function(id, username, fullName, role) {
    document.getElementById('edit-user-id').value = id;
    document.getElementById('edit-user-username').value = username;
    document.getElementById('edit-user-fullname').value = fullName;
    document.getElementById('edit-user-role').value = role;
    document.getElementById('edit-user-modal').style.display = 'block';
};

window.handleAddUser = async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const res = await fetch('api.php?action=add_user', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            showToast('เพิ่มผู้ใช้สำเร็จ', 'success');
            window.location.reload();
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) { showToast('Error', 'error'); }
};

window.handleEditUser = async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const res = await fetch('api.php?action=edit_user', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            showToast('แก้ไขผู้ใช้สำเร็จ', 'success');
            window.location.reload();
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) { showToast('Error', 'error'); }
};

window.deleteUser = async function(id, btn) {
    if (await showConfirm({title: 'ลบผู้ใช้?', message: 'ยืนยันการลบผู้ใช้รายนี้?', confirmText: 'ลบ', type: 'danger'})) {
        try {
            const formData = new FormData(); formData.append('user_id', id);
            const res = await fetch('api.php?action=delete_user', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
                showToast('ลบผู้ใช้สำเร็จ', 'success');
                const row = btn.closest('tr');
                if (row) {
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                }
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) { showToast('Error', 'error'); }
    }
};
</script>
