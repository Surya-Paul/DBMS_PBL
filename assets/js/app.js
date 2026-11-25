/**
 * Campus Parking Management System - Vanilla JavaScript Enhancements
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Live Parking Record Stay Category & Estimated Charge Calculation
    const hoursInput = document.getElementById('hours_parked');
    const slotSelect = document.getElementById('slot_id');
    const categoryDisplay = document.getElementById('stay_category_display');
    const categoryHidden = document.getElementById('stay_category');
    const chargeDisplay = document.getElementById('estimated_charge_display');

    function updateParkingCalculations() {
        if (!hoursInput) return;

        let hours = parseFloat(hoursInput.value);
        if (isNaN(hours) || hours < 0) {
            hours = 0;
        }

        // Stay Category Logic:
        // Short: < 4 hours
        // Medium: 4 to < 8 hours
        // Long: >= 8 hours
        let category = 'Short';
        let badgeClass = 'bg-info text-dark';
        let icon = '<i class="bi bi-clock me-1"></i>';

        if (hours >= 8.0) {
            category = 'Long';
            badgeClass = 'bg-danger text-white';
            icon = '<i class="bi bi-alarm-fill me-1"></i>';
        } else if (hours >= 4.0) {
            category = 'Medium';
            badgeClass = 'bg-warning text-dark';
            icon = '<i class="bi bi-hourglass-split me-1"></i>';
        }

        if (categoryDisplay) {
            categoryDisplay.innerHTML = `<span class="badge ${badgeClass} fs-6">${icon}${category} Stay</span>`;
        }
        if (categoryHidden) {
            categoryHidden.value = category;
        }

        // Estimated Charge Logic
        if (slotSelect && chargeDisplay) {
            const selectedOption = slotSelect.options[slotSelect.selectedIndex];
            const hourlyRate = selectedOption ? parseFloat(selectedOption.getAttribute('data-rate') || 0) : 0;
            const totalCharge = (hours * hourlyRate).toFixed(2);
            chargeDisplay.innerHTML = `<strong>₹${totalCharge}</strong> <span class="text-muted small">(${hours} hrs &times; ₹${hourlyRate}/hr)</span>`;
        }
    }

    if (hoursInput) {
        hoursInput.addEventListener('input', updateParkingCalculations);
    }
    if (slotSelect) {
        slotSelect.addEventListener('change', updateParkingCalculations);
        // Trigger initial calculation on load if values exist
        updateParkingCalculations();
    }

    // 2. Client-side Safe Delete Confirmation Prompt
    const deleteButtons = document.querySelectorAll('.btn-confirm-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            const itemName = this.getAttribute('data-name') || 'this record';
            const depCount = parseInt(this.getAttribute('data-dependencies') || '0', 10);
            
            let message = `Are you sure you want to delete ${itemName}?`;
            if (depCount > 0) {
                message = `WARNING: ${itemName} has ${depCount} dependent record(s) linked to it!\n\nDeleting it may violate foreign key constraints or require deleting linked records first.\n\nDo you still wish to proceed?`;
            }
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 3. Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 6000);
    });
});
