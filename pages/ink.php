<?php
require_once '../db.php';

try {
    $stmt = $pdo->query("SELECT * FROM ink_stock WHERE deleted_at IS NULL ORDER BY name ASC");
    $inks = $stmt->fetchAll();

    // สรุปหมึกแยกตามยี่ห้อ พร้อมรายละเอียดแต่ละรุ่น
    $brandSummary = [];
    foreach ($inks as $i) {
        $brand = $i['brand'] ?: 'ไม่ระบุ';
        if (!isset($brandSummary[$brand])) {
            $brandSummary[$brand] = ['total' => 0, 'low' => 0, 'models' => []];
        }
        $brandSummary[$brand]['total'] += (int)$i['current_quantity'];
        if ($i['current_quantity'] <= $i['min_quantity']) {
            $brandSummary[$brand]['low']++;
        }
        $brandSummary[$brand]['models'][] = [
            'name' => $i['name'],
            'qty'  => (int)$i['current_quantity'],
            'min'  => (int)$i['min_quantity'],
        ];
    }
} catch (Exception $e) {
    $inks = [];
    $brandSummary = [];
}

$brandIcons = ['HP' => '🖨️', 'Brother' => '🤝', 'Epson' => '🖊️', 'ไม่ระบุ' => '❓'];
?>

<div class="page-header">
    <div>
        <h1>คลังหมึก</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px">เช็คสต๊อก รับเข้า และเบิกจ่ายหมึก</p>
    </div>
    <div class="page-header-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
        <select id="ink-type-filter" onchange="filterInkByType()" style="padding: 8px 12px; border-radius: 10px; border: 1px solid var(--border); background: white; font-size: 0.85rem; cursor: pointer;">
            <option value="all">📁 ทั้งหมด</option>
            <option value="เลเซอร์">🔲 เลเซอร์</option>
            <option value="น้ำหมึกInkjet">💧 น้ำหมึกInkjet</option>
            <option value="ตลับผ้าหมึก">📠 ตลับผ้าหมึก</option>
            <option value="ผ้าหมึกRefill">🔄 ผ้าหมึกRefill</option>
        </select>
        <button class="btn-secondary" onclick="window.open('api.php?action=export_low_ink_csv')" style="background: white; border: 1px solid var(--border);">
            <span>🛒</span> แจ้งสั่งซื้อ
        </button>
        <button class="btn-primary scan-trigger">
            <span>📷</span> แสกน
        </button>
        <button class="btn-primary" id="add-ink-btn" style="background: var(--success)">
            <span>+</span> เพิ่มใหม่
        </button>
    </div>
</div>

