document.addEventListener('DOMContentLoaded', () => {
    // State Management
    const state = {
        currentPage: 'dashboard',
        currentPageFull: 'dashboard', // เก็บ full path รวม params เพื่อเปรียบเทียบใน hashchange
        isScannerOpen: false,
        scanner: null,
        pendingModalTimer: null   // ใช้ cancel setTimeout ที่ค้างอยู่
    };

    // DOM Elements
    const pageContainer = document.getElementById('page-container');
    const pageLoader = document.getElementById('page-loader');
    const navLinks = document.querySelectorAll('[data-page]');
    const scannerModal = document.getElementById('scanner-modal');
    const closeScannerBtn = document.querySelector('.close-modal');
    const scanTriggers = document.querySelectorAll('.scan-trigger');

    // --- Navigation System ---
    const cleanupBeforeLoad = () => {
        // ยกเลิก pending modal callback ที่ค้างจากการแสกน
        if (state.pendingModalTimer) {
            clearTimeout(state.pendingModalTimer);
            state.pendingModalTimer = null;
        }
        // ลบ openStockInModal / openAddInkModal ที่ผูกไว้กับหน้าเก่า
        window.openStockInModal = null;
        window.openAddInkModal  = null;

        // ===== Cleanup globals ที่ printer_details.php ฝังไว้ =====
        // ล้างเพื่อป้องกัน stale closure ชี้ไปที่ printer เครื่องเก่า
        window.deletePrinter            = null;
        window.onBarcodeScanned         = null;
        window.printLabel               = null;
        window.printLabelFromImage      = null;
        window.openDetailPhotoManager   = null;
        window.closeDetailPhotoManager  = null;
        window.openDetailLightbox       = null;
        window._dpmLoad                 = null;
        window._dpmRender               = null;
        window._dpmId                   = null;
        window._dpmSrc                  = null;

        // ปิด modal ที่อาจยังเปิดค้างไว้จากหน้าเก่า (ป้องกัน body-scroll lock)
        document.body.style.overflow = '';
        // ปิด flatpickr instances ที่อาจหลุดค้าง
        if (window._fpDateInstance) {
            try { window._fpDateInstance.destroy(); } catch(e) {}
            window._fpDateInstance = null;
        }
        if (window._fpTimeInstance) {
            try { window._fpTimeInstance.destroy(); } catch(e) {}
            window._fpTimeInstance = null;
        }
    };

    const loadPage = async (pageWithParams) => {
        const [page, params] = pageWithParams.split('?');

        // อัปเดต state ทันทีเพื่อป้องกัน double-load จาก hashchange ที่อาจยิงเข้ามาพร้อมกัน
        state.currentPage = page;
        state.currentPageFull = pageWithParams; // เก็บ full path รวม params

        // หยุด pending action จากหน้าก่อน
        cleanupBeforeLoad();

        pageLoader.style.display = 'flex';
        pageContainer.style.opacity = '0';
        
        try {
            const cacheBuster = `t=${Date.now()}`;
            const url = `pages/${page}.php${params ? '?' + params + '&' + cacheBuster : '?' + cacheBuster}`;
            const response = await fetch(url);
            const html = await response.text();
            
            // Inject HTML
            pageContainer.innerHTML = html;
            
            // Execute Scripts in the injected HTML
            const scripts = pageContainer.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
            
            // Update Active State
            navLinks.forEach(link => {
                if (link.getAttribute('data-page') === page) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });

            // Run page-specific special initializers
            if (page === 'analysis') {
                if (typeof initAnalysisCharts === 'function') initAnalysisCharts();
            }
        } catch (error) {
            console.error('Page load error:', error);
            pageContainer.innerHTML = '<div class="card"><h3>เกิดข้อผิดพลาด</h3><p>ไม่สามารถโหลดหน้านี้ได้</p></div>';
        } finally {
            pageLoader.style.display = 'none';
            pageContainer.style.opacity = '1';
        }
    };

    // --- Scanner System ---
    let html5QrCode = null;
    let isScannerStarting = false;

    const initScanner = () => {
        if (isScannerStarting) return;
        isScannerStarting = true;

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        } else if (typeof html5QrCode.getState === 'function' && html5QrCode.getState() === 2) { // 2 = SCANNING
            isScannerStarting = false;
            return;
        }
        
        // ปรับขนาดกรอบให้ "กว้างพอ" สำหรับ Barcode (1D) และ "สูงพอ" สำหรับ QR Code
        // โดยใช้ function คำนวณตามขนาดจอ เพื่อไม่ให้ขอบโดนตัด
        const config = { 
            fps: 30, // ปรับขึ้นเป็น 30 เพื่อให้จับภาพรัวขึ้น เพิ่มโอกาสเจอเฟรมที่บาร์โค้ดชัดที่สุด
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                let width = Math.min(viewfinderWidth * 0.9, 350);
                let height = 220;
                return { width: width, height: Math.min(viewfinderHeight * 0.7, height) };
            },
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            },
            formatsToSupport: [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39
            ]
        };
        document.getElementById('reader').innerHTML = ''; // Clear previous errors

        html5QrCode.start(
            { facingMode: "environment" }, 
            {
                ...config,
                videoConstraints: {
                    facingMode: "environment",
                    width: { ideal: 1280 }, // ลดความละเอียดลงมาที่ 720p (เพียงพอแล้ว) เพื่อให้ AI ประมวลผลภาพได้ไวขึ้น 2 เท่า
                    height: { ideal: 720 },
                    advanced: [{ focusMode: "continuous" }]
                }
            }, 
            onScanSuccess,
            onScanFailure
        ).then(() => {
            isScannerStarting = false;
        }).catch(err => {
            console.error(err);
            isScannerStarting = false;
            document.getElementById('reader').innerHTML = `<div style="padding: 30px; text-align: center; color: var(--danger);">ไม่สามารถเชื่อมต่อกล้องได้ กรุณาตรวจสอบสิทธิ์การใช้งาน<br><small style="color: var(--text-muted);">${err}</small></div>`;
        });
    };

    const onScanSuccess = (decodedText, decodedResult) => {
        // --- 1. Audio Feedback (Beep) ---
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gainNode = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(800, ctx.currentTime); // ความถี่เสียง
            gainNode.gain.setValueAtTime(0.1, ctx.currentTime); // ระดับความดัง
            osc.connect(gainNode);
            gainNode.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.1); // ดัง 0.1 วินาที
        } catch(e) {} // ignore if audio not supported

        // --- 2. Visual Feedback (Green Flash) ---
        const reader = document.getElementById('reader');
        if (reader) {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(34, 197, 94, 0.4);z-index:999;transition:all 0.3s;';
            reader.style.position = 'relative';
            reader.appendChild(overlay);
        }

        // --- 3. Pause & Delay Navigation ---
        if (html5QrCode && html5QrCode.getState() === 2) { 
            try { html5QrCode.pause(); } catch(e) {} 
        }

        setTimeout(() => {
            closeScanner();
            
            const prnMatch = decodedText.match(/(PRN-[A-Z0-9]+)/i);
            if (prnMatch) {
                const qrId = prnMatch[1].toUpperCase();
                window.location.hash = `printer_details?qr_id=${qrId}`;
                loadPage(`printer_details?qr_id=${qrId}`);
            } else {
                if (typeof window.onBarcodeScanned === 'function') {
                    window.onBarcodeScanned(decodedText);
                } else {
                    handleScanData(decodedText);
                }
            }
        }, 400); // รอให้เห็นสีเขียว 0.4 วินาทีแล้วค่อยเปลี่ยนหน้า
    };

    const onScanFailure = (error) => {
        // console.warn(`Scan error: ${error}`);
    };

    const openScanner = () => {
        scannerModal.style.display = 'block';
        initScanner();
    };

    const closeScanner = () => {
        scannerModal.style.display = 'none'; // ปิด UI ทันที
        if (html5QrCode) {
            try {
                const state = typeof html5QrCode.getState === 'function' ? html5QrCode.getState() : 0;
                // 2 = SCANNING, 3 = PAUSED. ต้องสั่ง stop เสมอเพื่อปิดฮาร์ดแวร์กล้อง
                if (state === 2 || state === 3) { 
                    // ถ้า pause อยู่ ต้อง resume ก่อน stop (บั๊กของไลบรารีบางเวอร์ชัน)
                    if (state === 3) { try { html5QrCode.resume(); } catch(e){} }
                    html5QrCode.stop().then(() => {
                        html5QrCode.clear();
                    }).catch(e => {
                        console.warn(e);
                        html5QrCode.clear();
                    });
                } else {
                    html5QrCode.clear();
                }
            } catch (e) {
                console.warn(e);
            }
        }
    };

    const handleScanData = async (barcode) => {
        try {
            // เช็คข้อมูลบาร์โค้ดจาก API
            const response = await fetch(`api.php?action=get_ink_details&barcode=${barcode}`);
            const result = await response.json();

            if (result.success) {
                if (result.exists) {
                    // ถ้ามีในระบบแล้ว -> เปิดหน้ารับสต๊อกเข้า
                    if (typeof window.openStockInModal === 'function') {
                        window.openStockInModal(result.data);
                    } else {
                        // ถ้าอยู่หน้าอื่น ให้เปลี่ยนไปหน้าคลังก่อน
                        window.location.hash = 'ink';
                        loadPage('ink').then(() => {
                            // ใช้ state.pendingModalTimer เพื่อให้ cancel ได้
                            state.pendingModalTimer = setTimeout(() => {
                                if (typeof window.openStockInModal === 'function') {
                                    window.openStockInModal(result.data);
                                }
                                state.pendingModalTimer = null;
                            }, 500);
                        });
                    }
                } else {
                    // ถ้ายังไม่มี -> เปิดหน้าลงทะเบียนหมึกใหม่
                    if (typeof window.openAddInkModal === 'function') {
                        window.openAddInkModal(barcode);
                    } else {
                        window.location.hash = 'ink';
                        loadPage('ink').then(() => {
                            state.pendingModalTimer = setTimeout(() => {
                                if (typeof window.openAddInkModal === 'function') {
                                    window.openAddInkModal(barcode);
                                }
                                state.pendingModalTimer = null;
                            }, 500);
                        });
                    }
                }
            }
        } catch (error) {
            console.error('Error handling scan data:', error);
            if (typeof showToast === 'function') {
                showToast('เกิดข้อผิดพลาดในการตรวจสอบข้อมูล', 'error');
            } else {
                alert('เกิดข้อผิดพลาดในการตรวจสอบข้อมูล');
            }
        }
    };

    // --- Event Listeners ---
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = link.getAttribute('data-page');
            // อัปเดต hash ก่อน แล้วค่อย load (ป้องกัน hashchange fire ซ้ำ)
            if (window.location.hash !== '#' + page) {
                history.replaceState(null, '', '#' + page);
            }
            loadPage(page);
        });
    });

    document.addEventListener('click', (e) => {
        if (e.target.closest('.scan-trigger')) {
            openScanner();
        }
    });

    closeScannerBtn.addEventListener('click', closeScanner);

    // Handle browser back/forward buttons
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.replace('#', '') || 'dashboard';
        // เปรียบเทียบ full path (รวม params) เพื่อให้ตรวจพบการ navigate ระหว่าง
        // printer_details?qr_id=A → printer_details?qr_id=B ได้ถูกต้อง
        if (state.currentPageFull !== hash) {
            loadPage(hash);
        }
    });

    // Initial Load
    const initialHash = window.location.hash.replace('#', '') || 'dashboard';
    loadPage(initialHash);

    // Close modal on outside click
    window.addEventListener('click', (event) => {
        if (event.target == scannerModal) closeScanner();
    });

    // Expose for external calls
    window.loadPage = loadPage;
});

// Helper for Charts (to be populated in analysis page)
function initAnalysisCharts() {
    // Logic for Chart.js will go here
}
