<?php
require_once '../db.php';

try {
    // จำนวนปริ้นเตอร์ทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) FROM printers WHERE deleted_at IS NULL AND status != 'retired'");
    $totalPrinters = $stmt->fetchColumn();

    // จำนวนปริ้นเตอร์ที่ออนไลน์ปกติ
    $stmt = $pdo->query("SELECT COUNT(*) FROM printers WHERE status = 'normal' AND deleted_at IS NULL");
    $onlinePrinters = $stmt->fetchColumn();

    // จำนวนหมึกคงเหลือทั้งหมด (ทุกประเภทรวมกัน)
    $stmt = $pdo->query("SELECT SUM(current_quantity) FROM ink_stock");
    $totalInk = $stmt->fetchColumn() ?: 0;

    // หมึกที่ใกล้หมด (ต่ำกว่าเกณฑ์)
    $stmt = $pdo->query("SELECT COUNT(*) FROM ink_stock WHERE current_quantity <= min_quantity");
    $lowInkCount = $stmt->fetchColumn();

    // ซ่อมรอดำเนินการ (ดูจากสถานะของเครื่องปริ้นที่กำลังซ่อม)
    $stmt = $pdo->query("SELECT COUNT(*) FROM printers WHERE status = 'repairing' AND deleted_at IS NULL");
    $pendingRepairs = $stmt->fetchColumn();

    // ดึงข้อมูลประวัติการซ่อมทั้งหมดสำหรับกราฟ (เฉพาะปริ้นเตอร์ที่ยังไม่ถูกลบ)
    $stmt = $pdo->query("
        SELECT ml.repair_date, p.department, ml.status_after_repair 
        FROM maintenance_logs ml 
        JOIN printers p ON ml.printer_id = p.id
        WHERE ml.deleted_at IS NULL
        AND p.deleted_at IS NULL
    ");
    $maintLogs = $stmt->fetchAll();

    // สรุปยอดเบิกหมึกตามกลุ่มงาน (ปีงบประมาณปัจจุบัน)
    $current_fiscal_year = getFiscalYear(date('Y-m-d'));
    
    $stmt = $pdo->prepare("
        SELECT p.department, SUM(it.quantity) as total_ink 
        FROM ink_transactions it 
        JOIN printers p ON it.printer_id = p.id 
        WHERE it.type = 'out' AND it.fiscal_year = ? AND p.deleted_at IS NULL 
        GROUP BY p.department 
        ORDER BY total_ink DESC 
        LIMIT 10
    ");
    $stmt->execute([$current_fiscal_year]);
    $inkByDept = $stmt->fetchAll();

    // สรุปยอดแจ้งซ่อมตามกลุ่มงาน (ปีงบประมาณปัจจุบัน)
    $stmt = $pdo->prepare("
        SELECT p.department, COUNT(ml.id) as total_repairs 
        FROM maintenance_logs ml 
        JOIN printers p ON ml.printer_id = p.id 
        WHERE ml.fiscal_year = ? AND ml.deleted_at IS NULL AND p.deleted_at IS NULL 
        GROUP BY p.department 
        ORDER BY total_repairs DESC 
        LIMIT 10
    ");
    $stmt->execute([$current_fiscal_year]);
    $repairsByDept = $stmt->fetchAll();

} catch (Exception $e) {
    $totalPrinters = 0;
    $onlinePrinters = 0;
    $totalInk = 0;
    $lowInkCount = 0;
    $pendingRepairs = 0;
    $maintLogs = [];
    $inkByDept = [];
    $repairsByDept = [];
}

$logsJson = json_encode(array_map(function($l) {
    return [
        'date'   => $l['repair_date'],
        'dept'   => $l['department'] ?? 'ไม่ระบุ',
        'status' => $l['status_after_repair'] ?? '',
    ];
}, $maintLogs));
?>



<div class="dashboard-grid">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center">
            <div>
                <p style="color: var(--text-muted); font-size: 0.9rem">ปริ้นเตอร์ทั้งหมด</p>
                <h2 style="font-size: 2rem; margin: 8px 0"><?= number_format($totalPrinters) ?> <span
                        style="font-size: 1rem; font-weight: 400">เครื่อง</span></h2>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.2">🖨️</div>
        </div>
        <div style="margin-top: 16px; font-size: 0.8rem; color: var(--success)">
            🟢 ออนไลน์ปกติ <?= number_format($onlinePrinters) ?> เครื่อง
        </div>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center">
            <div>
                <p style="color: var(--text-muted); font-size: 0.9rem">หมึกคงเหลือ (ทุกประเภท)</p>
                <h2 style="font-size: 2rem; margin: 8px 0"><?= number_format($totalInk) ?> <span
                        style="font-size: 1rem; font-weight: 400">กล่อง</span></h2>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.2">📦</div>
        </div>
        <div style="margin-top: 16px; font-size: 0.8rem; color: var(--danger)">
            ⚠️ ต่ำกว่าเกณฑ์ <?= number_format($lowInkCount) ?> รายการ
        </div>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center">
            <div>
                <p style="color: var(--text-muted); font-size: 0.9rem">รอดำเนินการซ่อม</p>
                <h2 style="font-size: 2rem; margin: 8px 0"><?= number_format($pendingRepairs) ?> <span
                        style="font-size: 1rem; font-weight: 400">รายการ</span></h2>
            </div>
            <div style="font-size: 2.5rem; opacity: 0.2">🔧</div>
        </div>
        <div style="margin-top: 16px; font-size: 0.8rem; color: var(--text-muted)">
            เช็คประวัติล่าสุดเมื่อ 1 ชม. ที่แล้ว
        </div>
    </div>
</div>

<!-- ===== Analytics Charts ===== -->
<div class="card" style="margin-top: 24px; padding: 24px">
    <!-- Card Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px">
        <div>
            <h3 style="margin: 0; font-size: 1.05rem">สถิติประวัติการซ่อม 🔧</h3>
            <p style="margin: 4px 0 0; font-size: 0.82rem; color: var(--text-muted)">ความถี่การซ่อมรายหน่วยงาน และสัดส่วนสถานะการซ่อม</p>
        </div>
    </div>
    <!-- Period Tabs + Date Picker -->
    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 20px">
        <div style="display: flex; background: #f1f5f9; border-radius: 12px; padding: 4px; gap: 4px">
            <?php foreach (['week' => 'สัปดาห์นี้', 'month' => 'เดือนนี้', 'year' => 'ปีนี้', 'custom' => 'เลือกวัน'] as $k => $label): ?>
                <button class="dash-period-tab <?= $k === 'month' ? 'active' : '' ?>" data-period="<?= $k ?>"
                    style="padding: 8px 16px; border: none; border-radius: 10px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
                           background: <?= $k === 'month' ? 'white' : 'transparent' ?>;
                           color: <?= $k === 'month' ? 'var(--primary)' : 'var(--text-muted)' ?>;
                           box-shadow: <?= $k === 'month' ? '0 2px 8px rgba(0,0,0,0.1)' : 'none' ?>">
                    <?= $label ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Custom Date Range (Flatpickr) -->
        <div id="dash-custom-date-row" style="display: none; align-items: center; gap: 8px; flex-wrap: wrap">
            <input type="text" id="dash-chart-date-start" placeholder="วันเริ่มต้น"
                style="padding: 8px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.9rem; font-family: inherit; outline: none; width: 140px">
            <span style="color: var(--text-muted)">—</span>
            <input type="text" id="dash-chart-date-end" placeholder="วันสิ้นสุด"
                style="padding: 8px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.9rem; font-family: inherit; outline: none; width: 140px">
            <button onclick="applyDashCustomDate()" class="btn-primary" style="padding: 8px 16px; font-size: 0.88rem">ดูกราฟ</button>
        </div>

        <div style="margin-left: auto; font-size: 0.85rem; color: var(--text-muted)" id="dash-chart-period-label"></div>
    </div>

    <!-- Charts: Layout = Horizontal bar (scrollable) + doughnut side by side -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;align-items:start">

        <!-- Dept Bar Chart (Horizontal, Scrollable) -->
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px">ความถี่การซ่อมรายหน่วยงาน</div>
                <div style="display: flex; align-items: center; gap: 8px">
                    <span style="font-size: 0.8rem; color: var(--text-muted)">แสดง Top</span>
                    <select id="dash-topn-select" style="padding: 4px 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; outline: none; cursor: pointer">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="0">ทั้งหมด</option>
                    </select>
                    <span style="font-size: 0.8rem; color: var(--text-muted)">หน่วยงาน</span>
                </div>
            </div>
            <!-- Scrollable wrapper — grows dynamically with data -->
            <div id="dash-chart-dept-wrapper" style="overflow-y: auto; max-height: 320px; border: 1px solid var(--border); border-radius: 14px; padding: 12px; background: #fafafa">
                <div style="position: relative" id="dash-chart-dept-container">
                    <canvas id="dash-chart-dept"></canvas>
                </div>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; text-align: right" id="dash-dept-count-label"></div>
        </div>

        <!-- Doughnut Status Chart -->
        <div>
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px">สัดส่วนสถานะ</div>
            <div style="position: relative; height: 240px">
                <canvas id="dash-chart-status"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
window.initDashCharts = function() {
    const RAW = <?= $logsJson ?>;

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

    let deptChart, statusChart;

    function renderCharts(period, startStr, endStr) {
        const range = getDateRange(period, startStr, endStr);

        const filtered = range
            ? RAW.filter(r => {
                const d = new Date(r.date);
                return d >= range.from && d <= range.to;
            })
            : RAW;

        const label = range
            ? `${fmt(range.from)} — ${fmt(range.to)}`
            : 'ทั้งหมด';
        document.getElementById('dash-chart-period-label').textContent = label;

        const normal   = filtered.filter(r => r.status === 'normal').length;
        const repairing= filtered.filter(r => r.status === 'repairing').length;
        const broken   = filtered.filter(r => r.status === 'broken').length;

        const deptCount = {};
        filtered.forEach(r => { deptCount[r.dept] = (deptCount[r.dept] || 0) + 1; });
        const allDepts  = Object.keys(deptCount).sort((a,b) => deptCount[b] - deptCount[a]);
        const allCounts = allDepts.map(d => deptCount[d]);

        const topN = parseInt(document.getElementById('dash-topn-select')?.value || '10');
        const depts  = topN > 0 ? allDepts.slice(0, topN)  : allDepts;
        const counts = topN > 0 ? allCounts.slice(0, topN) : allCounts;

        const labelEl = document.getElementById('dash-dept-count-label');
        if (labelEl) labelEl.textContent = allDepts.length > depts.length
            ? `แสดง ${depts.length} จาก ${allDepts.length} หน่วยงาน (เรียงจากซ่อมบ่อยสุด)`
            : `ทั้งหมด ${allDepts.length} หน่วยงาน`;

        const palette = ['#6366f1','#8b5cf6','#4f46e5','#7c3aed','#a78bfa','#818cf8',
                         '#10b981','#f59e0b','#ef4444','#06b6d4','#f97316','#84cc16'];

        const barH = Math.max(depts.length * 36, 80);
        const container = document.getElementById('dash-chart-dept-container');
        if (container) container.style.height = barH + 'px';

        const chartDeptCtx = document.getElementById('dash-chart-dept');
        if(chartDeptCtx) {
            if (deptChart) deptChart.destroy();
            const ctx1 = chartDeptCtx.getContext('2d');
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
                    indexAxis: 'y',
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
        }

        const chartStatusCtx = document.getElementById('dash-chart-status');
        if(chartStatusCtx) {
            if (statusChart) statusChart.destroy();
            const ctx2 = chartStatusCtx.getContext('2d');
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
    }

    let currentPeriod = 'month';
    let lastStart = '', lastEnd = '';

    document.querySelectorAll('.dash-period-tab').forEach(tab => {
        const newTab = tab.cloneNode(true);
        tab.parentNode.replaceChild(newTab, tab);
        newTab.addEventListener('click', () => {
            document.querySelectorAll('.dash-period-tab').forEach(t => {
                t.style.background = 'transparent';
                t.style.color = 'var(--text-muted)';
                t.style.boxShadow = 'none';
            });
            newTab.style.background = 'white';
            newTab.style.color = 'var(--primary)';
            newTab.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
            currentPeriod = newTab.dataset.period;
            const customRow = document.getElementById('dash-custom-date-row');
            customRow.style.display = currentPeriod === 'custom' ? 'flex' : 'none';
            if (currentPeriod !== 'custom') { lastStart = ''; lastEnd = ''; renderCharts(currentPeriod); }
        });
    });

    const topnSelect = document.getElementById('dash-topn-select');
    if (topnSelect) {
        const newSelect = topnSelect.cloneNode(true);
        topnSelect.parentNode.replaceChild(newSelect, topnSelect);
        newSelect.addEventListener('change', () => {
            renderCharts(currentPeriod, lastStart, lastEnd);
        });
    }

    if (typeof flatpickr !== 'undefined') {
        flatpickr('#dash-chart-date-start', {
            locale: 'th', dateFormat: 'Y-m-d',
            altInput: true, altFormat: 'd M Y', disableMobile: true
        });
        flatpickr('#dash-chart-date-end', {
            locale: 'th', dateFormat: 'Y-m-d',
            altInput: true, altFormat: 'd M Y', disableMobile: true
        });
    }

    window.applyDashCustomDate = function() {
        const s = document.getElementById('dash-chart-date-start')._flatpickr?.input.value
                  || document.getElementById('dash-chart-date-start').value;
        const e = document.getElementById('dash-chart-date-end')._flatpickr?.input.value
                  || document.getElementById('dash-chart-date-end').value;
        if (!s || !e) { showToast('กรุณาเลือกวันเริ่มต้นและวันสิ้นสุด', 'warning'); return; }
        lastStart = s; lastEnd = e;
        renderCharts('custom', s, e);
    };

    renderCharts('month');
};

if(document.getElementById('dash-chart-dept')) {
    window.initDashCharts();
}
</script>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-top: 24px;">
    <!-- Top 10 Ink Usage By Dept -->
    <div class="card">
        <h3 style="margin: 0 0 16px; font-size: 1.05rem;">📦 ยอดเบิกหมึกตามกลุ่มงาน (ปีงบฯ <?= $current_fiscal_year ?>)</h3>
        <?php if (empty($inkByDept)): ?>
            <p style="text-align: center; padding: 20px; color: var(--text-muted)">ยังไม่มีข้อมูลการเบิกหมึก</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php $maxInk = max(array_column($inkByDept, 'total_ink')); ?>
                <?php foreach ($inkByDept as $idx => $row): ?>
                    <?php 
                        $pct = ($row['total_ink'] / $maxInk) * 100; 
                        $rankColors = ['#f59e0b', '#94a3b8', '#b45309'];
                        $rankColor = $idx < 3 ? $rankColors[$idx] : 'var(--primary)';
                    ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 4px; font-weight: 600;">
                            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 8px;">
                                <?= ($idx+1) ?>. <?= htmlspecialchars($row['department'] ?: 'ไม่ระบุ') ?>
                            </span>
                            <span style="color: <?= $rankColor ?>; flex-shrink: 0;"><?= $row['total_ink'] ?> กล่อง</span>
                        </div>
                        <div style="background: #f1f5f9; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?= $pct ?>%; height: 100%; background: <?= $rankColor ?>; border-radius: 4px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top 10 Maintenance By Dept -->
    <div class="card">
        <h3 style="margin: 0 0 16px; font-size: 1.05rem;">🔧 แจ้งซ่อมตามกลุ่มงาน (ปีงบฯ <?= $current_fiscal_year ?>)</h3>
        <?php if (empty($repairsByDept)): ?>
            <p style="text-align: center; padding: 20px; color: var(--text-muted)">ยังไม่มีข้อมูลการแจ้งซ่อม</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php $maxRepairs = max(array_column($repairsByDept, 'total_repairs')); ?>
                <?php foreach ($repairsByDept as $idx => $row): ?>
                    <?php 
                        $pct = ($row['total_repairs'] / $maxRepairs) * 100; 
                        $rankColor = $idx < 3 ? '#ef4444' : 'var(--warning)';
                    ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 4px; font-weight: 600;">
                            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 8px;">
                                <?= ($idx+1) ?>. <?= htmlspecialchars($row['department'] ?: 'ไม่ระบุ') ?>
                            </span>
                            <span style="color: <?= $rankColor ?>; flex-shrink: 0;"><?= $row['total_repairs'] ?> ครั้ง</span>
                        </div>
                        <div style="background: #f1f5f9; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?= $pct ?>%; height: 100%; background: <?= $rankColor ?>; border-radius: 4px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// ดึงกิจกรรมล่าสุด (เอาทั้งประวัติซ่อมและเบิกหมึกมารวมกัน)
$activities = [];
try {
    // ประวัติซ่อม 3 รายการล่าสุด
    $stmt = $pdo->query("SELECT 'maintenance' as type, repair_date as date, repair_time as time, symptoms as detail FROM maintenance_logs ORDER BY repair_date DESC, repair_time DESC LIMIT 3");
    $m_logs = $stmt->fetchAll();

    // ประวัติเบิกหมึก 3 รายการล่าสุด
    $stmt = $pdo->query("SELECT 'ink' as type, transaction_date as date, '' as time, CONCAT('เบิกหมึก ', quantity, ' กล่อง') as detail FROM ink_transactions WHERE type='out' ORDER BY transaction_date DESC LIMIT 3");
    $i_logs = $stmt->fetchAll();

    $activities = array_merge($m_logs, $i_logs);
    // เรียงตามวันที่ล่าสุด
    usort($activities, function ($a, $b) {
        $dateA = strtotime($a['date'] . ' ' . $a['time']);
        $dateB = strtotime($b['date'] . ' ' . $b['time']);
        return $dateB - $dateA;
    });
    $activities = array_slice($activities, 0, 5); // เอา 5 รายการล่าสุด
} catch (Exception $e) {
}
?>
<div class="card" style="margin-top: 24px">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px">
        <h3>กิจกรรมล่าสุด</h3>
        <button class="btn-primary"
            onclick="window.loadPage ? window.loadPage('maintenance') : location.hash='maintenance'"
            style="padding: 6px 16px; font-size: 0.8rem">ดูประวัติทั้งหมด</button>
    </div>
    <div class="activity-list">
        <?php if (empty($activities)): ?>
            <p style="text-align: center; padding: 40px; color: var(--text-muted)">ยังไม่มีกิจกรรมล่าสุดในวันนี้</p>
        <?php else: ?>
            <?php foreach ($activities as $act): ?>
                <div
                    style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border)">
                    <div style="display: flex; align-items: center; gap: 12px">
                        <div style="font-size: 1.5rem; opacity: 0.8">
                            <?= $act['type'] == 'maintenance' ? '🔧' : '📦' ?>
                        </div>
                        <div>
                            <p style="font-weight: 500"><?= htmlspecialchars($act['detail']) ?></p>
                            <p style="font-size: 0.8rem; color: var(--text-muted)">
                                <?= $act['type'] == 'maintenance' ? 'แจ้งซ่อมบำรุง' : 'เบิกวัสดุสิ้นเปลือง' ?>
                            </p>
                        </div>
                    </div>
                    <div style="text-align: right; font-size: 0.85rem; color: var(--text-muted)">
                        <?= thdate($act['date']) ?>
                        <br>
                        <?= $act['time'] ? date('H:i', strtotime($act['time'])) : date('H:i', strtotime($act['date'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>