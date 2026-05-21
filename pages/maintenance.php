<?php
require_once '../db.php';

try {
    // เตรียม column deleted_at ถ้ายังไม่มี เพื่อป้องกัน error
    try { $pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch (Exception $e) {}

    // ดึงข้อมูลประวัติการซ่อมทั้งหมด พร้อมหน่วยงาน
    $stmt = $pdo->query("
        SELECT ml.*, p.brand, p.model, p.serial_number, p.department
        FROM maintenance_logs ml 
        JOIN printers p ON ml.printer_id = p.id 
        WHERE ml.deleted_at IS NULL
        AND p.deleted_at IS NULL
        ORDER BY ml.repair_date DESC, ml.repair_time DESC
    ");
    $logs = $stmt->fetchAll();

    // รายชื่อหน่วยงาน unique
    $depts = [];
    foreach ($logs as $log) {
        $d = $log['department'] ?? '';
        if ($d && !in_array($d, $depts)) $depts[] = $d;
    }
    sort($depts);
} catch (Exception $e) {
    $logs = [];
    $depts = [];
    $error = $e->getMessage();
}
?>

<?php
// ส่งข้อมูล logs ทั้งหมดไปยัง JS เพื่อวาดกราฟ
$logsJson = json_encode(array_map(function($l) {
    return [
        'date'   => $l['repair_date'],
        'dept'   => $l['department'] ?? 'ไม่ระบุ',
        'status' => $l['status_after_repair'] ?? '',
    ];
}, $logs));
?>

<div class="page-header" style="margin-bottom: 16px">
    <h1>ประวัติการซ่อมบำรุง 🔧</h1>
    <p style="color: var(--text-muted)">รายการแจ้งซ่อมและสถานะการดำเนินการทั้งหมด</p>
</div>

<!-- ===== Analytics Dashboard ===== -->
<div class="card" style="margin-bottom: 24px; padding: 24px">

    <!-- Period Tabs + Date Picker -->
    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 20px">
        <div style="display: flex; background: #f1f5f9; border-radius: 12px; padding: 4px; gap: 4px">
            <?php foreach (['week' => 'สัปดาห์นี้', 'month' => 'เดือนนี้', 'year' => 'ปีนี้', 'custom' => 'เลือกวัน'] as $k => $label): ?>
                <button class="period-tab <?= $k === 'month' ? 'active' : '' ?>" data-period="<?= $k ?>"
                    style="padding: 8px 16px; border: none; border-radius: 10px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
                           background: <?= $k === 'month' ? 'white' : 'transparent' ?>;
                           color: <?= $k === 'month' ? 'var(--primary)' : 'var(--text-muted)' ?>;
                           box-shadow: <?= $k === 'month' ? '0 2px 8px rgba(0,0,0,0.1)' : 'none' ?>">
                    <?= $label ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Custom Date Range (Flatpickr) -->
        <div id="custom-date-row" style="display: none; align-items: center; gap: 8px; flex-wrap: wrap">
            <input type="text" id="chart-date-start" placeholder="วันเริ่มต้น"
                style="padding: 8px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.9rem; font-family: inherit; outline: none; width: 140px">
            <span style="color: var(--text-muted)">—</span>
            <input type="text" id="chart-date-end" placeholder="วันสิ้นสุด"
                style="padding: 8px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.9rem; font-family: inherit; outline: none; width: 140px">
            <button onclick="applyCustomDate()" class="btn-primary" style="padding: 8px 16px; font-size: 0.88rem">ดูกราฟ</button>
        </div>

        <div style="margin-left: auto; font-size: 0.85rem; color: var(--text-muted)" id="chart-period-label"></div>
    </div>

    <!-- Stats Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 24px">
        <div style="text-align: center; padding: 14px; background: rgba(99,102,241,0.06); border-radius: 14px">
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary)" id="stat-total">—</div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px">ครั้งทั้งหมด</div>
        </div>
        <div style="text-align: center; padding: 14px; background: rgba(16,185,129,0.06); border-radius: 14px">
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--success)" id="stat-normal">—</div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px">ซ่อมสำเร็จ</div>
        </div>
        <div style="text-align: center; padding: 14px; background: rgba(245,158,11,0.06); border-radius: 14px">
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--warning)" id="stat-repairing">—</div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px">กำลังซ่อม</div>
        </div>
        <div style="text-align: center; padding: 14px; background: rgba(239,68,68,0.06); border-radius: 14px">
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--danger)" id="stat-broken">—</div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px">พัง</div>
        </div>
    </div>

    <!-- Charts: Layout = Horizontal bar (scrollable) + doughnut side by side -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;align-items:start">

        <!-- Dept Bar Chart (Horizontal, Scrollable) -->
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px">ความถี่การซ่อมรายหน่วยงาน</div>
                <div style="display: flex; align-items: center; gap: 8px">
                    <span style="font-size: 0.8rem; color: var(--text-muted)">แสดง Top</span>
                    <select id="topn-select" style="padding: 4px 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; outline: none; cursor: pointer">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="0">ทั้งหมด</option>
                    </select>
                    <span style="font-size: 0.8rem; color: var(--text-muted)">หน่วยงาน</span>
                </div>
            </div>
            <!-- Scrollable wrapper — grows dynamically with data -->
            <div id="chart-dept-wrapper" style="overflow-y: auto; max-height: 320px; border: 1px solid var(--border); border-radius: 14px; padding: 12px; background: #fafafa">
                <div style="position: relative" id="chart-dept-container">
                    <canvas id="chart-dept"></canvas>
                </div>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; text-align: right" id="dept-count-label"></div>
        </div>

        <!-- Doughnut Status Chart -->
        <div>
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px">สัดส่วนสถานะ</div>
            <div style="position: relative; height: 240px">
                <canvas id="chart-status"></canvas>
            </div>
        </div>
    </div>