<?php if (!empty($brandSummary)): ?>
<!-- Summary Cards by Brand -->
<div style="margin-bottom: 20px">
    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px">
        สรุปคลังหมึกแยกตามยี่ห้อ
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 14px; padding-bottom: 8px; align-items: flex-start;">
        <?php foreach ($brandSummary as $brand => $data): ?>
        <?php
            $icon = $brandIcons[$brand] ?? '🖨️';
            $isLow = $data['low'] > 0;
            $isEmpty = $data['total'] == 0;
            $borderColor = $isEmpty ? '#ef4444' : ($isLow ? '#f59e0b' : '#10b981');
            $cardId = 'brand-card-' . preg_replace('/[^a-z0-9]/i', '', $brand);
        ?>
        <div style="
            flex: 1; min-width: 200px; max-width: 280px;
            background: white;
            border: 1.5px solid var(--border);
            border-top: 4px solid <?= $borderColor ?>;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
        ">
            <!-- Card Header (คลิกเพื่อ expand) -->
            <div onclick="toggleBrandCard('<?= $cardId ?>')" style="padding: 16px; cursor: pointer; user-select: none;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px">
                    <div style="display: flex; align-items: center; gap: 6px">
                        <span style="font-weight: 700; font-size: 0.95rem; color: #0f172a"><?= htmlspecialchars($brand) ?></span>
                    </div>
                    <span id="<?= $cardId ?>-arrow" style="font-size: 0.8rem; color: var(--text-muted); transition: transform 0.2s">▼</span>
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: <?= $borderColor ?>; line-height: 1.1; margin-bottom: 6px">
                    <?= number_format($data['total']) ?>
                    <span style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted)">กล่อง</span>
                </div>
                <?php if ($isEmpty): ?>
                    <div style="font-size: 0.72rem; background: rgba(239,68,68,0.1); color: #ef4444; border-radius: 8px; padding: 3px 8px; display: inline-block; font-weight: 600">❌ หมดสต๊อก</div>
                <?php elseif ($isLow): ?>
                    <div style="font-size: 0.72rem; background: rgba(245,158,11,0.1); color: #d97706; border-radius: 8px; padding: 3px 8px; display: inline-block; font-weight: 600">⚠️ ใกล้หมด <?= $data['low'] ?> รายการ</div>
                <?php else: ?>
                    <div style="font-size: 0.72rem; background: rgba(16,185,129,0.1); color: #059669; border-radius: 8px; padding: 3px 8px; display: inline-block; font-weight: 600">✅ พร้อมใช้งาน</div>
                <?php endif; ?>
            </div>

            <!-- Expandable model list -->
            <div id="<?= $cardId ?>" style="display: none; border-top: 1px solid var(--border); background: #f8fafc; padding: 10px 14px 12px;">
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px">รุ่นในคลัง</div>
                <?php foreach ($data['models'] as $model): ?>
                <?php
                    $modelIsLow = $model['qty'] <= $model['min'];
                    $modelIsEmpty = $model['qty'] == 0;
                    $qtyColor = $modelIsEmpty ? '#ef4444' : ($modelIsLow ? '#d97706' : '#059669');
                ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid #e2e8f0;">
                    <div style="font-size: 0.8rem; color: #374151; font-weight: 500; flex: 1; padding-right: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($model['name']) ?>">
                        <?= htmlspecialchars($model['name']) ?>
                    </div>
                    <div style="font-size: 0.82rem; font-weight: 700; color: <?= $qtyColor ?>; white-space: nowrap;">
                        <?= $model['qty'] ?> <span style="font-weight: 400; color: var(--text-muted); font-size: 0.7rem">กล่อง</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleBrandCard(id) {
    const el = document.getElementById(id);
    const arrow = document.getElementById(id + '-arrow');
    if (!el) return;
    const isOpen = el.style.display !== 'none';
    el.style.display = isOpen ? 'none' : 'block';
    if (arrow) arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
}
</script>
<?php endif; ?>

