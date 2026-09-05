/**
 * Preschool Monitoring System - Main Interactivity
 */

document.addEventListener('DOMContentLoaded', () => {
    // Mobile Sidebar Toggle
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebar = document.getElementById('appSidebar');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Modal Triggers
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close modal when clicking on overlay background
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // Instant Table Live Search Filter
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
        const targetTableId = input.getAttribute('data-table-search');
        const targetTable = document.getElementById(targetTableId);
        if (!targetTable) return;

        input.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = targetTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });

    // Auto-dismiss Flash alerts after 6 seconds
    const flashAlerts = document.querySelectorAll('.flash-alert');
    flashAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.4s ease';
            setTimeout(() => alert.remove(), 400);
        }, 6000);
    });

    // Print helper
    window.triggerPrint = function() {
        window.print();
    };
});