</div>
<!-- ===== End Analytics ===== -->

<script>
(function() {
    const RAW = <?= $logsJson ?>;

    // === กำหนดช่วงวันตาม period ===
    function getDateRange(period, startStr, endStr) {
        const now = new Date();
        let from, to;
        if (period === 'week') {
            const day = now.getDay() || 7;
            from = new Date(now); from.setDate(now.getDate() - day + 1); from.setHours(0,0,0,0);
            to = new Date(from); to.setDate(from.getDate() + 6); to.setHours(23,59,59,999);
        } else if (period === 'month') {
            from = new Date(now.getFullYear(), now.getMonth(), 1);
            to   = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59);
        } else if (period === 'year') {
            from = new Date(now.getFullYear(), 0, 1);
            to   = new Date(now.getFullYear(), 11, 31, 23, 59, 59);
        } else if (period === 'custom' && startStr && endStr) {
            from = new Date(startStr); from.setHours(0,0,0,0);
            to   = new Date(endStr);   to.setHours(23,59,59,999);
        } else {
            return null;
        }
        return { from, to };
    }

    function fmt(d) {
        return d.toLocaleDateString('th-TH', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // === สร้าง/อัปเดตกราฟ ===
    let deptChart, statusChart;

    function renderCharts(period, startStr, endStr) {
        const range = getDateRange(period, startStr, endStr);

        // กรองข้อมูลตามช่วงวัน
        const filtered = range
            ? RAW.filter(r => {
                const d = new Date(r.date);
                return d >= range.from && d <= range.to;
            })
            : RAW;

        // อัปเดต label ช่วงวัน
        const label = range
            ? `${fmt(range.from)} — ${fmt(range.to)}`
            : 'ทั้งหมด';
        document.getElementById('chart-period-label').textContent = label;

        // Stats
        const total    = filtered.length;
        const normal   = filtered.filter(r => r.status === 'normal').length;
        const repairing= filtered.filter(r => r.status === 'repairing').length;
        const broken   = filtered.filter(r => r.status === 'broken').length;
        document.getElementById('stat-total').textContent     = total;
        document.getElementById('stat-normal').textContent    = normal;
        document.getElementById('stat-repairing').textContent = repairing;
        document.getElementById('stat-broken').textContent    = broken;

        // Dept count — all sorted
        const deptCount = {};
        filtered.forEach(r => { deptCount[r.dept] = (deptCount[r.dept] || 0) + 1; });
        const allDepts  = Object.keys(deptCount).sort((a,b) => deptCount[b] - deptCount[a]);
        const allCounts = allDepts.map(d => deptCount[d]);

        // Top N filter
        const topN = parseInt(document.getElementById('topn-select')?.value || '10');
        const depts  = topN > 0 ? allDepts.slice(0, topN)  : allDepts;
        const counts = topN > 0 ? allCounts.slice(0, topN) : allCounts;

        // Label count
        const labelEl = document.getElementById('dept-count-label');
        if (labelEl) labelEl.textContent = allDepts.length > depts.length
            ? `แสดง ${depts.length} จาก ${allDepts.length} หน่วยงาน (เรียงจากซ่อมบ่อยสุด)`
            : `ทั้งหมด ${allDepts.length} หน่วยงาน`;

        const palette = ['#6366f1','#8b5cf6','#4f46e5','#7c3aed','#a78bfa','#818cf8',
                         '#10b981','#f59e0b','#ef4444','#06b6d4','#f97316','#84cc16'];

        // --- Horizontal Bar Chart (indexAxis: 'y') ---
        // ความสูงขึ้นอยู่กับจำนวน dept — 36px/row
        const barH = Math.max(depts.length * 36, 80);
        const container = document.getElementById('chart-dept-container');
        if (container) container.style.height = barH + 'px';

        if (deptChart) deptChart.destroy();
        const ctx1 = document.getElementById('chart-dept').getContext('2d');
        deptChart = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: depts,
                datasets: [{
                    label: 'ครั้ง',
                    data: counts,
                    backgroundColor: depts.map((_, i) => palette[i % palette.length]),
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                indexAxis: 'y',   // <-- horizontal!
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.x} ครั้ง`
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 11 } },
                        grid: { color: '#f1f5f9' }
                    },
                    y: {
                        ticks: { font: { size: 12 }, color: '#374151' },
                        grid: { display: false }
                    }
                }
            }
        });

        // --- Doughnut Chart: status ---
        if (statusChart) statusChart.destroy();
        const ctx2 = document.getElementById('chart-status').getContext('2d');
        statusChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['ซ่อมสำเร็จ', 'กำลังซ่อม', 'พัง', 'ไม่ระบุ'],
                datasets: [{
                    data: [
                        normal, repairing, broken,
                        filtered.filter(r => !['normal','repairing','broken'].includes(r.status)).length
                    ],
                    backgroundColor: ['#10b981','#f59e0b','#ef4444','#94a3b8'],
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, font: { size: 12 } } }
                }
            }
        });
    }

    // === Period Tab Click ===
    let currentPeriod = 'month';
    let lastStart = '', lastEnd = '';

    document.querySelectorAll('.period-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.period-tab').forEach(t => {
                t.style.background = 'transparent';
                t.style.color = 'var(--text-muted)';
                t.style.boxShadow = 'none';
            });
            tab.style.background = 'white';
            tab.style.color = 'var(--primary)';
            tab.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
            currentPeriod = tab.dataset.period;
            const customRow = document.getElementById('custom-date-row');
            customRow.style.display = currentPeriod === 'custom' ? 'flex' : 'none';
            if (currentPeriod !== 'custom') { lastStart = ''; lastEnd = ''; renderCharts(currentPeriod); }
        });
    });

    // Top N select — re-render with current period/dates
    const topnSelect = document.getElementById('topn-select');
    if (topnSelect) {
        topnSelect.addEventListener('change', () => {
            renderCharts(currentPeriod, lastStart, lastEnd);
        });
    }

    // === Custom Date (Flatpickr) ===
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#chart-date-start', { disableMobile: true });
        flatpickr('#chart-date-end', { disableMobile: true });
    }

    window.applyCustomDate = function() {
        const s = document.getElementById('chart-date-start')._flatpickr?.input.value
                  || document.getElementById('chart-date-start').value;
        const e = document.getElementById('chart-date-end')._flatpickr?.input.value
                  || document.getElementById('chart-date-end').value;
        if (!s || !e) { showToast('กรุณาเลือกวันเริ่มต้นและวันสิ้นสุด', 'warning'); return; }
        lastStart = s; lastEnd = e;
        renderCharts('custom', s, e);
    };

    // Init
    renderCharts('month');
})();
</script>

<!-- Filter Bar -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
    <div style="position:relative;flex:1;min-width:180px">
        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none">🔍</span>
        <input type="text" id="maint-search" placeholder="ค้นหา ปริ้นเตอร์, อาการ, ช่าง..."
            class="form-input" style="padding-left:36px">
    </div>
    <select id="maint-dept-filter" class="form-input" style="min-width:160px;cursor:pointer">
        <option value="">📂 ทุกหน่วยงาน/กลุ่มงาน</option>
        <?php foreach ($depts as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
    </select>
    <div id="maint-result-count" style="font-size:0.82rem;color:var(--text-muted);white-space:nowrap"></div>
</div>

<div class="card">
    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch">
        <table style="width:100%;border-collapse:collapse;min-width:620px">
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid var(--border);">
                    <th style="padding: 12px;">วันที่/เวลา</th>
                    <th style="padding: 12px;">ปริ้นเตอร์</th>
                    <th style="padding: 12px;">อาการ</th>
                    <th style="padding: 12px;">การแก้ไข</th>
                    <th style="padding: 12px;">สถานะ</th>
                    <th style="padding: 12px;">หน่วยงาน</th>
                    <th style="padding: 12px;">ช่างผู้ดูแล</th>
                    <th style="padding: 12px; text-align: center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" style="padding: 40px; text-align: center; color: var(--text-muted);">
                            ไม่พบข้อมูลประวัติการซ่อม
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $rowSearch = strtolower(
                            ($log['brand'] ?? '') . ' ' . ($log['model'] ?? '') . ' ' .
                            ($log['serial_number'] ?? '') . ' ' . ($log['symptoms'] ?? '') . ' ' .
                            ($log['technician_name'] ?? '') . ' ' . ($log['department'] ?? '')
                        );
                    ?>
                        <tr class="maint-row" 
                            data-dept="<?= htmlspecialchars($log['department'] ?? '') ?>"
                            data-search="<?= htmlspecialchars($rowSearch) ?>"
                            style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 12px;">
                                <div><?= thdate($log['repair_date']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= $log['repair_time'] ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 600;"><?= $log['brand'] ?> <?= $log['model'] ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">SN: <?= $log['serial_number'] ?></div>
                            </td>
                            <td style="padding: 12px;"><?= htmlspecialchars($log['symptoms']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($log['action_taken']) ?></td>
                            <td style="padding: 12px;">
                                <?php 
                                    $s = $log['status_after_repair'] ?? '';
                                    if($s == 'normal') echo '<span class="badge badge-normal">ปกติ</span>';
                                    elseif($s == 'repairing') echo '<span class="badge badge-repairing">กำลังซ่อม</span>';
                                    elseif($s == 'broken') echo '<span class="badge badge-broken">พัง</span>';
                                    else echo '-';
                                ?>
                            </td>
                            <td style="padding: 12px;"><?= htmlspecialchars($log['department'] ?? '-') ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($log['technician_name']) ?></td>
                            <td style="padding: 12px; text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <button class="edit-log-btn" 
                                        data-id="<?= $log['id'] ?>"
                                        data-symptoms="<?= htmlspecialchars($log['symptoms']) ?>"
                                        data-action="<?= htmlspecialchars($log['action_taken']) ?>"
                                        data-tech="<?= htmlspecialchars($log['technician_name']) ?>"
                                        data-status="<?= $log['status_after_repair'] ?>"
                                        style="background: none; border: 1px solid var(--primary); color: var(--primary); padding: 4px 8px; border-radius: 6px; cursor: pointer; font-size: 0.8rem;">
                                        ✏️ แก้ไข
                                    </button>
                                    <button onclick="deleteMaintenanceLog(<?= $log['id'] ?>, this)"
                                        style="background: none; border: 1px solid var(--danger); color: var(--danger); padding: 4px 8px; border-radius: 6px; cursor: pointer; font-size: 0.8rem;">
                                        🗑️ ลบ
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal สำหรับแก้ไขประวัติการซ่อม -->
<div id="edit-maintenance-modal" class="modal">
    <div class="modal-content" style="max-width: 500px">
        <div class="modal-header">
            <h3>แก้ไขประวัติการซ่อม ✏️</h3>
            <button type="button" class="close-edit-modal-btn" class="close-modal-btn">&times;</button>
        </div>
        <form id="edit-maintenance-form">
            <input type="hidden" name="log_id" id="edit-log-id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">อาการเสีย/ปัญหา</label>
                    <textarea name="symptoms" id="edit-symptoms" rows="2" required class="form-input" style="font-family: inherit"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">วิธีแก้ไข/การดำเนินการ</label>
                    <textarea name="action_taken" id="edit-action" rows="2" class="form-input" style="font-family: inherit"></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px">
                    <div>
                        <label class="form-label">ช่างผู้ดูแล</label>
                        <input type="text" name="technician_name" id="edit-tech" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">สถานะหลังซ่อม</label>
                        <select name="status_after_repair" id="edit-status" class="form-input">
                            <option value="normal">ปกติ (ใช้งานได้)</option>
                            <option value="repairing">กำลังซ่อม</option>
                            <option value="broken">พัง (รอจำหน่าย)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" id="update-log-btn" class="btn-primary" style="width: 100%; justify-content: center; height: 50px">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const modal = document.getElementById('edit-maintenance-modal');
    const closeBtn = document.querySelector('.close-edit-modal-btn');
    const form = document.getElementById('edit-maintenance-form');
    
    document.querySelectorAll('.edit-log-btn').forEach(btn => {
        btn.onclick = () => {
            document.getElementById('edit-log-id').value = btn.dataset.id;
            document.getElementById('edit-symptoms').value = btn.dataset.symptoms;
            document.getElementById('edit-action').value = btn.dataset.action;
            document.getElementById('edit-tech').value = btn.dataset.tech;
            document.getElementById('edit-status').value = btn.dataset.status || 'normal';
            modal.style.display = 'block';
        };
    });

    closeBtn.onclick = () => modal.style.display = 'none';
    // ใช้ addEventListener แทน window.onclick เพื่อไม่ทับ global handler ของ app.js
    window.addEventListener('click', function _maintModalClose(e) {
        if (e.target === modal) modal.style.display = 'none';
    });

    form.onsubmit = async (e) => {
        e.preventDefault();
        
        const ok = await showConfirm({title: 'ยืนยันการบันทึก', message: 'ยืนยันการบันทึกการแก้ไขข้อมูลหรือไม่?', confirmText: 'บันทึก', type: 'info'});
        if (!ok) return;

        const formData = new FormData(form);
        try {
            const response = await fetch('api.php?action=edit_maintenance_log', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                showToast('แก้ไขข้อมูลเรียบร้อยแล้ว!', 'success');
                modal.style.display = 'none';
                // อัปเดต row ใน DOM ทันที ไม่ต้อง reload
                const logId = formData.get('log_id');
                const newSymptoms = formData.get('symptoms');
                const newAction = formData.get('action_taken');
                const newTech = formData.get('technician_name');
                const newStatus = formData.get('status_after_repair');
                const editBtn = document.querySelector(`.edit-log-btn[data-id="${logId}"]`);
                if (editBtn) {
                    const row = editBtn.closest('tr');
                    if (row) {
                        // อัปเดตข้อมูลบนปุ่มเพื่อใช้ครั้งต่อไป
                        editBtn.dataset.symptoms = newSymptoms;
                        editBtn.dataset.action    = newAction;
                        editBtn.dataset.tech      = newTech;
                        editBtn.dataset.status    = newStatus;
                        // อัปเดต cell อาการ
                        const cells = row.querySelectorAll('td');
                        if (cells[2]) cells[2].textContent = newSymptoms;
                        if (cells[3]) cells[3].textContent = newAction;
                        if (cells[6]) cells[6].textContent = newTech;
                        // อัปเดต badge สถานะ
                        if (cells[4]) {
                            const statusMap = {
                                normal: '<span class="badge badge-normal">ปกติ</span>',
                                repairing: '<span class="badge badge-repairing">กำลังซ่อม</span>',
                                broken: '<span class="badge badge-broken">พัง</span>'
                            };
                            cells[4].innerHTML = statusMap[newStatus] || '-';
                        }
                        // แสดง highlight flash
                        row.style.transition = 'background 0.3s';
                        row.style.background = 'rgba(99,102,241,0.08)';
                        setTimeout(() => { row.style.background = ''; }, 1000);
                    }
                }
            } else {
                showToast('เกิดข้อผิดพลาด: ' + result.message, 'error');
            }
        } catch (error) {
            console.error(error);
            showToast('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
        }
    };

    // ฟังก์ชันลบประวัติการซ่อม (Global)
    window.deleteMaintenanceLog = async (id, btn) => {
        const ok = await showConfirm({
            title: 'ลบประวัติการซ่อม?',
            message: 'คุณต้องการลบประวัติการซ่อมนี้ใช่หรือไม่?\nการกระทำนี้ไม่สามารถย้อนกลับได้',
            confirmText: '🗑️ ลบข้อมูล',
            type: 'danger'
        });
        
        if (ok) {
            try {
                const response = await fetch('api.php?action=delete_maintenance_log', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `log_id=${id}`
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast('ลบประวัติการซ่อมเรียบร้อยแล้ว', 'success');
                    // ลบ row ออกจาก DOM ทันที
                    const row = btn.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity 0.3s, transform 0.3s';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => row.remove(), 300);
                    }
                } else {
                    showToast('เกิดข้อผิดพลาด: ' + result.message, 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
            }
        }
    };
})();
</script>

<style>
.table-responsive {
    overflow-x: auto;
}
th {
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<script>
(function() {
    const searchInput = document.getElementById('maint-search');
    const deptSelect = document.getElementById('maint-dept-filter');
    const resultCount = document.getElementById('maint-result-count');
    const rows = document.querySelectorAll('.maint-row');

    function applyFilter() {
        const keyword = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const dept = deptSelect ? deptSelect.value : '';
        let visible = 0;
        rows.forEach(row => {
            const rowDept = row.dataset.dept || '';
            const rowSearch = row.dataset.search || '';
            const matchDept = !dept || rowDept === dept;
            const matchSearch = !keyword || rowSearch.includes(keyword);
            if (matchDept && matchSearch) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });
        if (resultCount) {
            if (keyword || dept) {
                resultCount.textContent = `แสดง ${visible} รายการ`;
            } else {
                resultCount.textContent = '';
            }
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilter);
    if (deptSelect) deptSelect.addEventListener('change', applyFilter);
})();
</script>
