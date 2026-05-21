<div class="page-header" style="margin-bottom: 24px;">
    <h2>♻️ คืนค่าฐานข้อมูล (Restore Database)</h2>
    <p style="color: var(--text-muted); font-size: 0.95rem;">อัปโหลดไฟล์ .sql เพื่อกู้คืนข้อมูลในระบบ</p>
</div>

<div class="card" style="padding: 32px; max-width: 600px; text-align: center; margin: 0 auto;">
    <div style="font-size: 4rem; margin-bottom: 16px;">♻️</div>
    <h3 style="margin-bottom: 12px; font-size: 1.3rem;">อัปโหลดไฟล์สำรองข้อมูล</h3>
    <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 24px;">
        กรุณาเลือกไฟล์ <strong>.sql</strong> ที่ได้ทำการสำรองไว้ <br>
        <strong style="color: var(--danger);">คำเตือน:</strong> ข้อมูลปัจจุบันทั้งหมดจะถูกแทนที่ด้วยข้อมูลจากไฟล์นี้
    </p>

    <form id="restore-form" onsubmit="handleRestore(event)">
        <div style="margin-bottom: 24px; text-align: left;">
            <label class="form-label">เลือกไฟล์ Backup (.sql)</label>
            <input type="file" id="restore-file" class="form-input" accept=".sql" required style="padding: 10px;">
        </div>
        
        <button type="submit" class="btn-primary" style="display: inline-flex; align-items: center; justify-content: center; padding: 14px 28px; font-size: 1.1rem; border-radius: 12px; width: 100%; box-shadow: 0 4px 12px rgba(99,102,241,0.3);">
            <span class="icon" style="margin-right: 10px;">⬆️</span> เริ่มการคืนค่าข้อมูล
        </button>
    </form>
</div>

<script>
async function handleRestore(e) {
    e.preventDefault();
    const fileInput = document.getElementById('restore-file');
    const file = fileInput.files[0];
    if (!file) {
        showToast('กรุณาเลือกไฟล์ .sql', 'warning');
        return;
    }

    if (!file.name.endsWith('.sql')) {
        showToast('กรุณาเลือกไฟล์นามสกุล .sql เท่านั้น', 'warning');
        return;
    }

    const confirm = await showConfirm({
        title: 'ยืนยันการคืนค่าข้อมูล?',
        message: 'ข้อมูลปัจจุบันทั้งหมดจะถูกลบและแทนที่ด้วยข้อมูลจากไฟล์ Backup นี้ คุณแน่ใจหรือไม่?',
        confirmText: 'ยืนยันและคืนค่า',
        type: 'danger'
    });

    if (!confirm) return;

    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ กำลังคืนค่าข้อมูล...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'restore_db');
    formData.append('backup_file', file);

    try {
        const res = await fetch('api.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.success) {
            showToast('คืนค่าข้อมูลสำเร็จ!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'เกิดข้อผิดพลาดในการคืนค่าข้อมูล', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (err) {
        showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}
</script>
