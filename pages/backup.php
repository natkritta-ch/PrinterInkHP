<div class="page-header" style="margin-bottom: 24px;">
    <h2>💾 สำรองฐานข้อมูล (Backup Database)</h2>
    <p style="color: var(--text-muted); font-size: 0.95rem;">ดาวน์โหลดข้อมูลทั้งหมดในระบบออกมาเป็นไฟล์ .sql เพื่อป้องกันข้อมูลสูญหาย</p>
</div>

<div class="card" style="padding: 32px; max-width: 600px; text-align: center; margin: 0 auto;">
    <div style="font-size: 4rem; margin-bottom: 16px;">📦</div>
    <h3 style="margin-bottom: 12px; font-size: 1.3rem;">พร้อมที่จะสำรองข้อมูลหรือไม่?</h3>
    <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 24px;">
        ระบบจะทำการรวบรวมข้อมูลทั้งหมด ได้แก่ รายชื่อปริ้นเตอร์, คลังหมึก, ประวัติการเบิก, ประวัติการซ่อม ฯลฯ <br>
        แล้วสร้างเป็นไฟล์ <strong>.sql</strong> ให้คุณดาวน์โหลดเก็บไว้ในเครื่อง
    </p>

    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); padding: 16px; border-radius: 12px; margin-bottom: 24px; text-align: left; display: inline-block;">
        <div style="display: flex; gap: 12px; align-items: flex-start;">
            <span style="font-size: 1.2rem;">💡</span>
            <div>
                <div style="font-weight: 600; color: #1e40af; margin-bottom: 4px;">คำแนะนำ</div>
                <div style="font-size: 0.85rem; color: #1e3a8a;">ควรสำรองข้อมูลอย่างน้อยสัปดาห์ละ 1 ครั้ง และเก็บไฟล์ไว้ในที่ปลอดภัย (เช่น Google Drive หรือ External Harddisk)</div>
            </div>
        </div>
    </div>

    <div>
        <a href="api.php?action=backup_db" target="_blank" class="btn-primary" style="display: inline-flex; align-items: center; justify-content: center; padding: 14px 28px; font-size: 1.1rem; border-radius: 12px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 12px rgba(99,102,241,0.3);">
            <span class="icon" style="margin-right: 10px;">⬇️</span> ดาวน์โหลดไฟล์ Backup (.sql)
        </a>
    </div>
</div>
