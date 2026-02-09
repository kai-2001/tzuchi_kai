/**
 * Speech Portal - Upload & Edit Logic
 * Handles XHR form submission with progress for videos/announcements
 * and multi-speaker form management
 */
document.addEventListener('DOMContentLoaded', function () {
    // ==========================================
    // Multi-Speaker Management
    // ==========================================
    const speakersContainer = document.getElementById('speakers-container');
    const addSpeakerBtn = document.getElementById('addSpeaker');

    if (speakersContainer && addSpeakerBtn) {
        /**
         * Create speaker item DOM element
         * @param {number} index - Speaker index
         * @param {object} data - Speaker data {name, affiliation, position}
         * @returns {HTMLElement}
         */
        function createSpeakerItem(index, data = {}) {
            const item = document.createElement('div');
            item.className = 'speaker-item';
            item.dataset.index = index;

            // Escape HTML to prevent XSS
            const escapedName = escapeHtml(data.name || '');
            const escapedAffiliation = escapeHtml(data.affiliation || '');
            const escapedPosition = escapeHtml(data.position || '');

            item.innerHTML = `
                <div class="speaker-item-header">
                    <span class="speaker-number">講者 ${index + 1}</span>
                    <button type="button" class="btn-remove-speaker" data-index="${index}">
                        <i class="fas fa-times"></i> 移除
                    </button>
                </div>
                <div class="speaker-fields">
                    <div class="speaker-field">
                        <label>姓名 <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="speakers[${index}][name]" value="${escapedName}" required>
                    </div>
                    <div class="speaker-field">
                        <label>服務單位</label>
                        <input type="text" name="speakers[${index}][affiliation]" value="${escapedAffiliation}">
                    </div>
                    <div class="speaker-field">
                        <label>職務</label>
                        <input type="text" name="speakers[${index}][position]" value="${escapedPosition}">
                    </div>
                </div>
            `;

            return item;
        }

        /**
         * Add new speaker to form
         * @param {object} data - Optional speaker data
         */
        function addSpeaker(data = {}) {
            const currentCount = speakersContainer.querySelectorAll('.speaker-item').length;
            const item = createSpeakerItem(currentCount, data);
            speakersContainer.appendChild(item);
            updateRemoveButtons();
        }

        /**
         * Remove speaker from form
         * @param {number} index - Speaker index to remove
         */
        function removeSpeaker(index) {
            const item = speakersContainer.querySelector(`.speaker-item[data-index="${index}"]`);
            if (item) {
                item.style.animation = 'slideOut 0.2s ease-in';
                setTimeout(() => {
                    item.remove();
                    reindexSpeakers();
                    updateRemoveButtons();
                }, 200);
            }
        }

        /**
         * Reindex all speakers after removal
         */
        function reindexSpeakers() {
            const items = speakersContainer.querySelectorAll('.speaker-item');
            items.forEach((item, idx) => {
                // Update display number
                item.querySelector('.speaker-number').textContent = `講者 ${idx + 1}`;

                // Update data-index
                item.dataset.index = idx;

                // Update form field names
                const inputs = item.querySelectorAll('input');
                inputs.forEach(input => {
                    const match = input.name.match(/\[([^\]]+)\]$/);
                    if (match) {
                        const fieldName = match[1];
                        input.name = `speakers[${idx}][${fieldName}]`;
                    }
                });

                // Update remove button index
                const removeBtn = item.querySelector('.btn-remove-speaker');
                if (removeBtn) {
                    removeBtn.dataset.index = idx;
                }
            });
        }

        /**
         * Update remove button visibility (min 1 speaker required)
         */
        function updateRemoveButtons() {
            const items = speakersContainer.querySelectorAll('.speaker-item');
            const removeButtons = speakersContainer.querySelectorAll('.btn-remove-speaker');

            if (items.length === 1) {
                removeButtons.forEach(btn => btn.style.display = 'none');
            } else {
                removeButtons.forEach(btn => btn.style.display = 'inline-flex');
            }
        }

        /**
         * Escape HTML to prevent XSS
         * @param {string} text - Text to escape
         * @returns {string}
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Event: Add speaker button
        addSpeakerBtn.addEventListener('click', () => addSpeaker());

        // Event: Remove speaker (event delegation)
        speakersContainer.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.btn-remove-speaker');
            if (removeBtn) {
                const index = parseInt(removeBtn.dataset.index);
                removeSpeaker(index);
            }
        });

        // Initialize first speaker (only for upload page, not edit)
        const isEditPage = window.location.href.includes('edit_video.php');
        if (!isEditPage && speakersContainer.children.length === 0) {
            addSpeaker();
        }

        // Make functions globally accessible for edit page
        window.addSpeaker = addSpeaker;
    }

    // ==========================================
    // Form Submission with XHR Progress
    // (ONLY for edit page, upload page uses normal submit)
    // ==========================================
    const form = document.querySelector('form');

    // Only use XHR for edit_video.php, let upload.php use normal form submission
    if (!form || !form.action.includes('edit_video.php')) {
        return; // Exit early for upload.php
    }

    const btnSubmit = document.getElementById('btn-submit');
    const progressContainer = document.getElementById('progress-container');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');

    if (!btnSubmit || !progressContainer) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Determine button text based on context
        const isEdit = form.action.includes('edit_video.php');
        const processingText = isEdit ? '處理中...' : '上傳中...';
        const fallbackText = isEdit ? '儲存修改' : '開始上傳';

        // Disable button
        btnSubmit.disabled = true;
        btnSubmit.innerText = processingText;
        progressContainer.style.display = 'block';

        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();

        xhr.open('POST', form.action, true);

        // Upload Progress
        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressText.innerText = percent + '%';

                if (percent >= 100) {
                    progressText.innerText = '傳送完成，正在處理資料...';
                }
            }
        };

        // Complete
        xhr.onload = function () {
            if (xhr.status === 200) {
                // Check if redirected to a different page (Success)
                const currentFile = isEdit ? 'edit_video.php' : 'upload.php';
                if (xhr.responseURL && !xhr.responseURL.includes(currentFile)) {
                    // Successfully redirected - navigate to new page
                    window.location.href = xhr.responseURL;
                } else {
                    // Stayed on same page - replace entire document to show result
                    // This preserves the original behavior while allowing JS to re-initialize
                    document.open();
                    document.write(xhr.responseText);
                    document.close();
                }
            } else {
                alert('操作失敗: ' + xhr.statusText);
                btnSubmit.disabled = false;
                btnSubmit.innerText = fallbackText;
                progressContainer.style.display = 'none';
            }
        };

        xhr.onerror = function () {
            alert('網路錯誤，操作失敗。');
            btnSubmit.disabled = false;
            btnSubmit.innerText = fallbackText;
            progressContainer.style.display = 'none';
        };

        xhr.send(formData);
    });
});
