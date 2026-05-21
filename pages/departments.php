<?php
require_once '../db.php';
$userRole = $_SESSION['role'] ?? '';

if ($userRole !== 'admin') {
    echo '<div class="card" style="text-align:center; padding: 40px; color: var(--danger);"><h3>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h3></div>';
    exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY group_name ASC, sub_name ASC");
    $rows = $stmt->fetchAll();
    
    $groups = [];
    foreach ($rows as $r) {
        $g = $r['group_name'];
        if (!isset($groups[$g])) {
            $groups[$g] = ['id' => null, 'subs' => []];
        }
        if (empty($r['sub_name'])) {
            $groups[$g]['id'] = $r['id'];
        } else {
            $groups[$g]['subs'][] = ['id' => $r['id'], 'name' => $r['sub_name']];
        }
    }
} catch (Exception $e) {
    $groups = [];
}
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1>จัดการกลุ่มงานและหน่วยงาน 🏢</h1>
            <p style="color: var(--text-muted);">จัดการรายชื่อกลุ่มงานหลักและหน่วยงานย่อยในระบบ</p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div style="position: relative;">
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); opacity: 0.5;">🔍</span>
                <input type="text" id="dept-search-input" placeholder="ค้นหากลุ่มงาน/หน่วยงาน..." style="padding: 10px 14px 10px 36px; border: 1.5px solid var(--border); border-radius: 12px; outline: none; font-family: inherit; font-size: 0.95rem; width: 260px;" oninput="filterDepartments(this.value)">
            </div>
            <button class="btn-primary" onclick="document.getElementById('add-group-modal').style.display='block'">
                <span class="icon">➕</span> เพิ่มกลุ่มงานหลัก
            </button>
        </div>
    </div>
</div>

<div class="dept-manager-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
    <?php foreach ($groups as $groupName => $groupData): ?>
    <div class="card" style="padding: 0; overflow: hidden; border-radius: 16px;">
        <div style="background: rgba(99,102,241,0.08); padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(99,102,241,0.12);">
            <div style="font-weight: 700; color: var(--primary-dark); font-size: 1.05rem;">
                🏢 <?= htmlspecialchars($groupName) ?>
            </div>
            <div style="display: flex; gap: 8px;">
                <button onclick="openEditGroup('<?= htmlspecialchars(addslashes($groupName)) ?>')" style="background: none; border: none; cursor: pointer; opacity: 0.7; font-size: 1.1rem;" title="แก้ไขชื่อกลุ่มงาน">✏️</button>
                <button onclick="deleteGroup('<?= htmlspecialchars(addslashes($groupName)) ?>')" style="background: none; border: none; cursor: pointer; opacity: 0.7; font-size: 1.1rem;" title="ลบกลุ่มงาน">🗑️</button>
            </div>
        </div>
        
        <div style="padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">หน่วยงานย่อย (<?= count($groupData['subs']) ?>)</span>
                <button onclick="openAddSub('<?= htmlspecialchars(addslashes($groupName)) ?>')" class="btn-secondary" style="padding: 4px 10px; font-size: 0.75rem; border-radius: 8px;">+ เพิ่ม</button>
            </div>
            
            <?php if (empty($groupData['subs'])): ?>
                <div style="text-align: center; padding: 16px 0; color: var(--text-muted); font-size: 0.85rem; background: #f8fafc; border-radius: 12px; border: 1px dashed var(--border);">
                    ไม่มีหน่วยงานย่อย
                </div>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px;">
                    <?php foreach ($groupData['subs'] as $sub): ?>
                        <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f8fafc; border-radius: 10px; border: 1px solid var(--border);">
                            <span style="font-size: 0.9rem; color: var(--text-main); font-weight: 500;">└ <?= htmlspecialchars($sub['name']) ?></span>
                            <div style="display: flex; gap: 6px;">
                                <button onclick="openEditSub(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['name'])) ?>')" style="background: none; border: none; cursor: pointer; font-size: 0.9rem; opacity: 0.6;" title="แก้ไข">✏️</button>
                                <button onclick="deleteSub(<?= $sub['id'] ?>, this)" style="background: none; border: none; cursor: pointer; font-size: 0.9rem; opacity: 0.6;" title="ลบ">❌</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal เพิ่มกลุ่มงาน -->
<div id="add-group-modal" class="modal">
    <div class="modal-content" style="max-width: 400px">
        <div class="modal-header">
            <h3>เพิ่มกลุ่มงานหลัก</h3>
            <button onclick="document.getElementById('add-group-modal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>
        <form id="add-group-form" onsubmit="handleAddGroup(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">ชื่อกลุ่มงานหลัก</label>
                    <input type="text" name="group_name" class="form-input" placeholder="เช่น กลุ่มงานการพยาบาล" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary" style="width: 100%">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal แก้ไขกลุ่มงาน -->
<div id="edit-group-modal" class="modal">
    <div class="modal-content" style="max-width: 400px">
        <div class="modal-header">
            <h3>แก้ไขชื่อกลุ่มงาน</h3>
            <button onclick="document.getElementById('edit-group-modal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>
        <form id="edit-group-form" onsubmit="handleEditGroup(event)">
            <input type="hidden" id="edit-group-old" name="old_name">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">ชื่อกลุ่มงานหลักใหม่</label>
                    <input type="text" id="edit-group-new" name="new_name" class="form-input" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary" style="width: 100%">อัปเดต</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal เพิ่มหน่วยงานย่อย -->
