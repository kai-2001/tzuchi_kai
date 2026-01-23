/**
 * Speech Portal - Announcements Logic
 * Handles filtering, management actions, and form interactions
 */

/**
 * Filter announcement rows by campus ID
 */
function filterAnnouncements(campusId, tabElement) {
    // 1. Update Tab Active State
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    tabElement.classList.add('active');

    // 2. Filter Rows
    const rows = document.querySelectorAll('.announcement-row');
    const isAll = (campusId === 'all');

    rows.forEach(row => {
        const rowCId = row.getAttribute('data-campus-id');
        if (isAll) {
            row.style.display = '';
        } else {
            if (rowCId == campusId || rowCId == 0) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

/**
 * Update sort order for an announcement (Used in manage view)
 */
function updateSortOrder(id, newOrder) {
    fetch('manage_announcements.php?action=update_order&id=' + id + '&order=' + newOrder)
        .then(response => {
            if (response.ok) {
                console.log('Order updated');
            } else {
                alert('排序更新失敗');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('排序更新發生錯誤');
        });
}

/**
 * Initialization for Announcement Add/Edit forms
 */
function initAnnouncementForm() {
    const radioShow = document.getElementById('hero_show');
    const radioHide = document.getElementById('hero_hide');
    const heroFields = document.getElementById('hero-fields');
    if (!radioShow || !heroFields) return;

    const inputs = heroFields.querySelectorAll('input, select, textarea');
    const eventDateInput = document.getElementById('event_date');
    const heroStartDateInput = document.getElementById('hero_start_date');
    const heroEndDateInput = document.getElementById('hero_end_date');

    function updateState() {
        const isShow = radioShow.checked;
        inputs.forEach(el => el.disabled = !isShow);
        heroFields.style.opacity = isShow ? '1' : '0.5';
    }

    // Auto-fill dates logic
    if (eventDateInput && heroStartDateInput && heroEndDateInput) {
        eventDateInput.addEventListener('change', function () {
            if (!this.value) return;

            const eventDate = new Date(this.value);

            // Start date: 7 days before
            const startDate = new Date(eventDate);
            startDate.setDate(eventDate.getDate() - 7);
            heroStartDateInput.value = startDate.toISOString().split('T')[0];

            // End date: 1 day after
            const endDate = new Date(eventDate);
            endDate.setDate(eventDate.getDate() + 1);
            heroEndDateInput.value = endDate.toISOString().split('T')[0];
        });
    }

    radioShow.addEventListener('change', updateState);
    radioHide.addEventListener('change', updateState);

    // Initial run
    updateState();
}

document.addEventListener('DOMContentLoaded', () => {
    initAnnouncementForm();
});
