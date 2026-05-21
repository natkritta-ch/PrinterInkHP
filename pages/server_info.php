<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="margin:0; display:flex; align-items:center; gap:10px;">
            <span>🖥️</span> ข้อมูล Server
        </h1>
        <p style="color:var(--text-muted); margin:6px 0 0; font-size:0.88rem;">
            ข้อมูลการเชื่อมต่อ Server ภายในองค์กร (เฉพาะผู้ดูแลระบบเท่านั้น)
        </p>
    </div>
    <div style="display:flex; align-items:center; gap:8px; background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2); border-radius:12px; padding:8px 16px;">
        <span style="font-size:0.85rem; color:var(--primary); font-weight:600;">🔒 Admin Only</span>
    </div>
</div>

<!-- ===== SERVER หลัก (PRODUCTION) ===== -->
<div style="margin-bottom:12px; display:flex; align-items:center; gap:10px;">
    <div style="width:10px; height:10px; border-radius:50%; background:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,0.2);"></div>
    <span style="font-weight:700; font-size:0.82rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted);">Server หลัก (PRODUCTION)</span>
</div>

<div class="card" style="margin-bottom:28px; border:1.5px solid rgba(16,185,129,0.25); border-radius:20px; padding:28px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="width:56px; height:56px; border-radius:16px; background:linear-gradient(135deg,#6366f1,#818cf8); display:flex; align-items:center; justify-content:center; font-size:1.6rem; flex-shrink:0;">🖥️</div>
            <div>
                <div style="font-weight:800; font-size:1.15rem; color:var(--text-main);">Main Server</div>
                <div style="font-size:0.83rem; color:var(--text-muted); margin-top:2px;">เซิร์ฟเวอร์หลักของระบบ</div>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:6px; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); padding:6px 14px; border-radius:20px;">
            <div style="width:7px; height:7px; border-radius:50%; background:#10b981;"></div>
            <span style="font-size:0.82rem; font-weight:700; color:#059669;">Online</span>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:20px; margin-top:24px;">
        <!-- IP -->
        <div style="background:var(--bg-card,#f8fafc); border:1px solid var(--border); border-radius:14px; padding:16px 18px;">
            <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                🌐 IP ADDRESS
            </div>
            <div id="prod-ip-display" style="font-size:1.05rem; font-weight:700; color:#6366f1; font-family:monospace; letter-spacing:0.5px;">192.168.9.230</div>
            <button onclick="copyText('192.168.9.230','prod-ip-copy')" id="prod-ip-copy"
                style="margin-top:10px; background:none; border:none; cursor:pointer; font-size:0.78rem; color:var(--text-muted); display:flex; align-items:center; gap:4px; padding:0; transition:color 0.2s;"
                onmouseenter="this.style.color='var(--primary)'" onmouseleave="this.style.color='var(--text-muted)'">
                📋 คัดลอก
            </button>
        </div>
        <!-- USERNAME -->
        <div style="background:var(--bg-card,#f8fafc); border:1px solid var(--border); border-radius:14px; padding:16px 18px;">
            <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                👤 USERNAME
            </div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--text-main); font-family:monospace;">root</div>
            <button onclick="copyText('root','prod-user-copy')" id="prod-user-copy"
                style="margin-top:10px; background:none; border:none; cursor:pointer; font-size:0.78rem; color:var(--text-muted); display:flex; align-items:center; gap:4px; padding:0; transition:color 0.2s;"
                onmouseenter="this.style.color='var(--primary)'" onmouseleave="this.style.color='var(--text-muted)'">
                📋 คัดลอก
            </button>
        </div>
        <!-- PASSWORD -->
        <div style="background:var(--bg-card,#f8fafc); border:1px solid var(--border); border-radius:14px; padding:16px 18px;">
            <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                🔑 PASSWORD
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <div id="prod-pass-text" style="font-size:1.05rem; font-weight:700; color:var(--text-main); font-family:monospace; letter-spacing:3px;">••••••••••</div>
                <button onclick="togglePass('prod-pass-text','phan11190','prod-eye')" id="prod-eye"
                    style="background:none; border:none; cursor:pointer; font-size:1rem; padding:0; opacity:0.5; transition:opacity 0.2s;"
                    onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='0.5'">👁️</button>
            </div>
            <button onclick="copyText('phan11190','prod-pass-copy')" id="prod-pass-copy"
                style="margin-top:10px; background:none; border:none; cursor:pointer; font-size:0.78rem; color:var(--text-muted); display:flex; align-items:center; gap:4px; padding:0; transition:color 0.2s;"
                onmouseenter="this.style.color='var(--primary)'" onmouseleave="this.style.color='var(--text-muted)'">
                📋 คัดลอก
            </button>
        </div>
    </div>
</div>

<!-- ===== SERVER ทดสอบ (TEST) ===== -->
<div style="margin-bottom:12px; display:flex; align-items:center; gap:10px;">
    <div style="width:10px; height:10px; border-radius:50%; background:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,0.2);"></div>
    <span style="font-weight:700; font-size:0.82rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted);">Server ทดสอบ (TEST)</span>
</div>

