/**
 * Speech Portal - Upload & Edit Logic
 * Handles XHR form submission with progress for videos/announcements
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    // Common logic for upload.php and edit_video.php (often edit.php in templates)
    if (!form || (!form.action.includes('upload.php') && !form.action.includes('edit_video.php'))) return;

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
                    window.location.href = xhr.responseURL;
                } else {
                    // Stayed on same page, likely error or refresh required
                    document.documentElement.innerHTML = xhr.responseText;
                    // Optional: window.location.reload();
                }
            } else {
                alert('操作失敗: ' + xhr.statusText);
                btnSubmit.disabled = false;
                btnSubmit.innerText = fallbackText;
            }
        };

        xhr.onerror = function () {
            alert('網路錯誤，操作失敗。');
            btnSubmit.disabled = false;
            btnSubmit.innerText = fallbackText;
        };

        xhr.send(formData);
    });
});