<div class="card" style="padding: 0; overflow: hidden">
    <div style="overflow-x: auto; -webkit-overflow-scrolling: touch">
        <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 800px">
        <thead style="background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--border)">
            <tr>
                <th style="padding: 16px 24px">รายการหมึก</th>
                <th style="padding: 16px 24px">ยี่ห้อ</th>
                <th style="padding: 16px 24px">รุ่น</th>
                <th style="padding: 16px 24px">ประเภท</th>
                <th style="padding: 16px 24px">จำนวนคงเหลือ</th>
                <th style="padding: 16px 24px">วันที่รับเข้า</th>
                <th style="padding: 16px 24px">สถานะ</th>
                <th style="padding: 16px 24px; text-align: center">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($inks)): ?>
                <tr>
                    <td colspan="7" style="padding: 40px; text-align: center; color: var(--text-muted)">
                        ไม่มีข้อมูลหมึกในระบบ
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($inks as $i): ?>
                    <tr class="ink-row" data-type="<?php echo htmlspecialchars($i['type']); ?>" style="border-bottom: 1px solid var(--border)">
                        <td style="padding: 16px 24px">
                            <div style="font-size: 0.8rem; color: var(--text-muted)"><?php echo htmlspecialchars($i['barcode']); ?></div>
                        </td>
                        <td style="padding: 16px 24px">
                            <span style="font-weight: 600">
                                <?php echo $i['brand'] ? htmlspecialchars($i['brand']) : '<span style="color:var(--text-muted)">—</span>'; ?>
                            </span>
                        </td>
                        <td style="padding: 16px 24px">
                            <span style="font-weight: 500">
                                <?php echo $i['name'] ? htmlspecialchars($i['name']) : '<span style="color:var(--text-muted)">—</span>'; ?>
                            </span>
                        </td>
                        <td style="padding: 16px 24px">
                            <span style="font-size: 0.85rem; background: #f1f5f9; padding: 4px 10px; border-radius: 8px;">
                                <?php 
                                    if($i['type'] === 'เลเซอร์' || $i['type'] === 'laser') echo '🔲 เลเซอร์';
                                    elseif($i['type'] === 'น้ำหมึกInkjet' || $i['type'] === 'liquid') echo '💧 น้ำหมึกInkjet';
                                    elseif($i['type'] === 'ตลับผ้าหมึก') echo '📠 ตลับผ้าหมึก';
                                    elseif($i['type'] === 'ผ้าหมึกRefill') echo '🔄 ผ้าหมึกRefill';
                                    else echo htmlspecialchars($i['type']);
                                ?>
                            </span>
                        </td>
                        <td style="padding: 16px 24px">
                            <span style="font-size: 1.1rem; font-weight: 700"><?php echo $i['current_quantity']; ?></span>
                            <span style="font-size: 0.85rem; color: var(--text-muted)"> กล่อง</span>
                            <?php if ($i['min_quantity'] > 0): ?>
                                <div style="font-size: 0.75rem; color: var(--text-muted)">แจ้งเตือนที่ ≤ <?php echo $i['min_quantity']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px 24px">
                            <?php if (!empty($i['created_at'])): ?>
                                <div style="font-size: 0.88rem; font-weight: 500">
                                    <?= thdate($i['created_at']) ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted)">
                                    <?= date('H:i', strtotime($i['created_at'])) ?> น.
                                </div>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.85rem">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px 24px">
                            <?php if ($i['current_quantity'] <= $i['min_quantity']): ?>
                                <span class="badge badge-broken">ของใกล้หมด</span>
                            <?php else: ?>
                                <span class="badge badge-normal">ปกติ</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px 24px; text-align: center">
                            <div style="display: flex; gap: 8px; justify-content: center">
                                <button onclick="openEditInk(<?php echo htmlspecialchars(json_encode($i)); ?>)" 
                                    style="background: #f1f5f9; border: none; padding: 8px; border-radius: 8px; cursor: pointer; color: var(--primary)" title="แก้ไข">✏️</button>
                                <button onclick="deleteInk(<?php echo $i['id']; ?>, '<?php echo htmlspecialchars(addslashes($i['name'])); ?>', this)" 
                                    style="background: #fff1f2; border: none; padding: 8px; border-radius: 8px; cursor: pointer; color: var(--danger)" title="ลบ">🗑️</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Modal สำหรับลงทะเบียนหมึกใหม่ -->
