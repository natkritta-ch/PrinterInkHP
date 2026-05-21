<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px">
    <div>
        <h1>วิเคราะห์/ยอดการเบิกหมึก 📊</h1>
        <p style="color: var(--text-muted)">วิเคราะห์ยอดการเบิกหมึกรายเดือน/รายไตรมาส และดูแนวโน้มการใช้งาน</p>
    </div>
    <div style="display: flex; gap: 12px; align-items: center">
        <label>ปีงบประมาณ:</label>
        <select id="fiscal-year-select" style="padding: 8px 16px; border-radius: 10px; border: 1px solid var(--border)">
            <?php 
                $currentYearBE = (int)date('Y') + 543;
                if (date('m') >= 10) $currentYearBE++;
                for ($be = $currentYearBE; $be >= 2567; $be--) {
                    echo "<option value='$be'>$be</option>";
                }
            ?>
        </select>
    </div>
    </div>
</div>

<!-- กราฟรายเดือน + Drill-down -->
<div class="card" style="margin-bottom: 24px">
    <h3>สรุปยอดการเบิกใช้รายเดือน (ม.ค. - ธ.ค.)</h3>
    <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 4px">💡 กดที่แท่งกราฟเพื่อดูรายละเอียดรุ่นที่เบิกในเดือนนั้น</p>
    <div style="height: 300px; margin-top: 20px">
        <canvas id="monthlyChart"></canvas>
    </div>
</div>

<!-- Monthly Drill-down Panel -->
<div id="monthly-drilldown-container" class="card" style="margin-bottom: 24px; display: none; border-left: 4px solid var(--primary)">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px">
        <h4 id="monthly-drilldown-title" style="color: var(--primary); font-size: 1rem"></h4>
        <button onclick="document.getElementById('monthly-drilldown-container').style.display='none'" style="background: none; border: none; font-size: 1.3rem; cursor: pointer; color: var(--text-muted); line-height: 1">&times;</button>
    </div>
    <div id="monthly-drilldown-content">
        <p style="text-align: center; padding: 20px; color: var(--text-muted)">⎳ กำลังโหลด...</p>
    </div>
</div>

<!-- ส่วนสรุปภาพรวม (Summary Cards) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px">
    <div class="card" style="border-left: 5px solid var(--primary)">
        <div style="color: var(--text-muted); font-size: 0.85rem">ยอดเบิกใช้รวมปีนี้</div>
        <div id="total-usage-year" style="font-size: 1.8rem; font-weight: 800; margin-top: 5px; color: var(--primary)">0 <span style="font-size: 1rem">กล่อง</span></div>
    </div>
    <div class="card" style="border-left: 5px solid var(--success)">
        <div style="color: var(--text-muted); font-size: 0.85rem">เฉลี่ยต่อเดือน</div>
        <div id="avg-usage-month" style="font-size: 1.8rem; font-weight: 800; margin-top: 5px; color: var(--success)">0 <span style="font-size: 1rem">กล่อง</span></div>
    </div>
    <div class="card" style="border-left: 5px solid var(--warning)">
        <div style="color: var(--text-muted); font-size: 0.85rem">หมวดที่ใช้สูงสุด</div>
        <div id="top-usage-cat" style="font-size: 1.2rem; font-weight: 700; margin-top: 8px; color: var(--warning)">-</div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- กราฟไตรมาส -->
    <div class="card" style="grid-column: span 2">
        <h3>ยอดการเบิกใช้หมึกรายไตรมาส</h3>
        <div style="height: 300px; margin-top: 20px">
            <canvas id="quarterlyChart"></canvas>
        </div>
    </div>

    <!-- รายการยอดนิยม -->
    <div class="card">
        <h3>หมึกที่เบิกใช้มากที่สุด</h3>
        <div id="top-items-list" style="margin-top: 20px">
            <!-- ข้อมูลจะถูกฉีดเข้าที่นี่ -->
            <p style="text-align: center; padding: 40px; color: var(--text-muted)">กำลังคำนวณข้อมูล...</p>
        </div>
    </div>
    </div>
</div>

<div class="card" style="margin-top: 24px">
    <h3>💡 ข้อแนะนำการจัดซื้อ</h3>
    <div id="procurement-suggestions" style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px">
        <!-- ข้อมูลจะถูกฉีดเข้าที่นี่ -->
    </div>
</div>

<!-- Modal สำหรับดูรายละเอียดการเบิกใช้หมึก -->
<div id="ink-usage-modal" class="modal">
    <div class="modal-content" style="max-width: 600px">
        <div class="modal-header">
            <h3>ประวัติการเบิกใช้รายหน่วยงาน 📦</h3>
            <button type="button" class="close-modal-btn" onclick="document.getElementById('ink-usage-modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <h4 id="usage-ink-name" style="margin-bottom: 15px; color: var(--primary)"></h4>
            <div id="usage-details-content">
                <!-- ตารางข้อมูลจะถูกฉีดเข้าที่นี่ -->
            </div>
        </div>
    </div>
