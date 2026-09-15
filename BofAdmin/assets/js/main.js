document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if(toggleBtn && sidebar && overlay) {
        // Toggle Sidebar
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });

        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }

    // Keep the AJAX filter clear button in sync without re-rendering the form.
    const filterForm = document.getElementById('filterForm');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    if (filterForm && clearFiltersBtn) {
        const ignoredFields = new Set(['ajax', 'page', 'type']);
        const updateClearFiltersButton = function() {
            const formData = new FormData(filterForm);
            const hasFilter = Array.from(formData.entries()).some(([name, value]) => {
                return !ignoredFields.has(name) && String(value).trim() !== '';
            });

            clearFiltersBtn.classList.toggle('hidden', !hasFilter);
        };

        filterForm.addEventListener('submit', updateClearFiltersButton);
        updateClearFiltersButton();
    }
});

// Centralized SweetAlert2 UI for BofAdmin index actions.
// Existing pages can migrate one at a time by replacing Swal.fire(...) with
// AdminDialog.fire(...). Non-confirmation alerts are forwarded unchanged.
window.AdminDialog = (function () {
    const actionLabels = {
        active: {
            title: 'ยืนยันการเปิดใช้งาน?',
            confirmText: 'ยืนยัน',
            confirmClass: 'admin-dialog-confirm admin-dialog-confirm--primary'
        },
        inactive: {
            title: 'ยืนยันการปิดใช้งาน?',
            confirmText: 'ยืนยัน',
            confirmClass: 'admin-dialog-confirm admin-dialog-confirm--primary'
        },
        delete: {
            title: 'ยืนยันการลบ?',
            confirmText: 'ยืนยันการลบ',
            confirmClass: 'admin-dialog-confirm admin-dialog-confirm--danger'
        },
        default: {
            title: 'ยืนยันการดำเนินการ?',
            confirmText: 'ยืนยัน',
            confirmClass: 'admin-dialog-confirm admin-dialog-confirm--primary'
        }
    };

    function inferAction(options) {
        if (options.adminAction) {
            const action = String(options.adminAction).toLowerCase();
            if (action.includes('delete')) return 'delete';
            if (action.includes('inactive') || action.includes('disable')) return 'inactive';
            if (action.includes('active') || action.includes('enable')) return 'active';
        }

        const confirmColor = String(options.confirmButtonColor || '').toLowerCase();
        if (['#d33', '#dc2626', '#ef4444'].includes(confirmColor)) return 'delete';

        const searchableText = [
            options.title,
            options.text,
            options.html,
            options.confirmButtonText
        ].filter(Boolean).join(' ').toLowerCase();

        if (/ลบ|delete/.test(searchableText)) return 'delete';
        if (/ปิด(?:การ)?ใช้งาน|ระงับการใช้งาน|inactive|disable/.test(searchableText)) return 'inactive';
        if (/เปิด(?:การ)?ใช้งาน|active|enable/.test(searchableText)) return 'active';

        return 'default';
    }

    function mergeClassNames(existingClasses, standardClasses) {
        return [existingClasses, standardClasses].filter(Boolean).join(' ');
    }

    function normalizeConfirmation(options) {
        const action = inferAction(options);
        const labels = actionLabels[action];
        const customClass = options.customClass && typeof options.customClass === 'object'
            ? options.customClass
            : {};

        const normalized = Object.assign({}, options, {
            title: labels.title,
            icon: action === 'delete' ? 'warning' : 'question',
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: labels.confirmText,
            cancelButtonText: 'ยกเลิก',
            customClass: Object.assign({}, customClass, {
                popup: mergeClassNames(customClass.popup, 'admin-dialog-popup'),
                title: mergeClassNames(customClass.title, 'admin-dialog-title'),
                htmlContainer: mergeClassNames(customClass.htmlContainer, 'admin-dialog-message'),
                actions: mergeClassNames(customClass.actions, 'admin-dialog-actions'),
                confirmButton: mergeClassNames(customClass.confirmButton, labels.confirmClass),
                cancelButton: mergeClassNames(customClass.cancelButton, 'admin-dialog-cancel')
            })
        });

        // Button colors are owned by style.css after buttonsStyling is disabled.
        delete normalized.confirmButtonColor;
        delete normalized.cancelButtonColor;
        delete normalized.adminAction;

        return normalized;
    }

    function fire(...args) {
        if (args.length === 1 && args[0] && typeof args[0] === 'object') {
            const options = args[0];
            if (options.showCancelButton === true) {
                return Swal.fire(normalizeConfirmation(options));
            }
        }

        return Swal.fire(...args);
    }

    function confirm(options = {}) {
        return fire(Object.assign({}, options, { showCancelButton: true }));
    }

    return Object.freeze({ fire, confirm });
})();

// Global Helper Functions for SweetAlert2 (kept for backward compatibility)
function confirmLink(event, url, message = 'คุณต้องการทำรายการนี้ใช่หรือไม่?') {
    event.preventDefault();
    AdminDialog.confirm({
        adminAction: 'default',
        text: message,
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}

function confirmDeleteForm(event, formId, message = 'คุณต้องการลบข้อมูลนี้ใช่หรือไม่?') {
    event.preventDefault();
    AdminDialog.confirm({
        adminAction: 'delete',
        text: message,
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById(formId).submit();
        }
    });
}

// Global Loading Overlay Function
function showLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.remove('hidden');
        loadingOverlay.classList.add('flex');
    }
}