<div id="add-ink-modal" class="modal">
    <div class="modal-content" style="max-width: 500px">
        <div class="modal-header">
            <h3>ลงทะเบียนหมึกใหม่ 📦</h3>
            <button type="button" class="close-modal-btn" onclick="document.getElementById('add-ink-modal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>
        <form id="add-ink-form">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">บาร์โค้ด (Barcode)</label>
                    <input type="text" name="barcode" id="new-ink-barcode" placeholder="แสกนบาร์โค้ด หรือ เว้นว่างเพื่อสุ่ม" class="form-input" style="background: #f8fafc">
                </div>
                <div class="form-group">
                    <label class="form-label">ชื่อหมึก/รุ่น</label>
                    <input type="text" name="name" placeholder="เช่น HP 76A Black" required class="form-input">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px">
                    <div>
                        <label class="form-label">ประเภท</label>
                        <select name="type" class="form-input">
                            <option value="เลเซอร์">เลเซอร์</option>
                            <option value="น้ำหมึกInkjet">น้ำหมึกInkjet</option>
                            <option value="ตลับผ้าหมึก">ตลับผ้าหมึก</option>
                            <option value="ผ้าหมึกRefill">ผ้าหมึกRefill</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">ยี่ห้อ</label>
                        <select name="brand" class="form-input" onchange="
                            if(this.value === 'อื่นๆ') {
                                this.removeAttribute('name');
                                document.getElementById('add-ink-brand-other').setAttribute('name', 'brand');
                                document.getElementById('add-ink-brand-other').style.display = 'block';
                                document.getElementById('add-ink-brand-other').required = true;
                                document.getElementById('add-ink-brand-other').value = '';
                                document.getElementById('add-ink-brand-other').focus();
                            } else {
                                this.setAttribute('name', 'brand');
                                document.getElementById('add-ink-brand-other').removeAttribute('name');
                                document.getElementById('add-ink-brand-other').style.display = 'none';
                                document.getElementById('add-ink-brand-other').required = false;
                            }
                        ">
                            <option value="HP">HP</option>
                            <option value="Brother">Brother</option>
                            <option value="Epson">Epson</option>
                            <option value="อื่นๆ">อื่นๆ (โปรดระบุ)</option>
                        </select>
                        <input type="text" id="add-ink-brand-other" placeholder="โปรดระบุยี่ห้อ..." style="display:none; width:100%; margin-top: 10px;" class="form-input">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px">
                    <div>
                        <label class="form-label">จำนวน</label>
                        <input type="number" name="current_quantity" value="0" min="0" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">แจ้งเตือนที่ ≤</label>
                        <input type="number" name="min_quantity" value="5" min="1" class="form-input">
                    </div>
                </div>
                <button type="submit" id="save-ink-btn" class="btn-primary" style="width: 100%; justify-content: center; height: 50px">ลงทะเบียนหมึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal สำหรับแก้ไขข้อมูลหมึก -->
<div id="edit-ink-modal" class="modal">
    <div class="modal-content" style="max-width: 500px">
        <div class="modal-header">
            <h3>แก้ไขข้อมูลหมึก ✏️</h3>
            <button type="button" class="close-modal-btn" onclick="document.getElementById('edit-ink-modal').style.display='none'">&times;</button>
        </div>
        <form id="edit-ink-form">
            <input type="hidden" name="ink_id" id="edit-ink-id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">บาร์โค้ด (Barcode)</label>
                    <input type="text" name="barcode" id="edit-ink-barcode" required class="form-input" style="background: #f1f5f9" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">ชื่อหมึก/รุ่น</label>
                    <input type="text" name="name" id="edit-ink-name" required class="form-input">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px">
                    <div>
                        <label class="form-label">ประเภท</label>
                        <select name="type" id="edit-ink-type" class="form-input">
                            <option value="เลเซอร์">เลเซอร์</option>
                            <option value="น้ำหมึกInkjet">น้ำหมึกInkjet</option>
                            <option value="ตลับผ้าหมึก">ตลับผ้าหมึก</option>
                            <option value="ผ้าหมึกRefill">ผ้าหมึกRefill</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">ยี่ห้อ</label>
                        <select name="brand" id="edit-ink-brand" class="form-input" onchange="
                            if(this.value === 'อื่นๆ') {
                                this.removeAttribute('name');
                                document.getElementById('edit-ink-brand-other').setAttribute('name', 'brand');
                                document.getElementById('edit-ink-brand-other').style.display = 'block';
                                document.getElementById('edit-ink-brand-other').required = true;
                                document.getElementById('edit-ink-brand-other').focus();
                            } else {
                                this.setAttribute('name', 'brand');
                                document.getElementById('edit-ink-brand-other').removeAttribute('name');
                                document.getElementById('edit-ink-brand-other').style.display = 'none';
                                document.getElementById('edit-ink-brand-other').required = false;
                            }
                        ">
                            <option value="HP">HP</option>
                            <option value="Brother">Brother</option>
                            <option value="Epson">Epson</option>
                            <option value="อื่นๆ">อื่นๆ (โปรดระบุ)</option>
                        </select>
                        <input type="text" id="edit-ink-brand-other" placeholder="โปรดระบุยี่ห้อ..." style="display:none; width:100%; margin-top: 10px;" class="form-input">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px">
                    <div>
                        <label class="form-label">จำนวนปัจจุบัน</label>
                        <input type="number" name="current_quantity" id="edit-ink-qty" min="0" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">แจ้งเตือนที่ ≤</label>
                        <input type="number" name="min_quantity" id="edit-ink-min" min="1" class="form-input">
                    </div>
                </div>
                <button type="submit" id="update-ink-btn" class="btn-primary" style="width: 100%; justify-content: center; height: 50px">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>
