<div class="page-header" style="margin-bottom: 24px;">
    <h2>🖥️ ข้อมูล Server</h2>
    <p style="color: var(--text-muted); font-size: 0.95rem;">ข้อมูลการเชื่อมต่อ Server ภายในองค์กร (เฉพาะผู้ดูแลระบบเท่านั้น)</p>
</div>

<!-- Production Server -->
<div style="margin-bottom: 24px;">
    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">🟢 Server หลัก (Production)</div>
    <div class="card" style="padding: 28px;">
        <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">🖥️</div>
            <div>
                <div style="font-weight: 700; font-size: 1.1rem;">Main Server</div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">เซิร์ฟเวอร์หลักของระบบ</div>
            </div>
            <div style="margin-left: auto;">
                <span style="background: rgba(16,185,129,0.1); color: #059669; border: 1px solid rgba(16,185,129,0.3); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">🟢 Online</span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid var(--border);">
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">🌐 IP Address</div>
                <div style="font-size: 1rem; font-weight: 700; font-family: monospace; color: var(--primary);">192.168.9.230</div>
                <button onclick="copyText('192.168.9.230', this)" style="margin-top: 8px; background: none; border: 1px solid var(--border); border-radius: 6px; padding: 3px 10px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; transition: all 0.2s;">📋 คัดลอก</button>
            </div>
            <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid var(--border);">
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">👤 Username</div>
                <div style="font-size: 1rem; font-weight: 700; font-family: monospace; color: #0f172a;">root</div>
                <button onclick="copyText('root', this)" style="margin-top: 8px; background: none; border: 1px solid var(--border); border-radius: 6px; padding: 3px 10px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; transition: all 0.2s;">📋 คัดลอก</button>
            </div>
            <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid var(--border);">
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">🔑 Password</div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div id="pass-prod" style="font-size: 1rem; font-weight: 700; font-family: monospace; color: #0f172a; letter-spacing: 3px;">••••••••</div>
                    <button onclick="togglePass('pass-prod', 'phan11190', this)" style="background: none; border: none; font-size: 1rem; cursor: pointer; color: var(--text-muted);">👁️</button>
                </div>
                <button onclick="copyText('phan11190', this)" style="margin-top: 8px; background: none; border: 1px solid var(--border); border-radius: 6px; padding: 3px 10px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; transition: all 0.2s;">📋 คัดลอก</button>
            </div>
        </div>
    </div>
</div>

<!-- Test Server -->
<div style="margin-bottom: 24px;">
    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">🟡 Server ทดสอบ (Test)</div>
    <div class="card" style="padding: 28px;">
        <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f59e0b, #ef4444); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">🧪</div>
            <div>
                <div style="font-weight: 700; font-size: 1.1rem;">Server 102 (Natkritta)</div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">เซิร์ฟเวอร์สำหรับทดสอบระบบ</div>
            </div>
            <div style="margin-left: auto;">
                <span style="background: rgba(245,158,11,0.1); color: #d97706; border: 1px solid rgba(245,158,11,0.3); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">🟡 Test</span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid var(--border);">
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">🌐 Server</div>
                <div style="font-size: 1rem; font-weight: 700; font-family: monospace; color: var(--primary);">102</div>
                <button onclick="copyText('102', this)" style="margin-top: 8px; background: none; border: 1px solid var(--border); border-radius: 6px; padding: 3px 10px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; transition: all 0.2s;">📋 คัดลอก</button>
            </div>
            <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid var(--border);">
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">👤 Username</div>
                <div style="font-size: 1rem; font-weight: 700; font-family: monospace; color: #0f172a;">phan</div>
                <button onclick="copyText('phan', this)" style="margin-top: 8px; background: none; border: 1px solid var(--border); border-radius: 6px; padding: 3px 10px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; transition: all 0.2s;">📋 คัดลอก</button>
            </div>
            <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid var(--border);">
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">🔑 Password</div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div id="pass-test" style="font-size: 1rem; font-weight: 700; font-family: monospace; color: #0f172a; letter-spacing: 3px;">••••••</div>
                    <button onclick="togglePass('pass-test', '11190', this)" style="background: none; border: none; font-size: 1rem; cursor: pointer; color: var(--text-muted);">👁️</button>
                </div>
                <button onclick="copyText('11190', this)" style="margin-top: 8px; background: none; border: 1px solid var(--border); border-radius: 6px; padding: 3px 10px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; transition: all 0.2s;">📋 คัดลอก</button>
            </div>
        </div>
    </div>
</div>

<!-- Warning Card -->
<div class="card" style="border: 1px solid rgba(239,68,68,0.25); background: rgba(255,241,242,0.5); padding: 16px 20px;">
    <div style="display: flex; gap: 12px; align-items: flex-start;">
        <span style="font-size: 1.4rem;">⚠️</span>
        <div>
            <div style="font-weight: 700; color: #b91c1c; margin-bottom: 4px;">คำเตือนด้านความปลอดภัย</div>
            <div style="font-size: 0.85rem; color: #7f1d1d; line-height: 1.6;">
                ข้อมูลนี้เป็นความลับสูง กรุณาอย่าแชร์ให้บุคคลที่ไม่เกี่ยวข้อง และไม่ควรถ่ายภาพหน้าจอหน้านี้
            </div>
        </div>
    </div>
</div>

<script>
function togglePass(id, value, btn) {
    const el = document.getElementById(id);
    if (el.textContent.includes('•')) {
        el.textContent = value;
        btn.textContent = '🙈';
    } else {
        el.textContent = '•'.repeat(value.length);
        btn.textContent = '👁️';
    }
}

function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✅ คัดลอกแล้ว';
        btn.style.color = '#059669';
        btn.style.borderColor = '#059669';
        setTimeout(() => {
            btn.textContent = orig;
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2000);
    });
}
</script>
