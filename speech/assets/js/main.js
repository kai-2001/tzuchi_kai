/**
 * Speech Portal - Main JavaScript
 * Shared functions across all pages
 */

// Global login state (will be set by individual pages)
window.isLoggedIn = window.isLoggedIn || false;

/**
 * Check authentication before accessing a video
 * @param {number} videoId - The video ID to navigate to
 */
function checkAuth(videoId) {
    if (window.isLoggedIn) {
        window.location.href = 'watch.php?id=' + videoId;
    } else {
        document.getElementById('loginModal').style.display = 'flex';
    }
}

/**
 * Close the login modal
 */
function closeModal() {
    var modal = document.getElementById('loginModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Confirm delete action with confirmation dialog
 * @param {number} id - The ID of the item to delete
 * @param {string} type - The type of item (default: 'video')
 */
function confirmDelete(id, type) {
    type = type || 'video';
    var message = '確定要刪除這部影片嗎？此動作無法復原。';

    if (confirm(message)) {
        window.location.href = 'delete_video.php?id=' + id;
    }
}