<div id="stock-in-modal" class="modal">
    <div class="modal-content" style="max-width: 400px">
        <div class="modal-header">
            <h3>รับหมึกเข้าสต๊อก 📥</h3>
            <button type="button" class="close-modal-btn" onclick="document.getElementById('stock-in-modal').style.display='none'">&times;</button>
        </div>
        <form id="stock-in-form">
            <div class="modal-body" style="text-align: center">
                <div style="margin-bottom: 20px">
                    <h4 id="stock-in-name" style="margin-bottom: 4px; font-size: 1.1rem">หมึก XYZ</h4>
                    <p id="stock-in-barcode" style="color: var(--text-muted); font-size: 0.85rem">123456789</p>
                </div>
                <input type="hidden" name="barcode" id="stock-in-barcode-val">
                <div class="form-group">
                    <label class="form-label">จำนวนที่รับเข้า</label>
                    <input type="number" name="quantity" value="1" min="1" required class="form-input" style="width:120px; font-size: 1.5rem; text-align: center; margin: 0 auto; height: 60px; border-width: 2px">
                </div>
                <button type="submit" id="save-stock-in-btn" class="btn-primary" style="width: 100%; justify-content: center; height: 50px">ยืนยันการรับเข้า</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const addInkBtn = document.getElementById('add-ink-btn');
        const addInkModal = document.getElementById('add-ink-modal');
        const stockInModal = document.getElementById('stock-in-modal');
        // ฟังก์ชันกรองประเภทหมึก
        window.filterInkByType = () => {
            const filterValue = document.getElementById('ink-type-filter').value;
            const rows = document.querySelectorAll('.ink-row');
            
            rows.forEach(row => {
                const type = row.getAttribute('data-type');
                if (filterValue === 'all' || type === filterValue) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        };

        const addInkForm = document.getElementById('add-ink-form');
        const stockInForm = document.getElementById('stock-in-form');

        if (addInkBtn) {
            addInkBtn.onclick = () => {
                document.getElementById('new-ink-barcode').value = '';
                addInkModal.style.display = 'block';
            };
        }

        // ฟังก์ชันเปิด Modal ลงทะเบียนใหม่ (กรณีแสกนแล้วไม่เจอ)
        window.openAddInkModal = (barcode) => {
            document.getElementById('new-ink-barcode').value = barcode;
            addInkModal.style.display = 'block';
        };

        // ฟังก์ชันเปิด Modal รับสต๊อก (กรณีแสกนแล้วเจอ)
        window.openStockInModal = (ink) => {
            document.getElementById('stock-in-name').textContent = ink.name;
            document.getElementById('stock-in-barcode').textContent = ink.barcode;
            document.getElementById('stock-in-barcode-val').value = ink.barcode;
            stockInModal.style.display = 'block';
        };

        addInkForm.onsubmit = async (e) => {
            e.preventDefault();
            const btn = document.getElementById('save-ink-btn');
            btn.disabled = true; btn.innerHTML = 'กำลังบันทึก...';
            
            // Generate random barcode if empty
            const barcodeInput = document.getElementById('new-ink-barcode');
            if(!barcodeInput.value) {
                barcodeInput.value = 'INK-' + Math.floor(Math.random() * 1000000000);
            }

            const formData = new FormData(addInkForm);
            try {
                const response = await fetch('api.php?action=add_ink', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) { 
                    showToast(result.message, 'success'); 
                    if(window.loadPage) window.loadPage('ink'); else location.reload();
                }
                else { showToast('เกิดข้อผิดพลาด: ' + result.message, 'error'); }
            } catch (error) { console.error(error); }
            btn.disabled = false; btn.innerHTML = 'ลงทะเบียนหมึก';
        };

        stockInForm.onsubmit = async (e) => {
            e.preventDefault();
            const btn = document.getElementById('save-stock-in-btn');
            btn.disabled = true; btn.innerHTML = 'กำลังบันทึก...';

            const formData = new FormData(stockInForm);
            try {
                const response = await fetch('api.php?action=update_stock', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) {
                    showToast(result.message, 'success');
                    stockInModal.style.display = 'none';
                    // อัปเดตตัวเลขจำนวนในตารางทันที
                    const barcodeVal = document.getElementById('stock-in-barcode-val').value;
                    const qtyAdded = parseInt(formData.get('quantity')) || 0;
                    document.querySelectorAll('.ink-row').forEach(row => {
                        const barcodeCell = row.querySelector('td:first-child div');
                        if (barcodeCell && barcodeCell.textContent.trim() === barcodeVal) {
                            const qtySpan = row.querySelector('td:nth-child(5) span:first-child');
                            if (qtySpan) {
                                const currentQty = parseInt(qtySpan.textContent) || 0;
                                const newQty = currentQty + qtyAdded;
                                qtySpan.textContent = newQty;
                                // อัปเดต badge สถานะ
                                const minText = row.querySelector('td:nth-child(5) div');
                                const minQty = minText ? parseInt(minText.textContent.replace(/[^0-9]/g,'')) || 0 : 0;
                                const badgeCell = row.querySelector('td:nth-child(7) span');
                                if (badgeCell) {
                                    if (newQty <= minQty) {
                                        badgeCell.className = 'badge badge-broken';
                                        badgeCell.textContent = 'ของใกล้หมด';
                                    } else {
                                        badgeCell.className = 'badge badge-normal';
                                        badgeCell.textContent = 'ปกติ';
                                    }
                                }
                            }
                        }
                    });
                }
                else { showToast('เกิดข้อผิดพลาด: ' + result.message, 'error'); }
            } catch (error) { console.error(error); }
            btn.disabled = false; btn.innerHTML = 'ยืนยันการรับเข้า';
        };

        // ระบบแก้ไขหมึก
        window.openEditInk = (ink) => {
            document.getElementById('edit-ink-id').value = ink.id;
            document.getElementById('edit-ink-barcode').value = ink.barcode;
            document.getElementById('edit-ink-name').value = ink.name;
            document.getElementById('edit-ink-type').value = ink.type;
            
            const brandSelect = document.getElementById('edit-ink-brand');
            const brandOtherInput = document.getElementById('edit-ink-brand-other');
            const standardBrands = ['HP', 'Brother', 'Epson'];
            if (ink.brand && !standardBrands.includes(ink.brand)) {
                brandSelect.value = 'อื่นๆ';
                brandSelect.removeAttribute('name');
                brandOtherInput.setAttribute('name', 'brand');
                brandOtherInput.style.display = 'block';
                brandOtherInput.required = true;
                brandOtherInput.value = ink.brand;
            } else {
                brandSelect.value = ink.brand || '';
                brandSelect.setAttribute('name', 'brand');
                brandOtherInput.removeAttribute('name');
                brandOtherInput.style.display = 'none';
                brandOtherInput.required = false;
                brandOtherInput.value = '';
            }
            document.getElementById('edit-ink-qty').value = ink.current_quantity;
            document.getElementById('edit-ink-min').value = ink.min_quantity;
            document.getElementById('edit-ink-modal').style.display = 'block';
        };

        const editInkForm = document.getElementById('edit-ink-form');
        editInkForm.onsubmit = async (e) => {
            e.preventDefault();
            const btn = document.getElementById('update-ink-btn');
            btn.disabled = true; btn.innerHTML = 'กำลังบันทึก...';
            const formData = new FormData(editInkForm);
            const inkId = formData.get('ink_id');
            try {
                const response = await fetch('api.php?action=edit_ink', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) {
                    showToast('แก้ไขข้อมูลเรียบร้อย', 'success');
                    document.getElementById('edit-ink-modal').style.display = 'none';
                    // อัปเดต row ใน DOM ทันที
                    const newName = formData.get('name');
                    const newBrand = formData.get('brand');
                    const newType = formData.get('type');
                    const newQty = formData.get('current_quantity');
                    const newMin = formData.get('min_quantity');
                    document.querySelectorAll('.ink-row').forEach(row => {
                        // หา row จาก delete button ที่มี data-ink-id ตรงกัน
                        const delBtn = row.querySelector(`[data-ink-id="${inkId}"]`);
                        if (delBtn) {
                            // อัปเดต brand
                            const brandCell = row.querySelector('td:nth-child(2) span');
                            if (brandCell) brandCell.textContent = newBrand || '—';
                            // อัปเดต name (model)
                            const nameCell = row.querySelector('td:nth-child(3) span');
                            if (nameCell) nameCell.textContent = newName || '—';
                            // อัปเดตจำนวน
                            const qtySpan = row.querySelector('td:nth-child(5) span:first-child');
                            if (qtySpan) qtySpan.textContent = newQty;
                            // อัปเดต badge สถานะ
                            const badgeCell = row.querySelector('td:nth-child(7) span');
                            if (badgeCell) {
                                if (parseInt(newQty) <= parseInt(newMin)) {
                                    badgeCell.className = 'badge badge-broken';
                                    badgeCell.textContent = 'ของใกล้หมด';
                                } else {
                                    badgeCell.className = 'badge badge-normal';
                                    badgeCell.textContent = 'ปกติ';
                                }
                            }
                        }
                    });
                } else { showToast('เกิดข้อผิดพลาด: ' + result.message, 'error'); }
            } catch (error) { console.error(error); }
            btn.disabled = false; btn.innerHTML = 'บันทึกการแก้ไข';
        };

        // ระบบลบหมึก
        window.deleteInk = async (id, name, btn) => {
            const ok = await showConfirm({
                title: 'ย้ายไปถังขยะ?',
                message: `คุณต้องการย้ายรายการหมึก "${name}" ไปที่ถังขยะใช่หรือไม่?\n*คุณสามารถกู้คืนได้ภายหลังจากเมนูถังขยะ`,
                confirmText: 'ย้ายไปถังขยะ',
                type: 'danger'
            });
            if (ok) {
                try {
                    const response = await fetch('api.php?action=delete_ink', { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `ink_id=${id}`
                    });
                    const result = await response.json();
                    if(result.success) {
                        showToast('ย้ายหมึกไปถังขยะแล้ว', 'info');
                        // ลบ row ออกจาก DOM ทันที
                        const row = btn.closest('tr');
                        if (row) {
                            row.style.transition = 'opacity 0.3s, transform 0.3s';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(30px)';
                            setTimeout(() => row.remove(), 300);
                        }
                    } else { showToast('ผิดพลาด: ' + result.message, 'error'); }
                } catch (error) { console.error(error); }
            }
        };
    })();
</script>
