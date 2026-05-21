<?php
require_once 'db.php';
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? '';
$fullName = $_SESSION['full_name'] ?? $username;
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Printer&Ink HP - Management System</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <!-- Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <!-- QR Code Generator -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Flatpickr (Date/Time Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
</head>

<body>
    <div id="app">
        <!-- Mobile Top Bar -->
        <div class="mobile-top-bar mobile-only">
            <div style="display: flex; align-items: center; gap: 12px;">
                <?php if($isLoggedIn): ?>
                <button onclick="toggleSidebar()" style="background: none; border: none; font-size: 1.5rem; color: var(--text-main); cursor: pointer;">☰</button>
                <?php endif; ?>
                <span style="font-weight: 700; font-size: 1.15rem; color: var(--primary-dark);">Printer&Ink</span>
            </div>
            <button class="btn-primary scan-trigger" style="padding: 6px 12px; font-size: 0.85rem;">
                <span class="icon">📷</span> แสกน
            </button>
        </div>

        <!-- Sidebar -->
        <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
        <aside id="app-sidebar" class="app-sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo">
                    <span class="logo-icon">🖨️</span>
                    <span class="logo-text">Printer&Ink HP</span>
                </a>
                <button class="close-sidebar-btn mobile-only" onclick="toggleSidebar()">✕</button>
            </div>
            
            <?php if($isLoggedIn): ?>
            <div class="sidebar-user">
                <div class="user-avatar">👤</div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($fullName ?: $username) ?></div>
                    <div class="user-role"><?= $userRole === 'admin' ? 'Administrator' : 'User' ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="sidebar-scrollable">
                <?php if($isLoggedIn): ?>
                <nav class="sidebar-nav">
                    <div class="nav-section-title">เมนูหลัก</div>
                    <a href="#dashboard" class="nav-item active" data-page="dashboard"><span class="icon">🏠</span> แดชบอร์ด</a>
                    <a href="#printers" class="nav-item" data-page="printers"><span class="icon">🖨️</span> ปริ้นเตอร์</a>
                    <a href="#ink" class="nav-item" data-page="ink"><span class="icon">📦</span> คลังหมึก</a>
                    <a href="#ink_history" class="nav-item" data-page="ink_history"><span class="icon">🧾</span> ประวัติการเบิกหมึก</a>
                    <a href="#maintenance" class="nav-item" data-page="maintenance"><span class="icon">🔧</span> ประวัติการซ่อม</a>
                    <a href="#analysis" class="nav-item" data-page="analysis"><span class="icon">📊</span> วิเคราะห์ข้อมูล</a>
                    
                    <div class="nav-section-title" style="margin-top: 15px;">ระบบ</div>
                    <a href="#trash" class="nav-item" data-page="trash"><span class="icon">🗑️</span> ถังขยะ</a>
                    <?php if($userRole === 'admin'): ?>
                        <a href="#departments" class="nav-item" data-page="departments"><span class="icon">🏢</span> จัดการหน่วยงาน</a>
                        <a href="#users" class="nav-item" data-page="users"><span class="icon">👥</span> จัดการผู้ใช้</a>
                        <a href="#backup" class="nav-item" data-page="backup"><span class="icon">💾</span> สำรองฐานข้อมูล</a>
                        <a href="#server_info" class="nav-item" data-page="server_info"><span class="icon">🖥️</span> ข้อมูล Server</a>
                    <?php endif; ?>
                </nav>
                <?php else: ?>
                <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                    <p>โหมดสิทธิ์อ่านเท่านั้น</p>
                    <p style="font-size: 0.8rem; margin-top: 8px;">ฟังก์ชันเมนูจะถูกซ่อนไว้</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="sidebar-footer">
                <button class="btn-primary scan-trigger desktop-only" style="width: 100%; justify-content: center; margin-bottom: 12px; padding: 12px;">
                    <span class="icon">📷</span> แสกน QR Code
                </button>
                <?php if($isLoggedIn): ?>
                <button onclick="handleLogout()" class="btn-secondary" style="width: 100%; justify-content: center; border-color: var(--border); color: var(--danger);">
                    <span class="icon">🚪</span> ออกระบบ
                </button>
                <?php else: ?>
                <button onclick="document.getElementById('login-overlay').style.display='flex'" class="btn-primary" style="width: 100%; justify-content: center;">
                    <span class="icon">🔑</span> เข้าสู่ระบบ
                </button>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main id="main-content">
            <!-- Loader -->
            <div id="page-loader" class="loader-container">
                <div class="loader"></div>
            </div>
            <!-- Dynamic Content Container -->
            <div id="page-container"></div>
        </main>

        <!-- Removed Mobile Floating Tab Bar -->

        <!-- QR Scanner Modal -->
        <div id="scanner-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 style="margin:0">แสกน QR หรือ Barcode</h3>
                    <button class="close-modal"
                        style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="reader" style="width:100%"></div>
                    <div class="scanner-info"
                        style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                        <p>แสกน QR ประจำเครื่องปริ้นเพื่อดูประวัติและเบิกหมึก</p>
                        <p>หรือแสกน Barcode ข้างกล่องหมึกเพื่อรับเข้าสต๊อก</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Thai BE Date Helpers (JS) -->
    <script>
        /**
         * แปลงวันที่เป็น พ.ศ. สำหรับ JavaScript
         * @param {string} dateStr - วันที่ใดก็ได้ที่ JS เข้าใจ
         * @param {boolean} withTime - แสดงเวลาด้วยไหม
         */
        window.thdate = function(dateStr, withTime = false) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            if (isNaN(d)) return '—';
            const dd   = String(d.getDate()).padStart(2, '0');
            const mm   = String(d.getMonth() + 1).padStart(2, '0');
            const yyyy = d.getFullYear() + 543;
            let result = `${dd}/${mm}/${yyyy}`;
            if (withTime) {
                const hh = String(d.getHours()).padStart(2, '0');
                const min = String(d.getMinutes()).padStart(2, '0');
                result += ` ${hh}:${min}`;
            }
            return result;
        };
        window.thdatetime = (s) => window.thdate(s, true);

        const updateYear = function(selectedDates, dateStr, instance) {
            if (!instance || !instance.currentYearElement) return;
            const yearInput = instance.currentYearElement;
            if (parseInt(yearInput.value) < 2500) {
                yearInput.value = parseInt(yearInput.value) + 543;
            }
        };

        // ตั้งค่า Flatpickr ให้แสดงผลเป็น พ.ศ. ทุกจุด
        flatpickr.setDefaults({
            locale: "th",
            altInput: true,
            altFormat: "d M Y",
            dateFormat: "Y-m-d",
            formatDate: function (date, format, locale) {
                if (format === "Y-m-d" || format === "H:i" || format === "Y-m-d H:i") {
                    return flatpickr.formatDate(date, format);
                }
                if (format === "d M Y") {
                    const months = ["ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
                    return `${date.getDate().toString().padStart(2, '0')} ${months[date.getMonth()]} ${date.getFullYear() + 543}`;
                }
                const beYear = date.getFullYear() + 543;
                return flatpickr.formatDate(date, format).replace(date.getFullYear().toString(), beYear.toString());
            },
            onReady: updateYear,
            onOpen: updateYear,
            onYearChange: updateYear,
            onMonthChange: updateYear,
            onValueUpdate: updateYear
        });
    </script>
    <!-- App Logic -->
    <script src="assets/js/app.js?v=<?= time() ?>"></script>

    <!-- ===== Global Toast & Confirm System ===== -->
    <div id="toast-container" style="
        position: fixed; top: 20px; right: 16px; z-index: 99999;
        display: flex; flex-direction: column; gap: 10px;
        pointer-events: none;
        max-width: min(360px, calc(100vw - 32px));
    "></div>

    <!-- Login Overlay -->
    <?php if(!$isLoggedIn): ?>
    <div id="login-overlay" style="display: flex; position: fixed; inset: 0; background: rgba(15,23,42,0.85); backdrop-filter: blur(12px); z-index: 999999; align-items: center; justify-content: center; padding: 20px;">
        <div class="card" style="max-width: 400px; width: 100%; text-align: center; animation: confirmPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <div style="font-size: 3rem; margin-bottom: 10px;">🔐</div>
            <h2 style="margin-bottom: 5px;">เข้าสู่ระบบ</h2>
            <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">กรุณาเข้าสู่ระบบเพื่อจัดการข้อมูล</p>
            
            <form id="login-form" onsubmit="handleLoginSubmit(event)">
                <div class="form-group" style="text-align: left;">
                    <label class="form-label">ชื่อผู้ใช้งาน</label>
                    <input type="text" id="login-username" class="form-input" required>
                </div>
                <div class="form-group" style="text-align: left;">
                    <label class="form-label">รหัสผ่าน</label>
                    <div style="position: relative;">
                        <input type="password" id="login-password" class="form-input" style="padding-right: 40px;" required>
                        <button type="button" onclick="togglePassword('login-password', this)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2rem; color: var(--text-muted); padding: 0;">👁️</button>
                    </div>
                </div>
                <div class="form-group" style="text-align: left; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="login-remember" style="width: 18px; height: 18px; cursor: pointer;">
                    <label for="login-remember" style="font-size: 0.9rem; color: var(--text-muted); cursor: pointer;">จดจำการเข้าสู่ระบบ (1 วัน)</label>
                </div>
                <div id="login-error" style="display:none; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#b91c1c; border-radius:12px; padding:10px 14px; font-size:0.88rem; font-weight:600; margin-bottom:12px; text-align:left; align-items:center; gap:8px;">
                    <span>⚠️</span><span id="login-error-msg"></span>
                </div>
                <button type="submit" id="login-submit-btn" class="btn-primary" style="width: 100%; margin-top: 10px; padding: 12px; font-size: 1rem;">เข้าสู่ระบบ</button>
            </form>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                <button onclick="document.getElementById('login-overlay').style.display='none'" style="background: none; border: none; width: 100%; padding: 12px; color: var(--text-muted); cursor: pointer; font-size: 0.95rem;">
                    👀 สิทธิ์อ่านเท่านั้น (Read Only)
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Read-only mode: ซ่อนปุ่มต่างๆ ที่ใช้สำหรับแก้ไขข้อมูล
    const roObserver = new MutationObserver(() => {
        document.querySelectorAll('button').forEach(btn => {
            const text = btn.textContent || '';
            const isActionBtn = text.includes('แก้ไข') || text.includes('ลบ') || text.includes('เพิ่ม') || text.includes('ลงทะเบียน') || text.includes('รับเข้า') || text.includes('บันทึก') || text.includes('ถังขยะ');
            if (isActionBtn && !btn.closest('#login-overlay') && btn.id !== 'scan-btn-nav' && !btn.classList.contains('scan-trigger')) {
                btn.style.display = 'none';
            }
        });
        document.querySelectorAll('.edit-log-btn').forEach(b => b.style.display = 'none');
    });
    roObserver.observe(document.body, { childList: true, subtree: true });
    </script>
    <?php endif; ?>

    <script>
    function toggleSidebar() {
        document.getElementById('app-sidebar').classList.toggle('active');
        document.querySelector('.sidebar-overlay').classList.toggle('active');
    }

    // ปิด Sidebar อัตโนมัติเมื่อกดลิงก์ (เฉพาะในมือถือ)
    document.querySelectorAll('.nav-item').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                document.getElementById('app-sidebar').classList.remove('active');
                document.querySelector('.sidebar-overlay').classList.remove('active');
            }
        });
    });

    window.togglePassword = function(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = '🙈';
        } else {
            input.type = 'password';
            btn.textContent = '👁️';
        }
    };

    async function handleLoginSubmit(e) {
        e.preventDefault();
        const u = document.getElementById('login-username').value;
        const p = document.getElementById('login-password').value;
        const r = document.getElementById('login-remember').checked;
        const submitBtn = document.getElementById('login-submit-btn');
        const errorBox  = document.getElementById('login-error');
        const errorMsg  = document.getElementById('login-error-msg');

        // ซ่อน error เก่า + ปิดปุ่ม
        errorBox.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.textContent = 'กำลังเข้าสู่ระบบ...';

        const formData = new FormData();
        formData.append('username', u);
        formData.append('password', p);
        formData.append('remember', r ? '1' : '0');
        
        try {
            const res = await fetch('api.php?action=login', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
                window.location.reload();
            } else {
                // แสดง error inline
                errorMsg.textContent = result.message || 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง';
                errorBox.style.display = 'flex';
                // Shake animation
                const card = errorBox.closest('.card');
                if (card) {
                    card.style.animation = 'none';
                    card.offsetHeight; // reflow
                    card.style.animation = 'loginShake 0.4s ease';
                }
                // เคลียร์รหัสผ่าน
                document.getElementById('login-password').value = '';
                document.getElementById('login-password').focus();
                submitBtn.disabled = false;
                submitBtn.textContent = 'เข้าสู่ระบบ';
            }
        } catch (err) {
            errorMsg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่';
            errorBox.style.display = 'flex';
            submitBtn.disabled = false;
            submitBtn.textContent = 'เข้าสู่ระบบ';
        }
    }

    async function handleLogout() {
        if(await showConfirm({title:'ออกระบบ?', message:'คุณต้องการออกจากระบบหรือไม่?', confirmText:'ออกจากระบบ', type:'warning'})) {
            await fetch('api.php?action=logout');
            window.location.reload();
        }
    }
    </script>

    <!-- Custom Confirm Dialog -->
    <div id="confirm-overlay" style="
        display:none; position:fixed; inset:0;
        background:rgba(15,23,42,0.65); backdrop-filter:blur(8px);
        z-index:99998; align-items:center; justify-content:center; padding:20px;
    ">
        <div id="confirm-box" style="
            background:white; border-radius:24px;
            padding:32px 28px; max-width:380px; width:100%;
            box-shadow:0 24px 60px rgba(0,0,0,0.22);
            animation: confirmPop .28s cubic-bezier(.34,1.56,.64,1);
            text-align:center;
        ">
            <div id="confirm-icon" style="font-size:2.8rem; margin-bottom:12px; line-height:1"></div>
            <h3 id="confirm-title" style="margin:0 0 8px; font-size:1.1rem; color:#0f172a"></h3>
            <p id="confirm-msg" style="margin:0 0 28px; font-size:0.9rem; color:#64748b; line-height:1.55"></p>
            <div style="display:flex; gap:12px; justify-content:center">
                <button id="confirm-cancel" style="
                    flex:1; padding:13px; border:1.5px solid #e2e8f0;
                    border-radius:14px; background:white; font-size:0.9rem;
                    font-weight:600; cursor:pointer; color:#64748b;
                    font-family:inherit; transition:all .2s;
                " onmouseenter="this.style.background='#f8fafc'" onmouseleave="this.style.background='white'">ยกเลิก</button>
                <button id="confirm-ok" style="
                    flex:1; padding:13px; border:none;
                    border-radius:14px; font-size:0.9rem;
                    font-weight:700; cursor:pointer; color:white;
                    font-family:inherit; transition:all .2s;
                "></button>
            </div>
        </div>
    </div>

    <style>
    @keyframes toastSlideIn {
        from { opacity:0; transform:translateX(110%); }
        to   { opacity:1; transform:translateX(0); }
    }
    @keyframes toastSlideOut {
        from { opacity:1; transform:translateX(0); }
        to   { opacity:0; transform:translateX(110%); }
    }
    @keyframes loginShake {
        0%,100% { transform: translateX(0); }
        20%     { transform: translateX(-8px); }
        40%     { transform: translateX(8px); }
        60%     { transform: translateX(-5px); }
        80%     { transform: translateX(5px); }
    }
    @keyframes confirmPop {
        from { opacity:0; transform:scale(.85) translateY(16px); }
        to   { opacity:1; transform:scale(1) translateY(0); }
    }
    .toast-item {
        pointer-events: auto;
        display: flex; align-items: flex-start; gap: 12px;
        padding: 14px 16px;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.14);
        backdrop-filter: blur(12px);
        animation: toastSlideIn .35s cubic-bezier(.34,1.56,.64,1) forwards;
        min-width: 260px;
        border: 1px solid rgba(255,255,255,0.4);
        position: relative;
        overflow: hidden;
    }
    .toast-item::before {
        content: '';
        position: absolute; left: 0; top: 0; bottom: 0;
        width: 4px;
        border-radius: 16px 0 0 16px;
    }
    .toast-success { background: rgba(240,253,248,.97); color:#065f46; }
    .toast-success::before { background:#10b981; }
    .toast-error   { background: rgba(255,241,242,.97); color:#9f1239; }
    .toast-error::before   { background:#ef4444; }
    .toast-warning { background: rgba(255,251,235,.97); color:#92400e; }
    .toast-warning::before { background:#f59e0b; }
    .toast-info    { background: rgba(239,246,255,.97); color:#1e40af; }
    .toast-info::before    { background:#6366f1; }
    .toast-icon { font-size: 1.35rem; flex-shrink:0; line-height:1; margin-top:1px; }
    .toast-body { flex:1; min-width:0; }
    .toast-body strong { display:block; font-size:.9rem; font-weight:700; margin-bottom:2px; }
    .toast-body span   { display:block; font-size:.8rem; opacity:.85; line-height:1.4; }
    .toast-close {
        background:none; border:none; cursor:pointer; opacity:.5;
        font-size:1rem; line-height:1; flex-shrink:0; padding:0;
        transition:opacity .2s; color:currentColor; margin-top:1px;
    }
    .toast-close:hover { opacity:1; }
    .toast-progress {
        position:absolute; bottom:0; left:0; height:3px;
        background:currentColor; opacity:.25;
        animation:toastProgress linear forwards;
    }
    @keyframes toastProgress {
        from { width:100%; } to { width:0%; }
    }
    @media (max-width:480px) {
        #toast-container { top:auto; bottom:84px; right:12px; left:12px; }
        @keyframes toastSlideIn {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }
    }
    </style>

    <script>
    // ========================================================
    // Global showToast(message, type, title, duration)
    // type: 'success' | 'error' | 'warning' | 'info'
    // ========================================================
    window.showToast = function(message, type = 'success', title = '', duration = 2000) {
        const icons = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️' };
        const titles = { success:'สำเร็จ', error:'เกิดข้อผิดพลาด', warning:'แจ้งเตือน', info:'ข้อมูล' };
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast-item toast-${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <div class="toast-body">
                <strong>${title || titles[type] || ''}</strong>
                <span>${message}</span>
            </div>
            <button class="toast-close" onclick="this.closest('.toast-item').remove()">✕</button>
            <div class="toast-progress" style="animation-duration:${duration}ms"></div>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = `toastSlideOut .3s ease forwards`;
            setTimeout(() => toast.remove(), 310);
        }, duration);
    };

    // ========================================================
    // Global showConfirm(options) → Promise<boolean>
    // options: { title, message, confirmText, cancelText, type }
    // ========================================================
    window.showConfirm = function({ title='ยืนยัน?', message='', confirmText='ยืนยัน', cancelText='ยกเลิก', type='warning' } = {}) {
        return new Promise((resolve) => {
            const overlay = document.getElementById('confirm-overlay');
            const icons   = { warning:'⚠️', danger:'🗑️', info:'💡', success:'✅' };
            const colors  = { warning:'#f59e0b', danger:'#ef4444', info:'#6366f1', success:'#10b981' };

            document.getElementById('confirm-icon').textContent  = icons[type]  || icons.warning;
            document.getElementById('confirm-title').textContent = title;
            document.getElementById('confirm-msg').textContent   = message;

            const okBtn = document.getElementById('confirm-ok');
            okBtn.textContent = confirmText;
            okBtn.style.background = `linear-gradient(135deg, ${colors[type]||colors.warning}, ${colors[type]||colors.warning}cc)`;

            overlay.style.display = 'flex';

            const cleanup = (val) => {
                overlay.style.display = 'none';
                okBtn.onclick = null;
                document.getElementById('confirm-cancel').onclick = null;
                resolve(val);
            };
            okBtn.onclick = () => cleanup(true);
            document.getElementById('confirm-cancel').onclick = () => cleanup(false);
            overlay.onclick = (e) => { if (e.target === overlay) cleanup(false); };
        });
    };
    </script>

    <!-- Polyfill สำหรับลากรูปภาพบนมือถือ (Touch Drag and Drop) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/default.min.css">
    <script src="https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/scroll-behaviour.min.js"></script>
    <script>
        MobileDragDrop.polyfill({
            dragImageTranslateOverride: MobileDragDrop.scrollBehaviourDragImageTranslateOverride,
            holdToDrag: 300 // กดค้าง 300ms เพื่อลาก (ป้องกันการลากมั่วตอนเลื่อนจอ)
        });
        // ป้องกัน Safari ไม่ให้ scroll ตอนลากรูป
        window.addEventListener('touchmove', function() {}, {passive: false});
    </script>
</body>

</html>