<?php
require_once '../db.php';

// ดึงข้อมูลปริ้นเตอร์ทั้งหมด
try {
    // --- TEMPORARY SEEDER START ---
    $checkStmt = $pdo->query("SELECT COUNT(*) FROM printers");
    if ($checkStmt->fetchColumn() < 1) { 
        $departments_seed = ["OPD กุมารเวชกรรม", "กลุ่มงานการแพทย์", "ตึกหญิง", "ตึกชาย"];
        $brands_seed = ["HP", "Canon", "Brother", "Epson", "Samsung"];
        $models_seed = ["LaserJet Pro M404n", "Pixma G3010", "HL-L2370DW", "L3150", "Xpress M2020"];
        $statuses_seed = ["normal", "normal", "normal", "repairing", "broken"];

        foreach ($departments_seed as $dept) {
            for ($i = 1; $i <= 5; $i++) {
                $brand = $brands_seed[array_rand($brands_seed)];
                $model = $models_seed[array_rand($models_seed)];
                $serial = strtoupper(substr(md5(uniqid()), 0, 10));
                $status = $statuses_seed[array_rand($statuses_seed)];
                $qr_id = 'PRN-' . strtoupper(substr(md5(time() . $serial . $i), 0, 8));
                $stmt = $pdo->prepare("INSERT INTO printers (brand, model, serial_number, department, qr_code_id, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$brand, $model, $serial, $dept, $qr_id, $status]);
            }
        }
    }
    // --- TEMPORARY SEEDER END ---

    $stmt = $pdo->query("SELECT * FROM printers WHERE deleted_at IS NULL AND status != 'retired' ORDER BY department ASC, brand ASC");
    $printers = $stmt->fetchAll();
    
    // จัดกลุ่มตามหน่วยงาน
    $groupedPrinters = [];
    foreach ($printers as $p) {
        $dept = $p['department'] ?: 'ไม่ระบุหน่วยงาน';
        if (!isset($groupedPrinters[$dept])) {
            $groupedPrinters[$dept] = [
                'items' => [],
                'stats' => ['total' => 0, 'normal' => 0, 'repairing' => 0, 'broken' => 0]
            ];
        }
        $groupedPrinters[$dept]['items'][] = $p;
        $groupedPrinters[$dept]['stats']['total']++;
        $groupedPrinters[$dept]['stats'][$p['status']]++;
    }

    // === Hierarchy: map sub-unit → parent group ===
    $stmtDept = $pdo->query("SELECT group_name, sub_name FROM departments ORDER BY group_name ASC, sub_name ASC");
    $rowsDept = $stmtDept->fetchAll();
    $hierMap = [];
    foreach ($rowsDept as $r) {
        $grp = $r['group_name'];
        if (!isset($hierMap[$grp])) $hierMap[$grp] = [];
        if (!empty($r['sub_name'])) {
            $hierMap[$grp][] = $r['sub_name'];
        }
    }
    // สร้าง reverse map: sub-unit → parent
    $subToParent = [];
    foreach ($hierMap as $parent => $subs) {
        foreach ($subs as $s) { $subToParent[$s] = $parent; }
    }
    // จัดกลุ่มระดับ 2: parent group → [sub-units ที่มีข้อมูล]
    $superGrouped = []; // parentGroup => ['subs' => [deptName => group], 'stats' => [...]]
    $standalone = [];    // dept ที่ไม่มี parent (หรือเป็น parent เอง)
    foreach ($groupedPrinters as $deptName => $group) {
        if (isset($subToParent[$deptName])) {
            $parent = $subToParent[$deptName];
            if (!isset($superGrouped[$parent])) {
                $superGrouped[$parent] = ['subs' => [], 'stats' => ['total'=>0,'normal'=>0,'repairing'=>0,'broken'=>0]];
            }
            $superGrouped[$parent]['subs'][$deptName] = $group;
            $superGrouped[$parent]['stats']['total'] += $group['stats']['total'];
            $superGrouped[$parent]['stats']['normal'] += $group['stats']['normal'];
            $superGrouped[$parent]['stats']['repairing'] += $group['stats']['repairing'];
            $superGrouped[$parent]['stats']['broken'] += $group['stats']['broken'];
        } elseif (isset($hierMap[$deptName])) {
            // เป็นกลุ่มงานหลักเอง → เก็บไว้ใน key พิเศษ '_direct' แทนการสร้าง sub-card ซ้อน
            if (!isset($superGrouped[$deptName])) {
                $superGrouped[$deptName] = ['subs' => [], 'direct' => [], 'stats' => ['total'=>0,'normal'=>0,'repairing'=>0,'broken'=>0]];
            }
            // เก็บ items ไว้ใน 'direct' สำหรับแสดงใน header โดยตรง
            if (!isset($superGrouped[$deptName]['direct'])) $superGrouped[$deptName]['direct'] = [];
            $superGrouped[$deptName]['direct'] = array_merge($superGrouped[$deptName]['direct'], $group['items']);
            $superGrouped[$deptName]['stats']['total']    += $group['stats']['total'];
            $superGrouped[$deptName]['stats']['normal']   += $group['stats']['normal'];
            $superGrouped[$deptName]['stats']['repairing']+= $group['stats']['repairing'];
            $superGrouped[$deptName]['stats']['broken']   += $group['stats']['broken'];
        } else {
            $standalone[$deptName] = $group;
        }
    }

    // คำนวณสรุปปริ้นเตอร์แต่ละยี่ห้อและรุ่น (รวมรุ่นที่พิมพ์ชื่อต่างกันเล็กน้อย)
    $brandSummary = [];
    $originalNamesCount = []; // เก็บความถี่ของชื่อที่พิมพ์เพื่อนำชื่อที่ใช้บ่อยสุดมาแสดง

    foreach ($printers as $p) {
        $brandRaw = trim($p['brand']) ?: 'ไม่ระบุ';
        $brandNorm = ucfirst(strtolower($brandRaw));
        if (strtoupper($brandNorm) === 'HP') $brandNorm = 'HP';
        
        $model = trim($p['model']) ?: 'ไม่ระบุ';
        $rawName = "$brandRaw $model";
        
        // 1. ทำเป็นตัวพิมพ์ใหญ่ทั้งหมด
        $norm = strtoupper($rawName);
        // 2. ตัดคำฟุ่มเฟือยที่มักพิมพ์ไม่ตรงกันออก
        $noise = ['TANK', 'INK', 'WIRELESS', 'SMART', 'WIFI', 'PRINTER', 'SERIES'];
        foreach ($noise as $w) {
            $norm = str_replace($w, '', $norm);
        }
        // 3. ลบอักขระพิเศษ ช่องว่าง และขีดต่างๆ ออกให้เหลือแต่ตัวอักษรและตัวเลข
        $normKey = preg_replace('/[^A-Z0-9]/', '', $norm);
        
        if (!isset($brandSummary[$brandNorm])) {
            $brandSummary[$brandNorm] = [
                'stats' => ['total' => 0, 'normal' => 0, 'repairing' => 0, 'broken' => 0],
                'models' => []
            ];
        }

        if (!isset($brandSummary[$brandNorm]['models'][$normKey])) {
            $brandSummary[$brandNorm]['models'][$normKey] = [
                'total' => 0, 
                'normal' => 0, 
                'repairing' => 0, 
                'broken' => 0, 
                'display_name' => $rawName 
            ];
            $originalNamesCount[$normKey] = [];
        }
        
        $brandSummary[$brandNorm]['stats']['total']++;
        $brandSummary[$brandNorm]['stats'][$p['status']]++;
        
        $brandSummary[$brandNorm]['models'][$normKey]['total']++;
        $brandSummary[$brandNorm]['models'][$normKey][$p['status']]++;
        
        if (!isset($originalNamesCount[$normKey][$rawName])) {
            $originalNamesCount[$normKey][$rawName] = 0;
        }
        $originalNamesCount[$normKey][$rawName]++;
    }

    // เลือกชื่อ Display Name เป็นชื่อที่มีคนพิมพ์เข้ามาเยอะที่สุดสำหรับกลุ่มนั้น และเรียงลำดับ
    foreach ($brandSummary as $brandName => &$bData) {
        foreach ($bData['models'] as $key => &$mData) {
            $names = $originalNamesCount[$key];
            arsort($names); // เรียงจากความถี่มากไปน้อย
            $mData['display_name'] = array_key_first($names);
        }
        unset($mData);
        
        uasort($bData['models'], function($a, $b) {
            return $b['total'] <=> $a['total'];
        });
    }
    unset($bData);

    uasort($brandSummary, function($a, $b) {
        return $b['stats']['total'] <=> $a['stats']['total'];
    });
} catch (Exception $e) {
    $printers = [];
    $groupedPrinters = [];
    $superGrouped = [];
    $standalone = [];
    $brandSummary = [];
}
?>

<div class="page-header">
    <div>
        <h1>ปริ้นเตอร์ 🖨️</h1>
        <p style="color:var(--text-muted);font-size:0.9rem;margin-top:4px">จัดการข้อมูลและสร้าง QR Code สำหรับเครื่องปริ้น</p>
    </div>
    <div class="page-header-actions">
        <button class="btn-secondary" onclick="window.open('api.php?action=export_printers_csv')">
            <span>📥</span> Export CSV
        </button>
        <button class="btn-secondary" id="print-all-qr-btn">
            <span>🖨️</span> พิมพ์ QR
        </button>
        <button class="btn-danger" onclick="window.loadPage('trash')">
            <span>🗑️</span> ถังขยะ
        </button>
        <button class="btn-primary" id="add-printer-btn">
            <span>+</span> เพิ่ม
        </button>
    </div>
</div>

<?php
// สร้างรายชื่อหน่วยงานทั้งหมดสำหรับ Dropdown
$allDepts = array_keys($groupedPrinters);
?>

<!-- สรุปปริ้นเตอร์แยกตามยี่ห้อ (อยู่บนสุด) -->
<?php if (!empty($brandSummary)): ?>
<div style="margin-bottom: 32px">
    <h3 style="font-size: 1.05rem; margin-bottom: 12px; color: var(--text-main); font-weight: 700;">🖨️ สรุปจำนวนเครื่องแยกตามยี่ห้อและรุ่น</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; align-items: start;">
        <?php $brandIdx = 0; foreach ($brandSummary as $brandName => $bData):
            $brandIdx++;
            $bStats = $bData['stats'];
            $bNormal = $bStats['normal'];
            $bTotal = $bStats['total'];
            $bIssues = $bStats['repairing'] + $bStats['broken'];
            $bBorderColor = ($bNormal == 0) ? '#ef4444' : ($bIssues > 0 ? '#f59e0b' : '#10b981');
            $modelCount = count($bData['models']);
        ?>
        <div style="
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            box-shadow: none;
            overflow: hidden;
        ">
            <div onclick="toggleSubDetail('brand-<?= $brandIdx ?>')" style="padding: 16px; cursor: pointer; transition: background 0.2s;" onmouseenter="this.style.background='#f1f5f9'" onmouseleave="this.style.background=''">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 10px; height: 10px; border-radius: 50%; background: <?= $bBorderColor ?>;"></div>
                        <span style="font-weight: 800; font-size: 1.15rem; color: #1e293b; letter-spacing: -0.3px;">
                            <?= htmlspecialchars($brandName) ?>
                        </span>
                    </div>
                    <span id="brand-<?= $brandIdx ?>-chevron" style="font-size:0.7rem; color:var(--text-muted); transition:transform 0.3s;">▼</span>
                </div>
                <div style="font-size: 0.72rem; color: var(--primary); margin-bottom: 8px; font-weight:600;">แยกเป็น <?= $modelCount ?> รุ่น — กดเพื่อดู</div>
                <div style="display: flex; align-items: baseline; gap: 6px;">
                    <span style="font-size: 2.2rem; font-weight: 800; color: <?= $bBorderColor ?>; line-height: 1;"><?= $bNormal ?></span>
                    <span style="font-size: 1rem; color: var(--text-muted); font-weight: 600;">/ <?= $bTotal ?> เครื่อง</span>
                </div>
                <?php if ($bTotal == 0): ?>
                    <div style="margin-top: 6px; font-size: 0.75rem; color: #ef4444; font-weight: 600">❌ ไม่มีในระบบ</div>
                <?php elseif ($bIssues > 0): ?>
                    <div style="margin-top: 6px; font-size: 0.75rem; color: #d97706; font-weight: 600">⚠️ รอซ่อม/เสีย <?= $bIssues ?> เครื่อง</div>
                <?php else: ?>
                    <div style="margin-top: 6px; font-size: 0.75rem; color: #059669; font-weight: 600">✅ พร้อมใช้งาน 100%</div>
                <?php endif; ?>
            </div>
            <div id="brand-<?= $brandIdx ?>" style="max-height:0; overflow:hidden; transition:max-height 0.35s ease; border-top:0px solid transparent; background: #f8fafc;">
                <div style="padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($bData['models'] as $mKey => $mData):
                        $mNormal = $mData['normal'];
                        $mTotal = $mData['total'];
                        $mIssues = $mData['repairing'] + $mData['broken'];
                        $mBorderColor = ($mNormal == 0) ? '#ef4444' : ($mIssues > 0 ? '#f59e0b' : '#10b981');
                    ?>
                    <div style="background: white; border: 1px solid var(--border); border-left: 4px solid <?= $mBorderColor ?>; border-radius: 8px; padding: 10px 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                        <div style="font-weight: 700; font-size: 0.85rem; color: #1e293b; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($mData['display_name']) ?>">
                            <?= htmlspecialchars($mData['display_name']) ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-size: 0.7rem; font-weight: 600;">
                                <?php if ($mTotal == 0): ?>
                                    <span style="color: #ef4444;">❌ ไม่มี</span>
                                <?php elseif ($mIssues > 0): ?>
                                    <span style="color: #d97706;">⚠️ ซ่อม <?= $mIssues ?></span>
                                <?php else: ?>
                                    <span style="color: #059669;">✅ ปกติ</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 1rem; font-weight: 800; color: <?= $mBorderColor ?>;">
                                <?= $mNormal ?> <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">/ <?= $mTotal ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- สรุปแต่ละกลุ่มงาน -->
<?php if (!empty($superGrouped) || !empty($standalone)): ?>
<div style="margin-bottom: 24px;">
    <h3 style="font-size: 1.05rem; margin-bottom: 12px; color: var(--text-main); font-weight: 700;">📊 สรุปความพร้อมใช้งานแต่ละกลุ่มงาน</h3>
    <div class="dept-summary-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px;">
        <?php $sgIdx = 0; foreach ($superGrouped as $parentName => $sg):
            $normal = $sg['stats']['normal'];
            $total = $sg['stats']['total'];
            $issues = $sg['stats']['repairing'] + $sg['stats']['broken'];
            $subCount = count($sg['subs']);
            $sgIdx++;
        ?>
            <div style="background: white; border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); border-top: 4px solid <?= $normal == $total ? 'var(--success)' : 'var(--warning)' ?>; overflow:hidden;">
                <!-- Clickable header -->
                <div onclick="toggleSubDetail('sg-<?= $sgIdx ?>')" style="padding: 14px 18px; cursor: pointer; transition: background 0.2s;"
                    onmouseenter="this.style.background='rgba(99,102,241,0.03)'" onmouseleave="this.style.background=''">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex:1;" title="<?= htmlspecialchars($parentName) ?>">
                            🏢 <?= htmlspecialchars($parentName) ?>
                        </div>
                        <?php if ($subCount > 0): ?>
                            <span id="sg-<?= $sgIdx ?>-chevron" style="font-size:0.7rem; color:var(--text-muted); transition:transform 0.3s; display:inline-block; flex-shrink:0; margin-left:6px;">▼</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($subCount > 0): ?>
                        <div style="font-size: 0.68rem; color: var(--primary); margin-bottom: 6px; font-weight:500;"><?= $subCount ?> หน่วยงานย่อย — กดเพื่อดู</div>
                    <?php endif; ?>
                    <div style="display: flex; align-items: baseline; gap: 4px;">
                        <span style="font-size: 1.8rem; font-weight: 800; color: <?= $normal == 0 ? 'var(--danger)' : 'var(--success)' ?>; line-height: 1;"><?= $normal ?></span>
                        <span style="font-size: 1rem; color: var(--text-muted); font-weight: 600;">/ <?= $total ?></span>
                    </div>
                    <div style="font-size: 0.75rem; margin-top: 6px; font-weight: 600; color: <?= $issues > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                        <?php if ($issues > 0): ?>
                            ⚠️ ซ่อม/เสีย: <?= $issues ?>
                        <?php else: ?>
                            ✅ พร้อมใช้งาน 100%
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Expandable sub-unit details -->
                <?php if ($subCount > 0): ?>
                <div id="sg-<?= $sgIdx ?>" style="max-height:0; overflow:hidden; transition:max-height 0.35s ease; border-top:0px solid transparent;">
                    <div style="padding: 0 14px 12px;">
                        <?php foreach ($sg['subs'] as $subName => $subGroup):
                            $sn = $subGroup['stats']['normal'];
                            $st = $subGroup['stats']['total'];
                            $si = $subGroup['stats']['repairing'] + $subGroup['stats']['broken'];
                        ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; border-radius:10px; background:rgba(99,102,241,0.03); margin-bottom:4px;">
                                <span style="font-size:0.8rem; font-weight:600; color:var(--text-main); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1;" title="<?= htmlspecialchars($subName) ?>">└ <?= htmlspecialchars($subName) ?></span>
                                <span style="font-size:0.8rem; font-weight:700; color:<?= $sn == $st ? 'var(--success)' : 'var(--warning)' ?>; white-space:nowrap; margin-left:8px;"><?= $sn ?>/<?= $st ?><?php if($si > 0) echo ' <span style="color:var(--danger)">⚠️'.$si.'</span>'; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php foreach ($standalone as $deptName => $group):
            $normal = $group['stats']['normal'];
            $total = $group['stats']['total'];
            $issues = $group['stats']['repairing'] + $group['stats']['broken'];
        ?>
            <div style="background: white; border: 1px solid var(--border); border-radius: 16px; padding: 14px 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid <?= $normal == $total ? 'var(--success)' : 'var(--warning)' ?>;">
                <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($deptName) ?>">
                    <?= htmlspecialchars($deptName) ?>
                </div>
                <div style="display: flex; align-items: baseline; gap: 4px;">
                    <span style="font-size: 1.8rem; font-weight: 800; color: <?= $normal == 0 ? 'var(--danger)' : 'var(--success)' ?>; line-height: 1;"><?= $normal ?></span>
                    <span style="font-size: 1rem; color: var(--text-muted); font-weight: 600;">/ <?= $total ?></span>
                </div>
                <div style="font-size: 0.75rem; margin-top: 6px; font-weight: 600; color: <?= $issues > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                    <?php if ($issues > 0): ?>
                        ⚠️ ซ่อม/เสีย: <?= $issues ?>
                    <?php else: ?>
                        ✅ พร้อมใช้งาน 100%
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>



<!-- Filter Bar -->
<div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:center">
    <div style="position:relative; flex:1; min-width:180px">
        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none">🔍</span>
        <input type="text" id="printer-search" placeholder="ค้นหาชื่อ, รุ่น, เลขเครื่อง..."
            class="form-input" style="padding-left:36px">
    </div>
    <select id="dept-filter" class="form-input" style="min-width:160px;cursor:pointer">
        <option value="">📂 ทุกหน่วยงาน/กลุ่มงาน</option>
        <?php foreach ($allDepts as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
    </select>
    <div id="filter-result-count" style="font-size:0.82rem;color:var(--text-muted);white-space:nowrap"></div>
</div>

<?php
// โหลดรูปภาพทุกเครื่อง (batch ครั้งเดียว)
$printerIds = array_column($printers, 'id');
$imagesMap = [];
if ($printerIds) {
    try {
        $in = implode(',', array_fill(0, count($printerIds), '?'));
        $stmt = $pdo->prepare("SELECT id, printer_id, filename, sort_order FROM printer_images WHERE printer_id IN ($in) ORDER BY printer_id, sort_order ASC");
        $stmt->execute($printerIds);
        foreach ($stmt->fetchAll() as $img) {
            $img['url'] = 'assets/img/printers/' . $img['filename'];
            $imagesMap[$img['printer_id']][] = $img;
        }
    } catch (Exception $e) {
        // ตาราง printer_images อาจยังไม่ถูกสร้าง — ข้ามไปก่อน
        $imagesMap = [];
    }
}
?>

<?php if (empty($groupedPrinters)): ?>
    <div class="card" style="text-align: center; padding: 60px">
        <div style="font-size: 4rem; margin-bottom: 20px">🖨️</div>
        <h3>ยังไม่มีข้อมูลปริ้นเตอร์</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px">เริ่มต้นโดยการเพิ่มปริ้นเตอร์เครื่องแรกเข้าสู่ระบบ</p>
        <button class="btn-primary open-add-printer" style="margin: 0 auto">เริ่มเพิ่มข้อมูล</button>
    </div>
<?php else: ?>

<?php
// === ฟังก์ชันวาดการ์ดปริ้นเตอร์ภายในแต่ละหน่วยงาน ===
function renderSubUnitCard($deptName, $group, $imagesMap) {
    $searchText = strtolower($deptName);
    foreach($group['items'] as $pi) {
        $searchText .= ' ' . strtolower($pi['brand'] . ' ' . $pi['model'] . ' ' . $pi['serial_number']);
    }
    ?>
    <div class="dept-card sub-unit-card" data-dept="<?= htmlspecialchars($deptName) ?>" data-search="<?= htmlspecialchars($searchText) ?>"
        style="margin-bottom: 20px; padding: 0; overflow: hidden; border-radius: 20px; border: 1px solid var(--border); box-shadow: 0 2px 12px rgba(0,0,0,0.03); background:white;">
        <div class="dept-header">
            <div style="display:flex;align-items:center;gap:10px">
                <div style="width:4px;height:20px;background:var(--primary);border-radius:2px"></div>
                <h2 style="margin:0;font-size:1rem;font-weight:700"><?= htmlspecialchars($deptName) ?></h2>
            </div>
            <div class="dept-stats">
                <div style="background:rgba(16,185,129,0.1);padding:3px 10px;border-radius:20px;color:var(--success)">● ใช้งาน <span class="ds-normal"><?= $group['stats']['normal'] ?></span>/<span class="ds-total"><?= $group['stats']['total'] ?></span></div>
                <?php if ($group['stats']['repairing'] > 0): ?>
                    <div style="background:rgba(245,158,11,0.1);padding:3px 10px;border-radius:20px;color:var(--warning)">🔧 <?= $group['stats']['repairing'] ?></div>
                <?php endif; ?>
                <?php if ($group['stats']['broken'] > 0): ?>
                    <div style="background:rgba(239,68,68,0.1);padding:3px 10px;border-radius:20px;color:var(--danger)">❌ <?= $group['stats']['broken'] ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="printer-grid">
            <?php foreach ($group['items'] as $p):
                $coverImg = !empty($imagesMap[$p['id']]) ? $imagesMap[$p['id']][0] : null;
                $imgCount = count($imagesMap[$p['id']] ?? []);
                $statusColors = ['normal'=>'#10b981','repairing'=>'#f59e0b','broken'=>'#ef4444'];
                $statusLabels = ['normal'=>'ปกติ','repairing'=>'กำลังซ่อม','broken'=>'พัง'];
                $statusColor = $statusColors[$p['status']] ?? '#94a3b8';
                $statusLabel = $statusLabels[$p['status']] ?? $p['status'];
            ?>
                <div class="printer-card" style="border-radius:18px; border:1.5px solid var(--border); overflow:hidden; background:white; box-shadow:0 2px 12px rgba(0,0,0,0.05); transition:all 0.25s; cursor:pointer; position:relative;"
                    onclick="if(window.loadPage){ window.location.hash='printer_details?qr_id=<?= $p['qr_code_id'] ?>'; window.loadPage('printer_details?qr_id=<?= $p['qr_code_id'] ?>'); } else { window.location.hash='printer_details?qr_id=<?= $p['qr_code_id'] ?>'; }"
                    onmouseenter="this.style.boxShadow='0 8px 32px rgba(0,0,0,0.12)'; this.style.transform='translateY(-3px)'"
                    onmouseleave="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.05)'; this.style.transform=''">
                    <div style="position:relative; width:100%; aspect-ratio:1/1; background:#f1f5f9; overflow:hidden;">
                        <?php if ($coverImg): ?>
                            <img src="<?= htmlspecialchars($coverImg['url']) ?>" style="width:100%;height:100%;object-fit:cover;display:block;" alt="printer photo">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px"><span style="font-size:3rem">🖨️</span><span style="font-size:0.72rem;color:var(--text-muted)">ยังไม่มีรูปภาพ</span></div>
                        <?php endif; ?>
                        <div style="position:absolute;top:10px;left:10px;background:<?= $statusColor ?>;color:white;font-size:0.7rem;font-weight:700;padding:3px 10px;border-radius:20px;box-shadow:0 2px 6px rgba(0,0,0,0.2)"><?= $statusLabel ?></div>
                        <?php if ($imgCount > 0): ?>
                            <div style="position:absolute;bottom:10px;left:10px;background:rgba(0,0,0,0.5);color:white;font-size:0.72rem;padding:3px 8px;border-radius:12px;backdrop-filter:blur(4px)">📷 <?= $imgCount ?></div>
                        <?php endif; ?>
                        <button class="photo-mgr-btn" onclick="event.stopPropagation(); openPhotoManager(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['brand'].' '.$p['model'])) ?>')"
                            style="position:absolute;bottom:10px;right:10px;background:rgba(255,255,255,0.92);border:none;border-radius:10px;padding:5px 10px;font-size:0.75rem;font-weight:600;cursor:pointer;backdrop-filter:blur(4px);transition:all 0.2s;color:#374151;box-shadow:0 2px 8px rgba(0,0,0,0.15)"
                            onmouseenter="this.style.background='white'" onmouseleave="this.style.background='rgba(255,255,255,0.92)'">📷 จัดการรูป</button>
                        <button class="quick-delete-btn" onclick="event.stopPropagation(); quickDeletePrinter(<?= $p['id'] ?>, '<?= $p['status'] ?>', '<?= md5($deptName) ?>', this)"
                            style="position:absolute;top:8px;right:8px;width:30px;height:30px;background:rgba(0,0,0,0.45);border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.85rem;cursor:pointer;backdrop-filter:blur(6px);transition:all 0.2s;box-shadow:0 2px 6px rgba(0,0,0,0.25);line-height:1;"
                            onmouseenter="this.style.background='rgba(239,68,68,0.9)'" onmouseleave="this.style.background='rgba(0,0,0,0.45)'" title="ลบปริ้นเตอร์">🗑️</button>
                    </div>
                    <div style="padding:10px 12px 12px;">
                        <div style="font-weight:700;font-size:0.85rem;color:var(--text-main);margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= htmlspecialchars($p['brand'] . ' ' . $p['model']) ?></div>
                        <div style="font-size:0.7rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">SN: <?= htmlspecialchars($p['serial_number']) ?></div>
                        <?php if(!empty($p['location'])): ?>
                        <div style="font-size:0.7rem;color:var(--primary);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">📍 <?= htmlspecialchars($p['location']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
?>

    <!-- === กลุ่มงานหลัก (Parent Group Wrappers) === -->
    <?php foreach ($superGrouped as $parentName => $sg):
        $sgStats = $sg['stats'];
        $sgSearchText = strtolower($parentName);
        foreach ($sg['subs'] as $subName => $subGroup) {
            $sgSearchText .= ' ' . strtolower($subName);
            foreach ($subGroup['items'] as $pi) { $sgSearchText .= ' ' . strtolower($pi['brand'] . ' ' . $pi['model'] . ' ' . $pi['serial_number']); }
        }
        // รวม direct items ใน search text ด้วย
        foreach ($sg['direct'] ?? [] as $pi) {
            $sgSearchText .= ' ' . strtolower($pi['brand'] . ' ' . $pi['model'] . ' ' . $pi['serial_number']);
        }
    ?>
        <div class="dept-card parent-group-card" data-dept="<?= htmlspecialchars($parentName) ?>" data-search="<?= htmlspecialchars($sgSearchText) ?>"
            style="margin-bottom: 32px; border-radius: 28px; border: 2px solid rgba(99,102,241,0.18); box-shadow: 0 4px 24px rgba(99,102,241,0.06); overflow:hidden; background:linear-gradient(135deg, rgba(99,102,241,0.015), rgba(139,92,246,0.015));">
            <!-- Parent Group Header -->
            <div style="background:linear-gradient(135deg, rgba(99,102,241,0.08), rgba(139,92,246,0.06)); padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-bottom:1px solid rgba(99,102,241,0.12);">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.3rem">🏢</span>
                    <h2 style="margin:0; font-size:1.05rem; font-weight:800; color:#4338ca;"><?= htmlspecialchars($parentName) ?></h2>
                </div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <div style="background:rgba(16,185,129,0.12); padding:4px 12px; border-radius:20px; color:var(--success); font-size:0.82rem; font-weight:600;">● ใช้งาน <?= $sgStats['normal'] ?>/<?= $sgStats['total'] ?></div>
                    <?php if ($sgStats['repairing'] > 0): ?>
                        <div style="background:rgba(245,158,11,0.1); padding:4px 12px; border-radius:20px; color:var(--warning); font-size:0.82rem; font-weight:600;">🔧 <?= $sgStats['repairing'] ?></div>
                    <?php endif; ?>
                    <?php if ($sgStats['broken'] > 0): ?>
                        <div style="background:rgba(239,68,68,0.1); padding:4px 12px; border-radius:20px; color:var(--danger); font-size:0.82rem; font-weight:600;">❌ <?= $sgStats['broken'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Sub-unit cards inside parent -->
            <div style="padding:16px;">
                <?php
                // แสดง printers ที่ register ตรงกับกลุ่มงานหลัก (ไม่ระบุหน่วยงานย่อย)
                $directItems = $sg['direct'] ?? [];
                if (!empty($directItems)):
                ?>
                <div class="dept-card sub-unit-card" data-dept="<?= htmlspecialchars($parentName) ?>" data-search="<?= htmlspecialchars(strtolower($parentName . ' ' . implode(' ', array_map(fn($p) => $p['brand'].' '.$p['model'].' '.$p['serial_number'], $directItems)))) ?>"
                    style="margin-bottom: 20px; padding: 0; overflow: hidden; border-radius: 20px; border: 1px solid rgba(99,102,241,0.2); box-shadow: 0 2px 12px rgba(99,102,241,0.06); background:white;">
                    <div class="dept-header">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:4px;height:20px;background:rgba(99,102,241,0.4);border-radius:2px"></div>
                            <h2 style="margin:0;font-size:0.95rem;font-weight:700;color:#6366f1;"><?= htmlspecialchars($parentName) ?></h2>
                        </div>
                    </div>
                    <div class="printer-grid">
                        <?php foreach ($directItems as $p):
                            $coverImg = !empty($imagesMap[$p['id']]) ? $imagesMap[$p['id']][0] : null;
                            $imgCount = count($imagesMap[$p['id']] ?? []);
                            $statusColors = ['normal'=>'#10b981','repairing'=>'#f59e0b','broken'=>'#ef4444'];
                            $statusLabels = ['normal'=>'ปกติ','repairing'=>'กำลังซ่อม','broken'=>'พัง'];
                            $statusColor = $statusColors[$p['status']] ?? '#94a3b8';
                            $statusLabel = $statusLabels[$p['status']] ?? $p['status'];
                        ?>
                            <div class="printer-card" style="border-radius:18px; border:1.5px solid var(--border); overflow:hidden; background:white; box-shadow:0 2px 12px rgba(0,0,0,0.05); transition:all 0.25s; cursor:pointer; position:relative;"
                                onclick="if(window.loadPage){ window.location.hash='printer_details?qr_id=<?= $p['qr_code_id'] ?>'; window.loadPage('printer_details?qr_id=<?= $p['qr_code_id'] ?>'); }"
                                onmouseenter="this.style.boxShadow='0 8px 32px rgba(0,0,0,0.12)'; this.style.transform='translateY(-3px)'"
                                onmouseleave="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.05)'; this.style.transform=''">
                                <div style="position:relative; width:100%; aspect-ratio:1/1; background:#f1f5f9; overflow:hidden;">
                                    <?php if ($coverImg): ?>
                                        <img src="<?= htmlspecialchars($coverImg['url']) ?>" style="width:100%;height:100%;object-fit:cover;display:block;" alt="printer photo">
                                    <?php else: ?>
                                        <div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px"><span style="font-size:3rem">🖨️</span><span style="font-size:0.72rem;color:var(--text-muted)">ยังไม่มีรูปภาพ</span></div>
                                    <?php endif; ?>
                                    <div style="position:absolute;top:10px;left:10px;background:<?= $statusColor ?>;color:white;font-size:0.7rem;font-weight:700;padding:3px 10px;border-radius:20px;"><?= $statusLabel ?></div>
                                    <?php if ($imgCount > 0): ?>
                                        <div style="position:absolute;bottom:10px;left:10px;background:rgba(0,0,0,0.5);color:white;font-size:0.72rem;padding:3px 8px;border-radius:12px;">📷 <?= $imgCount ?></div>
                                    <?php endif; ?>
                                    <button class="photo-mgr-btn" onclick="event.stopPropagation(); openPhotoManager(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['brand'].' '.$p['model'])) ?>')"
                                        style="position:absolute;bottom:10px;right:10px;background:rgba(255,255,255,0.92);border:none;border-radius:10px;padding:5px 10px;font-size:0.75rem;font-weight:600;cursor:pointer;backdrop-filter:blur(4px);color:#374151;">📷 จัดการรูป</button>
                                    <button class="quick-delete-btn" onclick="event.stopPropagation(); quickDeletePrinter(<?= $p['id'] ?>, '<?= $p['status'] ?>', '<?= md5($parentName) ?>', this)"
                                        style="position:absolute;top:8px;right:8px;width:30px;height:30px;background:rgba(0,0,0,0.45);border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.85rem;cursor:pointer;">🗑️</button>
                                </div>
                                <div style="padding:10px 12px 12px;">
                                    <div style="font-weight:700;font-size:0.85rem;color:var(--text-main);margin-bottom:3px;"><?= htmlspecialchars($p['brand'] . ' ' . $p['model']) ?></div>
                                    <div style="font-size:0.7rem;color:var(--text-muted);">SN: <?= htmlspecialchars($p['serial_number']) ?></div>
                                    <?php if(!empty($p['location'])): ?>
                                    <div style="font-size:0.7rem;color:var(--primary);margin-top:2px;">📍 <?= htmlspecialchars($p['location']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php foreach ($sg['subs'] as $subName => $subGroup): ?>
                    <?php renderSubUnitCard($subName, $subGroup, $imagesMap); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- === หน่วยงานอิสระ (ไม่อยู่ในกลุ่มงานหลัก) === -->
    <?php foreach ($standalone as $deptName => $group): ?>
        <?php renderSubUnitCard($deptName, $group, $imagesMap); ?>
    <?php endforeach; ?>

<?php endif; ?>



<!-- Modal สำหรับเพิ่มปริ้นเตอร์ -->
<div id="add-printer-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>เพิ่มปริ้นเตอร์ใหม่ 🖨️</h3>
            <button type="button" class="close-modal-btn">&times;</button>
        </div>
        <form id="add-printer-form" style="display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden;">
            <div class="modal-body" style="padding: 30px">
                <div style="margin-bottom: 20px">
                    <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">ยี่ห้อ (Brand)</label>
                    <select name="brand" id="brand-select" required style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit; background: white; cursor: pointer;" onchange="
                        if(this.value === 'อื่นๆ') {
                            this.removeAttribute('name');
                            document.getElementById('brand-input').setAttribute('name', 'brand');
                            document.getElementById('brand-input').style.display = 'block';
                            document.getElementById('brand-input').required = true;
                            document.getElementById('brand-input').value = '';
                            document.getElementById('brand-input').focus();
                        } else {
                            this.setAttribute('name', 'brand');
                            document.getElementById('brand-input').removeAttribute('name');
                            document.getElementById('brand-input').style.display = 'none';
                            document.getElementById('brand-input').required = false;
                        }
                    ">
                        <option value="" disabled selected>เลือกยี่ห้อปริ้นเตอร์</option>
                        <option value="Epson">Epson</option>
                        <option value="Canon">Canon</option>
                        <option value="Brother">Brother</option>
                        <option value="HP">HP</option>
                        <option value="อื่นๆ">อื่นๆ (โปรดระบุ)</option>
                    </select>
                    <input type="text" id="brand-input" placeholder="โปรดระบุยี่ห้อ..." style="display:none; width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit; margin-top: 10px;">
                </div>
                <div style="margin-bottom: 20px">
                    <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">รุ่น (Model)</label>
                    <input type="text" name="model" placeholder="เช่น LaserJet Pro M404n" required style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit">
                </div>
                <div style="margin-bottom: 20px">
                    <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">เลขเครื่อง (Serial Number)</label>
                    <input type="text" name="serial_number" placeholder="กรอกเลขรหัสประจำเครื่อง" required style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit">
                </div>
                <div style="margin-bottom: 20px; position: relative;">
                    <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">หน่วยงาน/กลุ่มงาน</label>
                    <div class="searchable-select">
                        <input type="text" id="dept-search" placeholder="พิมพ์เพื่อค้นหาหน่วยงาน..." autocomplete="off" style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit">
                        <input type="hidden" name="department" id="dept-value">
                        <div class="select-dropdown" id="dept-dropdown">
                            <?php 
                            $deptGroups = $hierMap;
                            foreach ($deptGroups as $groupName => $subUnits): ?>
                                <div class="select-item select-group-header" data-value="<?= htmlspecialchars($groupName) ?>" style="font-weight:700; color:var(--primary-dark); background:rgba(99,102,241,0.05); padding:10px 14px; font-size:0.88rem; border-bottom:1px solid var(--border); cursor:pointer;">
                                    🏢 <?= htmlspecialchars($groupName) ?>
                                </div>
                                <?php foreach ($subUnits as $sub): ?>
                                    <div class="select-item" data-value="<?= htmlspecialchars($sub) ?>" style="padding-left:32px; font-size:0.88rem;">
                                        └ <?= htmlspecialchars($sub) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <div class="select-item" data-value="ไม่ทราบ" style="margin-top:4px; border-top:1px solid var(--border); color:var(--text-muted);">❓ ไม่ทราบ</div>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 20px">
                    <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">สถานที่วางเครื่อง (Location)</label>
                    <input type="text" name="location" placeholder="เช่น ชั้น 2 ห้องการเงิน" style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit">
                </div>

                <!-- ปี พ.ศ. -->
                <div style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom: 10px; font-weight: 600; font-size: 0.95rem">ปี พ.ศ. <span style="color:var(--text-muted);font-weight:400;font-size:0.82rem">(ที่ได้รับ)</span></label>
                    <input type="text" name="year_be" id="year-be-input"
                        placeholder="<?php echo (date('Y') + 543); ?>"
                        maxlength="4"
                        style="width:100%; padding: 14px; border: 1px solid var(--border); border-radius: 14px; outline: none; font-family: inherit; font-size:1rem">
                </div>

                <button type="submit" class="btn-primary" id="save-printer-btn" style="width: 100%; justify-content: center; height: 56px; font-size: 1.1rem">
                    บันทึกข้อมูลและสร้าง QR Code
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal สำหรับพิมพ์ QR Code ทั้งหมด -->
<div id="print-qr-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>พิมพ์สติ๊กเกอร์ QR Code 🖨️</h3>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button class="btn-primary" onclick="printQRLabels('grid')" style="padding: 8px 16px; background: var(--primary)">📄 พิมพ์แบบตาราง (A4)</button>
                <button class="btn-primary" onclick="printQRLabels('thermal')" style="padding: 8px 16px; background: var(--success)">🖨️ พิมพ์ Thermal (สติ๊กเกอร์)</button>
                <button type="button" class="close-modal-btn">&#x00d7;</button>
            </div>
        </div>
        <div class="modal-body print-area" id="printable-labels" style="padding: 30px; background: white;">
            <div class="label-grid">
                <?php foreach ($printers as $p): ?>
                    <div class="label-item">
                        <div class="label-header"><?php echo htmlspecialchars($p['department']); ?></div>
                        <div class="label-qr" data-qr="<?php echo $p['qr_code_id']; ?>"></div>
                        <div class="label-footer"><?php echo htmlspecialchars($p['brand'] . ' ' . $p['model']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.label-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
}
.label-item {
    border: 1px dashed #ccc;
    padding: 15px;
    text-align: center;
    background: white;
    border-radius: 8px;
}
.label-header {
    font-size: 0.8rem;
    font-weight: 700;
    margin-bottom: 10px;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.label-qr {
    margin: 0 auto;
    width: 120px;
    height: 120px;
    display: flex;
    justify-content: center;
    align-items: center;
}
.label-footer {
    font-size: 0.75rem;
    margin-top: 10px;
    color: #666;
}

@media print {
    /* ไม่ใช้ window.print() โดยตรงแล้ว - ใช้ printQRLabels() แทน */
}
</style>

<script>
    // ฟังก์ชันเปิดหน้าต่างพิมพ์ใหม่แยกต่างหาก รองรับโหมดตารางและ Thermal
    function printQRLabels(mode = 'grid') {
        const labels = document.getElementById('printable-labels');
        if (!labels) return;

        // เก็บ QR data จาก DOM
        const items = labels.querySelectorAll('.label-item');
        let rows = '';
        items.forEach(item => {
            const header = item.querySelector('.label-header')?.textContent || '';
            const footer = item.querySelector('.label-footer')?.textContent || '';
            const qrEl = item.querySelector('.label-qr');
            const qrData = qrEl?.getAttribute('data-qr') || '';
            // ใช้ QR image ที่ generate แล้ว ถ้ามี
            const qrImg = qrEl?.querySelector('img');
            const qrSrc = qrImg ? qrImg.src : '';

            rows += `
                <div class="label-item">
                    <div class="label-header">${header}</div>
                    <div class="label-qr">
                        ${qrSrc 
                            ? `<img src="${qrSrc}" width="120" height="120">` 
                            : `<canvas id="qr-${qrData.replace(/[^a-z0-9]/gi,'')}"></canvas>`
                        }
                    </div>
                    <div class="label-footer">${footer}</div>
                </div>`;
        });

        const win = window.open('', '_blank', 'width=900,height=700');
        
        // CSS พิเศษสำหรับ Thermal: 1 ดวงต่อหน้า, ไม่มีขอบ, ปรับขนาดให้เต็มหน้า
        const thermalStyles = `
            body { padding: 0; }
            .label-grid { display: block; }
            .label-item { 
                border: none; 
                width: 100vw; 
                height: 100vh; 
                display: flex; 
                flex-direction: column; 
                justify-content: center; 
                align-items: center;
                page-break-after: always; 
                padding: 10px;
                box-sizing: border-box;
            }
            .label-header { font-size: 1.2rem; margin-bottom: 15px; }
            .label-qr img { width: 75vw !important; height: auto !important; max-width: 250px; }
            .label-footer { font-size: 1.1rem; margin-top: 15px; }
            @page { margin: 0; size: auto; }
        `;

        const gridStyles = `
            body { padding: 10mm; }
            .label-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            .label-item {
                border: 1px dashed #bbb;
                padding: 12px;
                text-align: center;
                border-radius: 6px;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .label-header { font-size: 0.75rem; font-weight: 700; color: #5b21b6; margin-bottom: 8px; }
            .label-qr img { display: block; margin: 0 auto; }
            .label-footer { font-size: 0.7rem; color: #444; }
            @page { margin: 10mm; }
        `;

        win.document.write(`
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>พิมพ์ QR Code ${mode === 'thermal' ? '(Thermal)' : ''}</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: sans-serif; }
        ${mode === 'thermal' ? thermalStyles : gridStyles}
        @media print {
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="label-grid">${rows}</div>
    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); window.close(); }, 800);
        };
    <\/script>
</body>
</html>`);
        win.document.close();
    }

    (function() {
        // ฟังก์ชันสร้าง QR Code สำหรับทุุกจุด
        const generateQRs = (selector) => {
            if (typeof QRCode === 'undefined') {
                setTimeout(() => generateQRs(selector), 300);
                return;
            }
            document.querySelectorAll(selector).forEach(container => {
                if (container.querySelector('img') || container.querySelector('canvas')) return;
                try {
                    const qrId = container.getAttribute('data-qr');
                    let fullUrl = window.location.origin + window.location.pathname + '#printer_details?qr_id=' + qrId;
                    if (!window.location.hostname.includes('localhost') && !window.location.hostname.includes('127.0.0.1')) {
                        fullUrl = fullUrl.replace(/^http:\/\//i, 'https://');
                    }
                    new QRCode(container, {
                        text: fullUrl,
                        width: container.classList.contains('label-qr') ? 120 : 70,
                        height: container.classList.contains('label-qr') ? 120 : 70,
                        colorDark : "#000000",
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.L // ใช้ L ลดความหนาแน่นจุด
                    });
                } catch (e) { console.error("QR Error:", e); }
            });
        };

        generateQRs('.qr-container');

        const modal = document.getElementById('add-printer-modal');
        const printModal = document.getElementById('print-qr-modal');
        const openBtns = document.querySelectorAll('#add-printer-btn, .open-add-printer');
        const printBtn = document.getElementById('print-all-qr-btn');
        const closeBtns = document.querySelectorAll('.close-modal-btn');
        const form = document.getElementById('add-printer-form');

        // Searchable Select Logic
        const deptSearch = document.getElementById('dept-search');
        if (deptSearch) {
            const deptValue = document.getElementById('dept-value');
            const deptDropdown = document.getElementById('dept-dropdown');
            const deptItems = deptDropdown.querySelectorAll('.select-item');
            const deptSubItems = deptDropdown.querySelectorAll('.select-item:not(.select-group-header)');

            // ตอนเปิด dropdown: แสดงเฉพาะ group headers, ซ่อน sub-units
            deptSearch.onfocus = () => {
                deptItems.forEach(item => {
                    if (item.classList.contains('select-group-header')) {
                        item.classList.remove('hidden');
                    } else {
                        item.classList.add('hidden');
                    }
                });
                deptDropdown.style.display = 'block';
            };

            deptSearch.oninput = (e) => {
                const val = e.target.value.toLowerCase().trim();
                deptValue.value = ''; // clear ค่าที่เลือกไว้เมื่อพิมพ์ใหม่

                if (!val) {
                    // ไม่มีคำค้น → แสดงเฉพาะ headers
                    deptItems.forEach(item => {
                        if (item.classList.contains('select-group-header')) {
                            item.classList.remove('hidden');
                        } else {
                            item.classList.add('hidden');
                        }
                    });
                    deptDropdown.style.display = 'block';
                    return;
                }

                // มีคำค้น → แสดงทุก item ที่ตรง
                let hasMatch = false;
                const matchedHeaders = new Set();
                deptItems.forEach(item => {
                    if (item.classList.contains('select-group-header') && item.textContent.toLowerCase().includes(val)) {
                        matchedHeaders.add(item);
                    }
                });
                let currentHeader = null;
                deptItems.forEach(item => {
                    if (item.classList.contains('select-group-header')) currentHeader = item;
                    const selfMatch = item.textContent.toLowerCase().includes(val);
                    const parentMatch = currentHeader && matchedHeaders.has(currentHeader) && !item.classList.contains('select-group-header');
                    if (selfMatch || parentMatch) {
                        item.classList.remove('hidden');
                        hasMatch = true;
                    } else {
                        item.classList.add('hidden');
                    }
                });
                deptDropdown.style.display = hasMatch ? 'block' : 'none';
            };

            deptItems.forEach(item => {
                item.addEventListener('mouseenter', () => { item.style.background = item.classList.contains('select-group-header') ? 'rgba(99,102,241,0.1)' : 'rgba(99,102,241,0.06)'; });
                item.addEventListener('mouseleave', () => { item.style.background = item.classList.contains('select-group-header') ? 'rgba(99,102,241,0.05)' : ''; });
                item.onclick = () => {
                    const val = item.getAttribute('data-value');
                    deptSearch.value = val;
                    deptValue.value = val;
                    deptDropdown.style.display = 'none';
                };
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.searchable-select')) {
                    if(deptDropdown) deptDropdown.style.display = 'none';
                }
            });
        }

        openBtns.forEach(btn => {
            btn.onclick = () => {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };
        });

        if (printBtn) {
            printBtn.onclick = () => {
                printModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                setTimeout(() => generateQRs('.label-qr'), 100);
            };
        }

        const closeAllModals = () => {
            modal.style.display = 'none';
            printModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        };

        closeBtns.forEach(btn => btn.onclick = closeAllModals);
        window.addEventListener('click', (e) => { 
            if(e.target == modal || e.target == printModal) closeAllModals(); 
        });

        form.onsubmit = async (e) => {
            e.preventDefault();
            const saveBtn = document.getElementById('save-printer-btn');
            const originalText = saveBtn.innerHTML;

            // ตรวจสอบว่าเลือกหน่วยงานจาก dropdown แล้ว
            const deptValEl = document.getElementById('dept-value');
            const deptSearchEl = document.getElementById('dept-search');
            if (deptValEl && !deptValEl.value.trim()) {
                if (deptSearchEl && deptSearchEl.value.trim()) {
                    // fallback: ใช้ค่าที่พิมพ์ในช่องค้นหาตรงๆ
                    deptValEl.value = deptSearchEl.value.trim();
                } else {
                    showToast('กรุณาเลือกหน่วยงาน/กลุ่มงาน', 'warning');
                    deptSearchEl && deptSearchEl.focus();
                    return;
                }
            }

            saveBtn.disabled = true;
            saveBtn.innerHTML = 'กำลังบันทึก...';

            const formData = new FormData(form);
            console.log('[AddPrinter] department =', formData.get('department'));

            try {
                const response = await fetch('api.php?action=add_printer', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if(result.success) {
                    showToast('บันทึกสำเร็จ!', 'success');
                    if (typeof window.loadPage === 'function') {
                        window.loadPage('printers');
                    } else {
                        location.reload();
                    }
                } else {
                    showToast('เกิดข้อผิดพลาด: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
            }
        };
    })();

    // === Toggle sub-unit details in summary cards ===
    function toggleSubDetail(id) {
        const el = document.getElementById(id);
        const chevron = document.getElementById(id + '-chevron');
        if (!el) return;
        if (el.style.maxHeight && el.style.maxHeight !== '0px') {
            el.style.maxHeight = '0px';
            el.style.borderTop = '0px solid transparent';
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        } else {
            el.style.maxHeight = el.scrollHeight + 'px';
            el.style.borderTop = '1px solid var(--border)';
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        }
    }

    // === Live Filter Logic ===
    (function() {
        const searchInput = document.getElementById('printer-search');
        const deptSelect = document.getElementById('dept-filter');
        const resultCount = document.getElementById('filter-result-count');
        // การ์ดระดับบนสุด (parent-group-card + standalone sub-unit-card ที่ไม่อยู่ใน parent)
        const topCards = document.querySelectorAll('.parent-group-card, .sub-unit-card:not(.parent-group-card .sub-unit-card)');
        // sub-unit-card ทั้งหมด (รวมที่อยู่ใน parent group)
        const allSubCards = document.querySelectorAll('.sub-unit-card');

        let scrollTimer = null;

        function applyFilter() {
            const keyword = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const dept = deptSelect ? deptSelect.value : '';
            let visible = 0;
            let firstMatchSubCard = null;

            // ล้าง highlight เก่าทั้งหมด
            allSubCards.forEach(sc => {
                sc.style.outline = '';
                sc.style.boxShadow = sc.style.boxShadow.replace(/,?\s*0 0 0 3px rgba\(99,102,241,0\.35\)/g, '').replace(/^,\s*/, '');
                sc.style.transition = 'box-shadow 0.3s, outline 0.3s';
            });

            topCards.forEach(card => {
                const cardDept = card.dataset.dept || '';
                const cardSearch = card.dataset.search || '';
                const matchDept = !dept || cardDept === dept || cardSearch.includes(dept.toLowerCase());
                const matchSearch = !keyword || cardSearch.includes(keyword);

                if (matchDept && matchSearch) {
                    card.style.display = '';
                    visible++;

                    // ถ้าเป็น parent-group-card — ตรวจ sub-unit ภายใน
                    if (card.classList.contains('parent-group-card') && keyword) {
                        const subCards = card.querySelectorAll('.sub-unit-card');
                        subCards.forEach(sc => {
                            const scSearch = sc.dataset.search || '';
                            const scDept = sc.dataset.dept || '';
                            const scMatchSearch = scSearch.includes(keyword);
                            const scMatchDept = !dept || scDept === dept || scSearch.includes(dept.toLowerCase());

                            if (scMatchSearch && scMatchDept) {
                                // ไฮไลต์ sub-unit ที่ตรง
                                sc.style.outline = '2.5px solid rgba(99,102,241,0.7)';
                                sc.style.boxShadow = '0 0 0 5px rgba(99,102,241,0.15), 0 4px 20px rgba(99,102,241,0.2)';
                                if (!firstMatchSubCard) firstMatchSubCard = sc;
                            } else {
                                // ซ่อน sub-unit ที่ไม่ตรง (เฉพาะตอนค้นหา)
                                sc.style.opacity = '0.25';
                                sc.style.filter = 'grayscale(60%)';
                                sc.style.transition = 'opacity 0.3s, filter 0.3s';
                            }
                        });
                        // ถ้ามี sub-unit ตรง ก็ reset opacity ของที่ตรง
                        card.querySelectorAll('.sub-unit-card').forEach(sc => {
                            const scSearch = sc.dataset.search || '';
                            if (scSearch.includes(keyword)) {
                                sc.style.opacity = '';
                                sc.style.filter = '';
                            }
                        });
                    }
                } else {
                    card.style.display = 'none';
                }
            });

            // ถ้าไม่มีคำค้น ล้าง opacity/filter ที่ fade ทั้งหมด
            if (!keyword && !dept) {
                allSubCards.forEach(sc => {
                    sc.style.opacity = '';
                    sc.style.filter = '';
                });
            }

            if (resultCount) {
                if (keyword || dept) {
                    resultCount.textContent = `แสดง ${visible} หน่วยงาน`;
                } else {
                    resultCount.textContent = '';
                }
            }

            // Auto-scroll ไปที่ sub-unit ที่ตรงกัน (debounce 350ms)
            if (scrollTimer) clearTimeout(scrollTimer);
            if (firstMatchSubCard && keyword) {
                scrollTimer = setTimeout(() => {
                    firstMatchSubCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 350);
            }
        }

        if (searchInput) searchInput.addEventListener('input', applyFilter);
        if (deptSelect) deptSelect.addEventListener('change', applyFilter);
    })();
</script>

<!-- ===== Photo Manager Modal ===== -->
<div id="photo-manager-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; backdrop-filter:blur(6px); padding:20px; box-sizing:border-box; overflow-y:auto; align-items:flex-start; justify-content:center">
    <div style="background:white; border-radius:24px; max-width:640px; width:100%; margin:40px auto; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3)">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border); background:#f8fafc">
            <div>
                <h3 style="margin:0; font-size:1.1rem">📷 จัดการรูปภาพ</h3>
                <div id="pm-printer-name" style="font-size:0.85rem; color:var(--text-muted); margin-top:2px"></div>
            </div>
            <button onclick="closePhotoManager()" style="background:none; border:none; font-size:1.6rem; cursor:pointer; color:var(--text-muted); line-height:1">✕</button>
        </div>

        <div style="padding:24px">
            <!-- Upload Zone -->
            <label for="pm-file-input" id="pm-upload-zone"
                style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; border:2px dashed var(--border); border-radius:16px; padding:28px; cursor:pointer; transition:all 0.2s; margin-bottom:20px; background:#fafafa"
                onmouseenter="this.style.borderColor='var(--primary)'; this.style.background='rgba(99,102,241,0.03)'"
                onmouseleave="this.style.borderColor='var(--border)'; this.style.background='#fafafa'">
                <span style="font-size:2.5rem">📤</span>
                <span style="font-size:0.9rem; font-weight:600; color:var(--primary)">เลือกหรือลากรูปมาวาง</span>
                <span style="font-size:0.78rem; color:var(--text-muted)">JPG, PNG, WEBP — ขนาดไม่เกิน 30MB</span>
            </label>
            <input type="file" id="pm-file-input" accept="image/*" multiple style="display:none">

            <div style="font-size:0.82rem; color:var(--text-muted); margin-bottom:10px">
                🖱 ลากเพื่อเรียงลำดับ — รูปแรก <span style="color:var(--primary); font-weight:600">(กรอบสีม่วง)</span> จะแสดงหน้าการ์ด
            </div>
            <div id="pm-image-grid" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; min-height:60px"></div>
            <div id="pm-uploading" style="display:none; text-align:center; padding:16px; color:var(--primary); font-weight:600">⏳ กำลังอัปโหลด...</div>
        </div>
    </div>
</div>

<style>
.pm-img-item {
    position: relative; border-radius: 12px; overflow: hidden;
    aspect-ratio: 1/1; cursor: grab;
    border: 2.5px solid transparent; transition: border-color 0.2s, transform 0.15s, box-shadow 0.15s;
    background: #f1f5f9;
    user-select: none;
}
.pm-img-item:first-child { border-color: var(--primary) !important; }
.pm-img-item.dragging { opacity: 0.35; transform: scale(0.95); cursor: grabbing; }
.pm-img-item.drag-over { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.3); }
.pm-img-item img { width:100%; height:100%; object-fit:cover; display:block; pointer-events:none; }
.pm-del-btn {
    position:absolute; top:6px; right:6px;
    background:rgba(239,68,68,0.88); color:white; border:none; border-radius:8px;
    width:28px; height:28px; font-size:1rem; cursor:pointer; display:flex;
    align-items:center; justify-content:center; font-weight:700; transition:background 0.2s;
}
.pm-del-btn:hover { background:#ef4444; }
.pm-badge-first {
    position:absolute; bottom:6px; left:6px;
    background:var(--primary); color:white; font-size:0.62rem; font-weight:700;
    padding:2px 8px; border-radius:10px; pointer-events:none;
}
</style>

<script>
// ===== Photo Manager (printers.php) — guard จาก SPA re-inject =====
(function() {
    // ใช้ window เพื่อไม่ให้ let ชนกันตอน SPA re-load
    window._pmPrinterId = window._pmPrinterId || null;
    window._pmDragSrc   = window._pmDragSrc   || null;

    window.openPhotoManager = function(printerId, name) {
        window._pmPrinterId = printerId;
        document.getElementById('pm-printer-name').textContent = name;
        document.getElementById('photo-manager-modal').style.display = 'flex';
        window._loadPMImages();
    };

    window.closePhotoManager = function() {
        document.getElementById('photo-manager-modal').style.display = 'none';
        if (window.loadPage) window.loadPage('printers'); else location.reload();
    };

    window._loadPMImages = async function() {
        const grid = document.getElementById('pm-image-grid');
        if (!grid) return;
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:20px">⏳ กำลังโหลด...</div>';
        try {
            const res = await fetch(`api.php?action=get_printer_images&printer_id=${window._pmPrinterId}`);
            const data = await res.json();
            window._renderPMGrid(data.images || []);
        } catch(e) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--danger);padding:20px">เกิดข้อผิดพลาดในการโหลดรูป</div>';
        }
    };

    window._renderPMGrid = function(images) {
        const grid = document.getElementById('pm-image-grid');
        if (!grid) return;
        grid.innerHTML = '';
        if (!images.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:30px">ยังไม่มีรูปภาพ — กดเลือกรูปด้านบน</div>';
            return;
        }
        images.forEach((img, idx) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'pm-wrapper';
            wrapper.style.position = 'relative';
            wrapper.style.aspectRatio = '1/1';
            wrapper.dataset.id = img.id;

            const item = document.createElement('div');
            item.className = 'pm-img-item';
            item.draggable = true;
            item.style.width = '100%';
            item.style.height = '100%';
            item.innerHTML = `
                <img src="${img.url}?t=${Date.now()}" alt="printer">
                ${idx === 0 ? '<div class="pm-badge-first">หน้าปก</div>' : ''}
            `;
            
            const btn = document.createElement('button');
            btn.type = 'button'; 
            btn.className = 'pm-del-btn';
            btn.title = 'ลบรูป';
            btn.innerHTML = '✕';
            btn.onclick = () => window._deletePMImage(img.id);

            wrapper.appendChild(item);
            wrapper.appendChild(btn);

            item.addEventListener('dragstart', function(e) { window._pmDragSrc = wrapper; item.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', img.id); });
            item.addEventListener('dragenter', function(e) { e.preventDefault(); });
            item.addEventListener('dragover',  function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; document.querySelectorAll('.pm-img-item').forEach(el => el.classList.remove('drag-over')); item.classList.add('drag-over'); });
            item.addEventListener('dragend',   function(e) { document.querySelectorAll('.pm-img-item').forEach(el => { el.classList.remove('dragging'); el.classList.remove('drag-over'); }); });
            item.addEventListener('drop', function(e) {
                e.preventDefault(); e.stopPropagation();
                if (!window._pmDragSrc || window._pmDragSrc === wrapper) return;
                const wrappersList = [...grid.querySelectorAll('.pm-wrapper')];
                const si = wrappersList.indexOf(window._pmDragSrc), di = wrappersList.indexOf(wrapper);
                if (si < di) grid.insertBefore(window._pmDragSrc, wrapper.nextSibling);
                else grid.insertBefore(window._pmDragSrc, wrapper);
                window._pmSaveOrder();
            });
            grid.appendChild(wrapper);
        });
    };

    window._pmSaveOrder = async function() {
        const wrappers = document.querySelectorAll('#pm-image-grid .pm-wrapper');
        const order = [...wrappers].map((el, i) => ({ id: +el.dataset.id, sort_order: i }));
        wrappers.forEach((el, i) => {
            const old = el.querySelector('.pm-badge-first'); if (old) old.remove();
            if (i === 0) { const b = document.createElement('div'); b.className = 'pm-badge-first'; b.textContent = 'หน้าปก'; el.querySelector('.pm-img-item').appendChild(b); }
        });
        const fd = new FormData(); fd.append('order', JSON.stringify(order));
        await fetch('api.php?action=reorder_printer_images', { method: 'POST', body: fd });
    };

    window._deletePMImage = async function(imgId) {
        if (typeof window.showConfirm !== 'function') {
            if (!document.getElementById('confirm-overlay')) {
                const div = document.createElement('div');
                div.innerHTML = `<div id="confirm-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); backdrop-filter:blur(8px); z-index:999998; align-items:center; justify-content:center; padding:20px;"><div id="confirm-box" style="background:white; border-radius:24px; padding:32px 28px; max-width:380px; width:100%; box-shadow:0 24px 60px rgba(0,0,0,0.22); text-align:center;"><div id="confirm-icon" style="font-size:2.8rem; margin-bottom:12px; line-height:1"></div><h3 id="confirm-title" style="margin:0 0 8px; font-size:1.1rem; color:#0f172a"></h3><p id="confirm-msg" style="margin:0 0 28px; font-size:0.9rem; color:#64748b; line-height:1.55"></p><div style="display:flex; gap:12px; justify-content:center"><button id="confirm-cancel" style="flex:1; padding:13px; border:1.5px solid #e2e8f0; border-radius:14px; background:white; font-size:0.9rem; font-weight:600; cursor:pointer; color:#64748b;">ยกเลิก</button><button id="confirm-ok" style="flex:1; padding:13px; border:none; border-radius:14px; font-size:0.9rem; font-weight:700; cursor:pointer; color:white;"></button></div></div></div>`;
                document.body.appendChild(div.firstElementChild);
            }
            window.showConfirm = function({ title='ยืนยัน?', message='', confirmText='ยืนยัน', cancelText='ยกเลิก', type='warning' } = {}) {
                return new Promise((resolve) => {
                    const overlay = document.getElementById('confirm-overlay');
                    if (!overlay) { resolve(confirm(title + '\\n' + message)); return; }
                    const icons   = { warning:'⚠️', danger:'🗑️', info:'💡', success:'✅' };
                    const colors  = { warning:'#f59e0b', danger:'#ef4444', info:'#6366f1', success:'#10b981' };
                    document.getElementById('confirm-icon').textContent  = icons[type]  || icons.warning;
                    document.getElementById('confirm-title').textContent = title;
                    document.getElementById('confirm-msg').textContent   = message;
                    const okBtn = document.getElementById('confirm-ok');
                    okBtn.textContent = confirmText;
                    okBtn.style.background = `linear-gradient(135deg, ${colors[type]||colors.warning}, ${colors[type]||colors.warning}cc)`;
                    overlay.style.display = 'flex';
                    const cleanup = (val) => { overlay.style.display = 'none'; okBtn.onclick = null; document.getElementById('confirm-cancel').onclick = null; resolve(val); };
                    okBtn.onclick = () => cleanup(true);
                    document.getElementById('confirm-cancel').onclick = () => cleanup(false);
                    overlay.onclick = (e) => { if (e.target === overlay) cleanup(false); };
                });
            };
        }

        const confirmed = await window.showConfirm({
            title: 'ยืนยันการลบรูปภาพ',
            message: 'ลบรูปนี้ออกถาวรหรือไม่?',
            confirmText: 'ลบถาวร',
            cancelText: 'ยกเลิก',
            type: 'danger'
        });
        if (!confirmed) return;
        
        const fd = new FormData(); fd.append('img_id', imgId);
        const res = await fetch('api.php?action=delete_printer_image', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) window._loadPMImages(); else showToast('ผิดพลาด: ' + data.message, 'error');
    };

    // Client-side Image Compression (Mobile Safe Version)
    window._pmCompressImage = function(file) {
        return new Promise((resolve) => {
            if (!file.type.match(/image.*/)) return resolve(file);
            
            const img = new Image();
            const objectUrl = URL.createObjectURL(file);
            
            img.onload = () => {
                URL.revokeObjectURL(objectUrl); // คืนหน่วยความจำให้เบราว์เซอร์ทันที
                
                let width = img.width, height = img.height;
                const maxDim = 1200; // ย่อรูปเหลือสูงสุด 1200px
                if (width > maxDim || height > maxDim) {
                    if (width > height) { height = Math.round(height * (maxDim / width)); width = maxDim; }
                    else { width = Math.round(width * (maxDim / height)); height = maxDim; }
                }
                const canvas = document.createElement('canvas');
                canvas.width = width; canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    if (blob) {
                        resolve(new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", { type: 'image/jpeg', lastModified: Date.now() }));
                    } else resolve(file);
                }, 'image/jpeg', 0.85); // คุณภาพ 85%
            };
            img.onerror = () => {
                URL.revokeObjectURL(objectUrl);
                resolve(file);
            };
            img.src = objectUrl;
        });
    };

    // Upload helper (shared)
    window._pmUploadFiles = async function(files, printerId) {
        const uploading = document.getElementById('pm-uploading');
        const errors = [];
        for (let file of files) {
            uploading.textContent = `⏳ กำลังย่อขนาดรูป: ${file.name}...`;
            uploading.style.display = 'block';
            
            try { file = await window._pmCompressImage(file); } catch(e) { console.error('Compress error:', e); }

            uploading.textContent = `⏳ กำลังอัปโหลด: ${file.name} (${(file.size/1024/1024).toFixed(1)} MB)...`;
            try {
                const fd = new FormData();
                fd.append('image', file);
                fd.append('printer_id', printerId);
                const res = await fetch('api.php?action=upload_printer_image', { method: 'POST', body: fd });
                const rawText = await res.text(); // อ่าน text ก่อนเสมอ
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch(parseErr) {
                    // Server ส่ง HTML/text กลับมาแทน JSON
                    errors.push(`${file.name}: Server error — ${rawText.substring(0, 200)}`);
                    continue;
                }
                if (!data.success) errors.push(`${file.name}: ${data.message}`);
            } catch(netErr) {
                errors.push(`${file.name}: เชื่อมต่อไม่ได้ (${netErr.message})`);
            }
        }
        uploading.style.display = 'none';
        uploading.textContent = '⏳ กำลังอัปโหลด...';
        if (errors.length) {
            showToast('อัปโหลดล้มเหลวบางส่วน โปรดลองใหม่', 'error', 'พบข้อผิดพลาด');
            console.error('Upload errors:', errors);
        } else if (files.length > 0) {
            showToast('อัปโหลดรูปภาพสำเร็จ', 'success');
        }
        window._loadPMImages();
    };

    // Bind events — guard ด้วย flag เพื่อไม่ให้ bind ซ้ำ
    if (!window._pmBound) {
        window._pmBound = true;

        document.addEventListener('change', async function(e) {
            if (e.target && e.target.id === 'pm-file-input') {
                const files = [...e.target.files];
                e.target.value = '';
                if (files.length) await window._pmUploadFiles(files, window._pmPrinterId);
            }
        });

        document.addEventListener('click', function(e) {
            const modal = document.getElementById('photo-manager-modal');
            if (modal && e.target === modal) window.closePhotoManager();
        });

        // Drop files on upload zone (delegated)
        document.addEventListener('dragover', function(e) {
            if (e.target && e.target.id === 'pm-upload-zone') { e.preventDefault(); e.target.style.borderColor = 'var(--primary)'; }
        });
        document.addEventListener('dragleave', function(e) {
            if (e.target && e.target.id === 'pm-upload-zone') { e.target.style.borderColor = 'var(--border)'; }
        });
        document.addEventListener('drop', async function(e) {
            if (e.target && e.target.id === 'pm-upload-zone') {
                e.preventDefault();
                e.target.style.borderColor = 'var(--border)';
                const files = [...e.dataTransfer.files].filter(f => f.type.startsWith('image/'));
                if (files.length) await window._pmUploadFiles(files, window._pmPrinterId);
            }
        });
    }
})();

    // Quick Delete Printer directly from list
    window.quickDeletePrinter = async function(id, status, deptHash, btn) {
        const confirmed = await window.showConfirm({
            title: 'ย้ายไปถังขยะ?',
            message: 'ข้อมูลปริ้นเตอร์จะถูกย้ายไปถังขยะและลบถาวรใน 30 วัน',
            confirmText: '🗑️ ลบเลย',
            cancelText: 'ยกเลิก',
            type: 'danger'
        });
        
        if (confirmed) {
            try {
                const formData = new FormData();
                formData.append('printer_id', id);
                
                const card = btn.closest('.printer-card');
                const deptCard = btn.closest('.dept-card');
                const summaryCard = document.querySelector(`.summary-card-${deptHash}`);

                if (card) {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                }

                const response = await fetch('api.php?action=delete_printer', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    showToast('ย้ายปริ้นเตอร์ไปถังขยะแล้ว', 'success');
                    if (card) {
                        setTimeout(() => {
                            card.remove();
                            // Update realtime stats!
                            if (deptCard) {
                                // List stats
                                const totalEl = deptCard.querySelector('.ds-total');
                                if(totalEl) totalEl.textContent = Math.max(0, parseInt(totalEl.textContent) - 1);
                                
                                if(status === 'normal') {
                                    const normEl = deptCard.querySelector('.ds-normal');
                                    if(normEl) normEl.textContent = Math.max(0, parseInt(normEl.textContent) - 1);
                                } else if(status === 'repairing') {
                                    const repEl = deptCard.querySelector('.ds-repair');
                                    if(repEl) {
                                        const newVal = Math.max(0, parseInt(repEl.textContent) - 1);
                                        repEl.textContent = newVal;
                                        if(newVal === 0) deptCard.querySelector('.ds-repair-badge').style.display = 'none';
                                    }
                                } else if(status === 'broken') {
                                    const broEl = deptCard.querySelector('.ds-broken');
                                    if(broEl) {
                                        const newVal = Math.max(0, parseInt(broEl.textContent) - 1);
                                        broEl.textContent = newVal;
                                        if(newVal === 0) deptCard.querySelector('.ds-broken-badge').style.display = 'none';
                                    }
                                }
                                // Hide dept if empty
                                if(deptCard.querySelectorAll('.printer-card').length === 0) {
                                    deptCard.style.display = 'none';
                                }
                            }
                            if (summaryCard) {
                                // Top summary stats
                                const totalEl = summaryCard.querySelector('.sum-total');
                                if(totalEl) totalEl.textContent = Math.max(0, parseInt(totalEl.textContent) - 1);
                                
                                if(status === 'normal') {
                                    const normEl = summaryCard.querySelector('.sum-normal');
                                    if(normEl) normEl.textContent = Math.max(0, parseInt(normEl.textContent) - 1);
                                } else {
                                    const issueEl = summaryCard.querySelector('.sum-issues span');
                                    if(issueEl) {
                                        const newVal = Math.max(0, parseInt(issueEl.textContent) - 1);
                                        if(newVal === 0) {
                                            summaryCard.querySelector('.sum-issues').innerHTML = '✅ พร้อมใช้งาน 100%';
                                            summaryCard.querySelector('.sum-issues').style.color = 'var(--text-muted)';
                                        } else {
                                            issueEl.textContent = newVal;
                                        }
                                    }
                                }
                            }
                        }, 300);
                    }
                } else {
                    showToast(result.message, 'error');
                    if (card) {
                        card.style.opacity = '1';
                        card.style.transform = 'none';
                    }
                }
            } catch (error) {
                console.error(error);
                showToast('การเชื่อมต่อล้มเหลว', 'error');
            }
        }
    };
</script>