</div>

<!-- Modal สำหรับดูรายการหมึกที่เบิกใช้ในเดือนนั้น -->
<div id="monthly-detail-modal" class="modal">
    <div class="modal-content" style="max-width: 600px">
        <div class="modal-header">
            <h3 id="monthly-detail-title">รายการที่เบิกใช้ประจำเดือน 📦</h3>
            <button type="button" class="close-modal-btn" onclick="document.getElementById('monthly-detail-modal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <div id="monthly-detail-content">
                <!-- รายการหมึกจะถูกฉีดเข้าที่นี่ -->
            </div>
        </div>
    </div>
</div>

<script>
    // ฟังก์ชันสำหรับหน้า Analysis
    async function initAnalysisPage() {
        const yearSelect = document.getElementById('fiscal-year-select');
        
        const fetchData = async (year) => {
            // ดึงข้อมูลรายไตรมาส
            const qResponse = await fetch(`api.php?action=get_budget_analysis&year=${year}`);
            const qResult = await qResponse.json();
            if(qResult.success) {
                renderQuarterlyChart(qResult.quarterly);
                renderTopItems(qResult.top_items);
                renderSuggestions(qResult.quarterly, qResult.top_items);
                
                // คำนวณสรุปยอดรวม
                const total = qResult.quarterly.reduce((a, b) => a + b.value, 0);
                document.getElementById('total-usage-year').innerHTML = `${total} <span style="font-size: 1rem">กล่อง</span>`;
                document.getElementById('avg-usage-month').innerHTML = `${(total/12).toFixed(1)} <span style="font-size: 1rem">กล่อง</span>`;
                if(qResult.top_items[0]) {
                    document.getElementById('top-usage-cat').textContent = qResult.top_items[0].name;
                }
            }

            // ดึงข้อมูลรายเดือน
            const mResponse = await fetch(`api.php?action=get_monthly_analysis&year=${year}`);
            const mResult = await mResponse.json();
            if(mResult.success) {
                renderMonthlyChart(mResult.data);
            }
        };

        const renderQuarterlyChart = (data) => {
            const ctx = document.getElementById('quarterlyChart').getContext('2d');
            if (window.qChart) window.qChart.destroy();
            window.qChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        label: 'จำนวนกล่องที่เบิก',
                        data: data.map(d => d.value),
                        backgroundColor: 'rgba(99, 102, 241, 0.6)',
                        borderColor: 'rgb(99, 102, 241)',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { display: false } },
                        x: { grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        };

        const renderMonthlyChart = (data) => {
            const ctx = document.getElementById('monthlyChart').getContext('2d');
            if (window.mChart) window.mChart.destroy();
            window.mChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        label: 'จำนวนที่เบิก',
                        data: data.map(d => d.value),
                        backgroundColor: 'rgba(16, 185, 129, 0.6)',
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (evt, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            showMonthlyDetail(index + 1, data[index].label);
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { display: false } },
                        x: { grid: { display: false } }
                    },
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterBody: () => '💡 คลิกเพื่อดูรายละเอียด'
                            }
                        }
                    }
                }
            });
        };

        window.showMonthlyDetail = async (month, monthLabel) => {
            const container = document.getElementById('monthly-drilldown-container');
            const titleEl = document.getElementById('monthly-drilldown-title');
            const content = document.getElementById('monthly-drilldown-content');
            const year = document.getElementById('fiscal-year-select').value;
            titleEl.textContent = `📦 รายการที่เบิกใช้ประจำเดือน ${monthLabel} ${year}`;
            content.innerHTML = '<p style="text-align: center; padding: 20px">⌛ กำลังโหลดรายการ...</p>';
            container.style.display = 'block';
            
            // เลื่อนหน้าจอลงมาดูข้อมูล
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            try {
                const response = await fetch(`api.php?action=get_monthly_ink_usage&year=${year}&month=${month}`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    let html = `
                        <div style="overflow-x: auto">
                            <table style="width: 100%; border-collapse: collapse">
                                <thead>
                                    <tr style="text-align: left; border-bottom: 2px solid var(--border)">
                                        <th style="padding: 10px">รุ่นหมึก</th>
                                        <th style="padding: 10px; text-align: center">จำนวนที่เบิก</th>
                                        <th style="padding: 10px; text-align: right">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    
                    result.data.forEach(row => {
                        html += `
                            <tr style="border-bottom: 1px solid var(--border)">
                                <td style="padding: 12px 10px; font-weight: 600">${row.name}</td>
                                <td style="padding: 12px 10px; text-align: center; font-weight: 700; color: var(--primary)">${row.total_used} อัน</td>
                                <td style="padding: 12px 10px; text-align: right">
                                    <button onclick="showInkUsage(${row.id}, '${row.name}')" 
                                        style="padding: 6px 12px; border-radius: 6px; border: 1px solid var(--primary); background: white; color: var(--primary); cursor: pointer; font-size: 0.85rem">
                                        ดูรายกลุ่มงาน ❯
                                    </button>
                                </td>
                            </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p style="text-align: center; padding: 40px; color: var(--text-muted)">ไม่มีรายการเบิกใช้ในเดือนนี้</p>';
                }
            } catch (error) {
                content.innerHTML = '<p style="color: var(--danger)">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>';
            }
        };

        const renderTopItems = (items) => {
            const list = document.getElementById('top-items-list');
            if (items.length === 0) {
                list.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--text-muted)">ยังไม่มีประวัติการเบิกในปีนี้</p>';
                return;
            }
            list.innerHTML = items.map((item, index) => `
                <div onclick="showInkUsage(${item.id}, '${item.name}')" 
                    style="display: flex; justify-content: space-between; align-items: center; padding: 12px 10px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.2s; border-radius: 8px"
                    onmouseenter="this.style.background='rgba(99, 102, 241, 0.05)'"
                    onmouseleave="this.style.background='transparent'">
                    <div style="display: flex; align-items: center; gap: 12px">
                        <span style="font-weight: 700; color: var(--primary)">#${index + 1}</span>
                        <span>${item.name}</span>
                    </div>
                    <div style="font-weight: 700">${item.used} กล่อง ❯</div>
                </div>
            `).join('');
        };

        const renderSuggestions = (quarterly, topItems) => {
            const container = document.getElementById('procurement-suggestions');
            const totalUsed = quarterly.reduce((a, b) => a + b.value, 0);
            const avgPerQuarter = (totalUsed / 4).toFixed(1);

            container.innerHTML = `
                <div class="card" style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2)">
                    <h4 style="color: var(--success)">แนวโน้มการใช้งาน</h4>
                    <p style="margin-top: 10px">จากการวิเคราะห์ ปีนี้มีการใช้งานเฉลี่ย <strong>${avgPerQuarter} กล่อง/ไตรมาส</strong></p>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px">ควรสำรองหมึกเพิ่มอย่างน้อย 20% สำหรับไตรมาสถัดไป</p>
                </div>
                <div class="card" style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2)">
                    <h4 style="color: var(--warning)">รายการที่ควรสั่งซื้อเพิ่ม</h4>
                    <p style="margin-top: 10px">แนะนำให้เตรียมงบประมาณสำหรับ <strong>${topItems[0] ? topItems[0].name : 'หมึกหลัก'}</strong> เป็นพิเศษ เนื่องจากมีการใช้งานสูงสุด</p>
                </div>
            `;
        };

        window.showInkUsage = async (inkId, inkName) => {
            const modal = document.getElementById('ink-usage-modal');
            const nameEl = document.getElementById('usage-ink-name');
            const content = document.getElementById('usage-details-content');
            const year = document.getElementById('fiscal-year-select').value;

            nameEl.textContent = `รุ่นหมึก: ${inkName}`;
            content.innerHTML = '<p style="text-align: center; padding: 20px">⌛ กำลังโหลดข้อมูล...</p>';
            modal.style.display = 'flex';

            try {
                const response = await fetch(`api.php?action=get_ink_usage_details&ink_id=${inkId}&year=${year}`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    let html = `
                        <table style="width: 100%; border-collapse: collapse; margin-top: 10px">
                            <thead>
                                <tr style="text-align: left; border-bottom: 2px solid var(--border)">
                                    <th style="padding: 10px">หน่วยงาน</th>
                                    <th style="padding: 10px; text-align: center">จำนวนที่เบิก</th>
                                    <th style="padding: 10px; text-align: right">เบิกล่าสุด</th>
                                </tr>
                            </thead>
                            <tbody>`;
                    
                    result.data.forEach(row => {
                        html += `
                            <tr style="border-bottom: 1px solid var(--border)">
                                <td style="padding: 12px 10px; font-weight: 500">${row.department}</td>
                                <td style="padding: 12px 10px; text-align: center; font-weight: 700">${row.total_used} อัน</td>
                                <td style="padding: 12px 10px; text-align: right; font-size: 0.85rem; color: var(--text-muted)">
                                    ${new Date(row.last_withdrawn).toLocaleDateString('th-TH', {day:'2-digit', month:'short', year:'2-digit'})}
                                </td>
                            </tr>`;
                    });
                    
                    html += '</tbody></table>';
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p style="text-align: center; padding: 40px; color: var(--text-muted)">ไม่พบประวัติการเบิกในรอบปีนี้</p>';
                }
            } catch (error) {
                content.innerHTML = '<p style="color: var(--danger)">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>';
            }
        };

        yearSelect.onchange = () => fetchData(yearSelect.value);
        fetchData(yearSelect.value);
    }

    // เรียกใช้งาน
    initAnalysisPage();
</script>