<div class="card" style="margin-bottom:28px; border:1.5px solid rgba(245,158,11,0.25); border-radius:20px; padding:28px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="width:56px; height:56px; border-radius:16px; background:linear-gradient(135deg,#f59e0b,#fbbf24); display:flex; align-items:center; justify-content:center; font-size:1.6rem; flex-shrink:0;">🔧</div>
            <div>
                <div style="font-weight:800; font-size:1.15rem; color:var(--text-main);">Server 102 (Natkritta)</div>
                <div style="font-size:0.83rem; color:var(--text-muted); margin-top:2px;">เซิร์ฟเวอร์สำหรับทดสอบระบบ</div>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:6px; background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3); padding:6px 14px; border-radius:20px;">
            <div style="width:7px; height:7px; border-radius:50%; background:#f59e0b;"></div>
            <span style="font-size:0.82rem; font-weight:700; color:#b45309;">Test</span>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:20px; margin-top:24px;">
        <!-- SERVER -->
        <div style="background:var(--bg-card,#f8fafc); border:1px solid var(--border); border-radius:14px; padding:16px 18px;">
            <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                🖥️ SERVER
            </div>
            <div style="font-size:1.05rem; font-weight:700; color:#d97706; font-family:monospace;">102</div>
            <button onclick="copyText('102','test-server-copy')" id="test-server-copy"
                style="margin-top:10px; background:none; border:none; cursor:pointer; font-size:0.78rem; color:var(--text-muted); display:flex; align-items:center; gap:4px; padding:0; transition:color 0.2s;"
                onmouseenter="this.style.color='var(--primary)'" onmouseleave="this.style.color='var(--text-muted)'">
                📋 คัดลอก
            </button>
        </div>
        <!-- USERNAME -->
        <div style="background:var(--bg-card,#f8fafc); border:1px solid var(--border); border-radius:14px; padding:16px 18px;">
            <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                👤 USERNAME
            </div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--text-main); font-family:monospace;">phan</div>
            <button onclick="copyText('phan','test-user-copy')" id="test-user-copy"
                style="margin-top:10px; background:none; border:none; cursor:pointer; font-size:0.78rem; color:var(--text-muted); display:flex; align-items:center; gap:4px; padding:0; transition:color 0.2s;"
                onmouseenter="this.style.color='var(--primary)'" onmouseleave="this.style.color='var(--text-muted)'">
                📋 คัดลอก
            </button>
        </div>
        <!-- PASSWORD -->
        <div style="background:var(--bg-card,#f8fafc); border:1px solid var(--border); border-radius:14px; padding:16px 18px;">
            <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                🔑 PASSWORD
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <div id="test-pass-text" style="font-size:1.05rem; font-weight:700; color:var(--text-main); font-family:monospace; letter-spacing:3px;">•••••</div>
                <button onclick="togglePass('test-pass-text','11190','test-eye')" id="test-eye"
                    style="background:none; border:none; cursor:pointer; font-size:1rem; padding:0; opacity:0.5; transition:opacity 0.2s;"
                    onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='0.5'">👁️</button>
            </div>
            <button onclick="copyText('11190','test-pass-copy')" id="test-pass-copy"
                style="margin-top:10px; background:none; border:none; cursor:pointer; font-size:0.78rem; color:var(--text-muted); display:flex; align-items:center; gap:4px; padding:0; transition:color 0.2s;"
                onmouseenter="this.style.color='var(--primary)'" onmouseleave="this.style.color='var(--text-muted)'">
                📋 คัดลอก
            </button>
        </div>
    </div>
</div>

<!-- หมายเหตุ -->
<div style="background:rgba(99,102,241,0.04); border:1px solid rgba(99,102,241,0.15); border-radius:14px; padding:16px 20px; display:flex; align-items:flex-start; gap:12px;">
    <span style="font-size:1.2rem; flex-shrink:0;">ℹ️</span>
    <div>
        <div style="font-weight:600; font-size:0.88rem; color:var(--primary); margin-bottom:4px;">หมายเหตุ</div>
        <div style="font-size:0.83rem; color:var(--text-muted); line-height:1.6;">
            ข้อมูลในหน้านี้เป็นความลับ กรุณาอย่าเปิดเผยต่อบุคคลภายนอก<br>
            หากต้องการแก้ไขข้อมูล Server กรุณาติดต่อผู้ดูแลระบบ
        </div>
    </div>
</div>

<script>
    // Toggle show/hide password
    window.togglePass = function(elId, realVal, btnId) {
        const el  = document.getElementById(elId);
        const btn = document.getElementById(btnId);
        if (!el) return;
        if (el.dataset.shown === '1') {
            el.textContent = '•'.repeat(realVal.length);
            el.dataset.shown = '0';
            btn.textContent = '👁️';
        } else {
            el.textContent = realVal;
            el.dataset.shown = '1';
            btn.textContent = '🙈';
        }
    };

    // Copy to clipboard
    window.copyText = function(text, btnId) {
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.getElementById(btnId);
            if (!btn) return;
            const orig = btn.innerHTML;
            btn.innerHTML = '✅ คัดลอกแล้ว';
            btn.style.color = '#10b981';
            setTimeout(() => {
                btn.innerHTML = orig;
                btn.style.color = '';
            }, 1800);
        }).catch(() => {
            // fallback สำหรับ browser เก่า
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast('คัดลอกแล้ว', 'success', '', 1500);
        });
    };
</script>
