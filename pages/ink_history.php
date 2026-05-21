<div class="page-header" style="margin-bottom: 24px;">
    <h2>🧾 ประวัติการเบิกหมึก</h2>
    <p style="color: var(--text-muted); font-size: 0.95rem;">สรุปและดูรายละเอียดประวัติการเบิกหมึกแยกตามหน่วยงานและรุ่นหมึก</p>
</div>

<!-- Filter Section -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
        
        <div style="display: flex; background: #f1f5f9; border-radius: 12px; padding: 4px; gap: 4px">
            <button class="ink-period-tab" data-period="week"
                style="padding: 8px 16px; border: none; border-radius: 10px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s; background: transparent; color: var(--text-muted); box-shadow: none">
                สัปดาห์นี้
            </button>
            <button class="ink-period-tab active" data-period="month"
                style="padding: 8px 16px; border: none; border-radius: 10px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s; background: white; color: var(--primary); box-shadow: 0 2px 8px rgba(0,0,0,0.1)">
                เดือนนี้
            </button>
            <button class="ink-period-tab" data-period="year"
                style="padding: 8px 16px; border: none; border-radius: 10px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s; background: transparent; color: var(--text-muted); box-shadow: none">
                ปีนี้
            </button>
            <button class="ink-period-tab" data-period="custom"
                style="padding: 8px 16px; border: none; border-radius: 10px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s; background: transparent; color: var(--text-muted); box-shadow: none">
                เลือกวัน
            </button>
        </div>

        <div id="ink-custom-date-row" style="display: none; align-items: center; gap: 8px; flex-wrap: wrap">
            <input type="text" id="ink-history-date-start" placeholder="วันเริ่มต้น" style="padding: 8px 14px; border: 1.5px solid var(--border); border-radius: 10px; max-width: 140px; font-family: inherit; font-size: 0.9rem; outline: none;">
            <span style="color: var(--text-muted);">—</span>
            <input type="text" id="ink-history-date-end" placeholder="วันสิ้นสุด" style="padding: 8px 14px; border: 1.5px solid var(--border); border-radius: 10px; max-width: 140px; font-family: inherit; font-size: 0.9rem; outline: none;">
            <button id="ink-history-filter-btn" class="btn-primary" style="padding: 8px 16px; font-size: 0.88rem;">ค้นหา</button>
        </div>

        <div style="margin-left: auto; font-size: 0.85rem; color: var(--text-muted);" id="ink-period-label"></div>
    </div>
</div>

<!-- Dashboard Summary -->
<div id="ink-history-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 24px; display: none;">
    <div class="stat-card" style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border);">
        <div style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 8px;">📦 รวมยอดเบิกทั้งหมด</div>
        <div id="summary-total-qty" style="font-size: 2.2rem; font-weight: 700; color: var(--primary);">0</div>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">กล่อง</div>
    </div>
    <div class="stat-card" style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border);">
        <div style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 8px;">🏢 หน่วยงานที่เบิกสูงสุด</div>
        <div id="summary-top-dept" style="font-size: 1.4rem; font-weight: 700; color: #f59e0b;">-</div>
        <div id="summary-top-dept-qty" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">0 กล่อง</div>
    </div>
    <div class="stat-card" style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border);">
        <div style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 8px;">🖨️ รุ่นหมึกที่เบิกสูงสุด</div>
        <div id="summary-top-ink" style="font-size: 1.4rem; font-weight: 700; color: #10b981;">-</div>
        <div id="summary-top-ink-qty" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">0 กล่อง</div>
    </div>
</div>