<div id="add-sub-modal" class="modal">
    <div class="modal-content" style="max-width: 400px">
        <div class="modal-header">
            <h3>เพิ่มหน่วยงานย่อย</h3>
            <button onclick="document.getElementById('add-sub-modal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>
        <form id="add-sub-form" onsubmit="handleAddSub(event)">
            <input type="hidden" id="add-sub-group" name="group_name">
            <div class="modal-body">
                <p style="margin-bottom: 12px; font-size: 0.9rem; color: var(--text-muted);">
                    เพิ่มภายใต้กลุ่มงาน: <strong id="add-sub-group-text" style="color: var(--text-main);"></strong>
                </p>
                <div class="form-group">
                    <label class="form-label">ชื่อหน่วยงานย่อย</label>
                    <input type="text" name="sub_name" class="form-input" placeholder="เช่น ตึกหญิง 2" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary" style="width: 100%">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal แก้ไขหน่วยงานย่อย -->
<div id="edit-sub-modal" class="modal">
    <div class="modal-content" style="max-width: 400px">
        <div class="modal-header">
            <h3>แก้ไขหน่วยงานย่อย</h3>
            <button onclick="document.getElementById('edit-sub-modal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>
        <form id="edit-sub-form" onsubmit="handleEditSub(event)">
            <input type="hidden" id="edit-sub-id" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">ชื่อหน่วยงานย่อย</label>
                    <input type="text" id="edit-sub-name" name="sub_name" class="form-input" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary" style="width: 100%">อัปเดต</button>
            </div>
        </form>
    </div>
</div>

<script>
window.handleAddGroup = async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const res = await fetch('api.php?action=add_group', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) { showToast('เพิ่มสำเร็จ', 'success'); window.location.reload(); }
        else showToast(result.message, 'error');
    } catch(e) { showToast('Error', 'error'); }
};

window.openEditGroup = function(oldName) {
    document.getElementById('edit-group-old').value = oldName;
    document.getElementById('edit-group-new').value = oldName;
    document.getElementById('edit-group-modal').style.display = 'block';
};

window.handleEditGroup = async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const res = await fetch('api.php?action=edit_group', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) { showToast('แก้ไขสำเร็จ', 'success'); window.location.reload(); }
        else showToast(result.message, 'error');
    } catch(e) { showToast('Error', 'error'); }
};

window.deleteGroup = async function(groupName) {
    if (await showConfirm({title:'ลบกลุ่มงาน?', message:`ยืนยันการลบกลุ่มงาน ${groupName} และหน่วยงานย่อยทั้งหมด? (ข้อมูลปริ้นเตอร์เดิมจะไม่ถูกลบ แต่จะกลายเป็นไม่ระบุหน่วยงาน)`, confirmText:'ลบ', type:'danger'})) {
        const formData = new FormData(); formData.append('group_name', groupName);
        try {
            const res = await fetch('api.php?action=delete_group', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) { showToast('ลบสำเร็จ', 'success'); window.location.reload(); }
            else showToast(result.message, 'error');
        } catch(e) { showToast('Error', 'error'); }
    }
};

window.openAddSub = function(groupName) {
    document.getElementById('add-sub-group').value = groupName;
    document.getElementById('add-sub-group-text').textContent = groupName;
    document.getElementById('add-sub-modal').style.display = 'block';
};

window.handleAddSub = async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const res = await fetch('api.php?action=add_sub', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) { showToast('เพิ่มสำเร็จ', 'success'); window.location.reload(); }
        else showToast(result.message, 'error');
    } catch(e) { showToast('Error', 'error'); }
};

window.openEditSub = function(id, name) {
    document.getElementById('edit-sub-id').value = id;
    document.getElementById('edit-sub-name').value = name;
    document.getElementById('edit-sub-modal').style.display = 'block';
};

window.handleEditSub = async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const res = await fetch('api.php?action=edit_sub', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) { showToast('แก้ไขสำเร็จ', 'success'); window.location.reload(); }
        else showToast(result.message, 'error');
    } catch(e) { showToast('Error', 'error'); }
};

window.deleteSub = async function(id, btn) {
    if (await showConfirm({title:'ลบหน่วยงานย่อย?', message:'ยืนยันการลบหน่วยงานย่อยนี้?', confirmText:'ลบ', type:'danger'})) {
        const formData = new FormData(); formData.append('id', id);
        try {
            const res = await fetch('api.php?action=delete_sub', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) { 
                showToast('ลบสำเร็จ', 'success'); 
                btn.closest('li').remove();
            }
            else showToast(result.message, 'error');
        } catch(e) { showToast('Error', 'error'); }
    }
};

window.filterDepartments = function(query) {
    query = query.toLowerCase().trim();
    const cards = document.querySelectorAll('.dept-manager-grid .card');
    cards.forEach(card => {
        const groupTitleEl = card.querySelector('div[style*="font-weight: 700"]');
        if (!groupTitleEl) return;
        const groupTitle = groupTitleEl.textContent.toLowerCase();
        let matchGroup = groupTitle.includes(query);
        
        const listItems = card.querySelectorAll('ul li');
        let matchAnySub = false;
        
        listItems.forEach(li => {
            const subNameEl = li.querySelector('span');
            if (!subNameEl) return;
            const subName = subNameEl.textContent.toLowerCase();
            
            if (subName.includes(query) || matchGroup || query === '') {
                li.style.display = 'flex';
                if (subName.includes(query)) matchAnySub = true;
            } else {
                li.style.display = 'none';
            }
        });
        
        // Also check if no-data text matches (usually not needed, but safe to just rely on group or sub matches)
        if (matchGroup || matchAnySub || query === '') {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
};
</script>
