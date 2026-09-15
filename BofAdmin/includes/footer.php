    </div> <!-- End Main Content -->
    
    <!-- Global Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 z-[99999] bg-white/80 backdrop-blur-sm hidden flex-col items-center justify-center">
        <div class="w-16 h-16 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-4 text-lg font-semibold text-gray-700">กำลังบันทึกข้อมูล กรุณารอสักครู่...</p>
    </div>

    <!-- Footer scripts etc -->
    <script>
        // Global File Size Validation
        const CONF_UPLOAD_MAX_MB = <?php echo defined('CONF_UPLOAD_MAX_MB') ? CONF_UPLOAD_MAX_MB : 2; ?>;
        const CONF_UPLOAD_MAX_BYTES = CONF_UPLOAD_MAX_MB * 1024 * 1024;

        document.addEventListener('change', function(e) {
            if (e.target && e.target.type === 'file') {
                let files = e.target.files;
                let hasOversized = false;
                if (files && files.length > 0) {
                    for (let i = 0; i < files.length; i++) {
                        if (files[i].size > CONF_UPLOAD_MAX_BYTES) {
                            hasOversized = true;
                            break;
                        }
                    }
                    if (hasOversized) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'ขนาดไฟล์เกินกำหนด',
                                text: 'กรุณาอัปโหลดไฟล์ขนาดไม่เกิน ' + CONF_UPLOAD_MAX_MB + 'MB'
                            });
                        } else {
                            alert('ไฟล์มีขนาดใหญ่เกินไป กรุณาอัปโหลดไฟล์ขนาดไม่เกิน ' + CONF_UPLOAD_MAX_MB + 'MB');
                        }
                        e.target.value = ''; // Reset the input
                    }
                }
            }
        });
    </script>
</body>
</html>