<!-- History Table -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <h3 style="margin: 0; font-size: 1.1rem;">รายการเบิกหมึก</h3>
        <input type="text" id="ink-history-search" placeholder="🔍 ค้นหารายการเบิก..." style="padding: 10px 16px; border-radius: 20px; border: 1px solid var(--border); width: 100%; max-width: 300px;">
    </div>
    <div class="table-responsive">
        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="min-width: 120px;">วันที่เบิก</th>
                    <th style="min-width: 150px;">หน่วยงาน</th>
                    <th style="min-width: 150px;">รุ่นหมึก</th>
                    <th style="min-width: 150px;">สำหรับปริ้นเตอร์</th>
                    <th style="min-width: 80px; text-align: center;">จำนวน</th>
                </tr>
            </thead>
            <tbody id="ink-history-tbody">
                <tr><td colspan="5" style="text-align: center; padding: 30px;">⌛ กำลังโหลดข้อมูล...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    // DOM Elements
    const dateStart = document.getElementById('ink-history-date-start');
    const dateEnd = document.getElementById('ink-history-date-end');
    const filterBtn = document.getElementById('ink-history-filter-btn');
    const searchInput = document.getElementById('ink-history-search');
    const tbody = document.getElementById('ink-history-tbody');
    const summaryPanel = document.getElementById('ink-history-summary');
    
    const tabs = document.querySelectorAll('.ink-period-tab');
    const customDateRow = document.getElementById('ink-custom-date-row');
    const periodLabel = document.getElementById('ink-period-label');

    let currentPeriod = 'month';
    
    const updateYear = function(selectedDates, dateStr, instance) {
        if (!instance || !instance.currentYearElement) return;
        const yearInput = instance.currentYearElement;
        if (parseInt(yearInput.value) < 2500) {
            yearInput.value = parseInt(yearInput.value) + 543;
        }
    };

    // Explicit Flatpickr configuration for B.E. Year to ensure it's applied correctly
    const fpConfig = {
        locale: "th",
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "d M Y",
        disableMobile: true,
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
    };

    // Init Flatpickr
    if (typeof flatpickr !== 'undefined') {
        flatpickr(dateStart, fpConfig);
        flatpickr(dateEnd, fpConfig);
    }

    function getDateRange(period) {
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
        } else if (period === 'custom') {
            const s = dateStart._flatpickr ? dateStart._flatpickr.input.value : dateStart.value;
            const e = dateEnd._flatpickr ? dateEnd._flatpickr.input.value : dateEnd.value;
            if (s && e) {
                from = new Date(s); from.setHours(0,0,0,0);
                to   = new Date(e); to.setHours(23,59,59,999);
            }
        }
        return { from, to };
    }

    function fmt(d) {
        if (!d) return '';
        const months = ["ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
        return `${d.getDate().toString().padStart(2,'0')} ${months[d.getMonth()]} ${d.getFullYear() + 543}`;
    }

    function toYMD(d) {
        if (!d) return '';
        return `${d.getFullYear()}-${(d.getMonth()+1).toString().padStart(2,'0')}-${d.getDate().toString().padStart(2,'0')}`;
    }

    async function loadHistory() {
        const range = getDateRange(currentPeriod);
        if (!range || !range.from || !range.to) {
            if (currentPeriod === 'custom') showToast('กรุณาระบุวันที่ให้ครบ', 'warning');
            return;
        }

        periodLabel.innerHTML = `ข้อมูล: <b>${fmt(range.from)} - ${fmt(range.to)}</b>`;
        const s = toYMD(range.from);
        const e = toYMD(range.to);

        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 30px;">⌛ กำลังโหลดข้อมูล...</td></tr>';
        
        try {
            const res = await fetch(`api.php?action=get_ink_history&start=${s}&end=${e}`);
            const result = await res.json();
            
            if (result.success) {
                renderSummary(result.data.summary);
                renderTable(result.data.history);
            } else {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: red;">❌ เกิดข้อผิดพลาด: ${result.message}</td></tr>`;
            }
        } catch (error) {
            console.error(error);
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: red;">❌ เกิดข้อผิดพลาดในการเชื่อมต่อ</td></tr>`;
        }
    }

    // Tabs Click Event
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => {
                t.style.background = 'transparent';
                t.style.color = 'var(--text-muted)';
                t.style.boxShadow = 'none';
                t.classList.remove('active');
            });
            this.style.background = 'white';
            this.style.color = 'var(--primary)';
            this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
            this.classList.add('active');

            currentPeriod = this.dataset.period;
            if (currentPeriod === 'custom') {
                customDateRow.style.display = 'flex';
                // Set default to current month for custom pickers if they are empty
                if (dateStart._flatpickr && !dateStart._flatpickr.input.value) {
                    const now = new Date();
                    dateStart._flatpickr.setDate(new Date(now.getFullYear(), now.getMonth(), 1));
                    dateEnd._flatpickr.setDate(new Date(now.getFullYear(), now.getMonth() + 1, 0));
                }
            } else {
                customDateRow.style.display = 'none';
                loadHistory(); // load immediately for predefined periods
            }
        });
    });

    function renderSummary(summary) {
        summaryPanel.style.display = 'grid';
        
        document.getElementById('summary-total-qty').textContent = summary.total || 0;
        
        if (summary.departments && summary.departments.length > 0) {
            document.getElementById('summary-top-dept').textContent = summary.departments[0].department || 'ไม่ระบุ';
            document.getElementById('summary-top-dept-qty').textContent = `${summary.departments[0].total} กล่อง`;
        } else {
            document.getElementById('summary-top-dept').textContent = '-';
            document.getElementById('summary-top-dept-qty').textContent = '0 กล่อง';
        }
        
        if (summary.inks && summary.inks.length > 0) {
            document.getElementById('summary-top-ink').textContent = summary.inks[0].name;
            document.getElementById('summary-top-ink-qty').textContent = `${summary.inks[0].total} กล่อง`;
        } else {
            document.getElementById('summary-top-ink').textContent = '-';
            document.getElementById('summary-top-ink-qty').textContent = '0 กล่อง';
        }
    }

    function renderTable(history) {
        if (!history || history.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">ไม่พบประวัติการเบิกหมึกในช่วงเวลานี้</td></tr>';
            return;
        }

        let html = '';
        history.forEach(row => {
            const searchStr = `${row.department} ${row.ink_name} ${row.printer_model}`.toLowerCase();
            const dateStr = window.thdatetime ? window.thdatetime(row.transaction_date) : row.transaction_date;
            
            html += `
                <tr class="ink-row" data-search="${searchStr}" style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 12px; font-size: 0.9rem;">${dateStr}</td>
                    <td style="padding: 12px; font-weight: 500;">${row.department || '-'}</td>
                    <td style="padding: 12px;">
                        <div style="font-weight: 600; color: var(--primary);">${row.ink_name}</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">${row.ink_brand || ''}</div>
                    </td>
                    <td style="padding: 12px; font-size: 0.9rem;">${row.printer_model || '-'}</td>
                    <td style="padding: 12px; text-align: center; font-weight: bold; font-size: 1.1rem; color: #ef4444;">-${row.quantity}</td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
        applySearch(); // Re-apply search if there is any keyword
    }

    function applySearch() {
        const keyword = searchInput.value.toLowerCase().trim();
        const rows = tbody.querySelectorAll('.ink-row');
        
        rows.forEach(row => {
            if (!keyword || row.dataset.search.includes(keyword)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Events
    filterBtn.addEventListener('click', loadHistory);
    searchInput.addEventListener('input', applySearch);

    // Initial Load
    setTimeout(loadHistory, 100);
})();
</script>
