<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="margin:0; display:flex; align-items:center; gap:10px;">
            <span>💾</span> สำรองฐานข้อมูล (Backup Database)
        </h1>
    </div>
    <div>
        <p style="color:var(--text-muted); margin:0; font-size:0.9rem;">
            ดาวน์โหลดข้อมูลทั้งหมดในระบบออกมาเป็นไฟล์ .sql เพื่อป้องกันข้อมูลสูญหาย
        </p>
    </div>
</div>

<div class="card" style="max-width: 600px; margin: 40px auto; text-align: center; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
    <div style="font-size: 4rem; margin-bottom: 20px;">📦</div>
    
    <h2 style="margin-bottom: 15px; color: var(--text-main);">พร้อมที่จะสำรองข้อมูลหรือไม่?</h2>
    
    <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; margin-bottom: 30px;">
        ระบบจะทำการรวบรวมข้อมูลทั้งหมด ได้แก่ รายชื่อปริ้นเตอร์, คลังหมึก, ประวัติการเบิก, ประวัติการซ่อม ฯลฯ <br>
        แล้วสร้างเป็นไฟล์ .sql ให้คุณดาวน์โหลดเก็บไว้ในเครื่อง
    </p>

    <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 12px; padding: 16px 20px; text-align: left; display: flex; gap: 12px; margin-bottom: 30px;">
        <span style="font-size: 1.2rem; flex-shrink: 0;">💡</span>
        <div>
            <div style="color: #2563eb; font-weight: 700; font-size: 0.9rem; margin-bottom: 4px;">คำแนะนำ</div>
            <div style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5;">
                ควรสำรองข้อมูลอย่างน้อยสัปดาห์ละ 1 ครั้ง และเก็บไฟล์ไว้ในที่ปลอดภัย (เช่น Google Drive หรือ External Harddisk)
            </div>
        </div>
    </div>

    <a href="api.php?action=backup_db" target="_blank" class="btn-primary" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 28px; font-size: 1rem; border-radius: 12px; text-decoration: none; width: 100%; max-width: 300px;">
        ⬇️ ดาวน์โหลดไฟล์ Backup (.sql)
    </a>
</div>
