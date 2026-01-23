/**
 * Speech Portal - Video Player Tracking Logic
 * Extracted from templates/watch.php
 */
function initPlayer(videoId, lastSavedPosition) {
    const video = document.getElementById('mainVideo');
    if (!video) return;

    const chapters = document.querySelectorAll('.chapter-item');
    let initialSeekDone = false;
    let localLastSavedPosition = lastSavedPosition || 0;

    // 1. Auto-resume logic
    video.addEventListener('loadedmetadata', function () {
        if (localLastSavedPosition > 1 && !initialSeekDone) {
            video.currentTime = localLastSavedPosition;
        }
        initialSeekDone = true;
    });

    // 2. Progress Tracking (Throttled)
    let lastUpdateTime = Date.now();
    video.addEventListener('timeupdate', function () {
        if (!initialSeekDone) return;

        const now = Date.now();
        const currentPos = Math.floor(video.currentTime);

        // Save progress every 5 seconds
        if (now - lastUpdateTime > 5000 && currentPos !== localLastSavedPosition) {
            saveProgress(videoId, currentPos);
            lastUpdateTime = now;
            localLastSavedPosition = currentPos;
        }

        // Highlight current chapter
        highlightChapter(currentPos, chapters);
    });

    // 3. Chapter Navigation
    chapters.forEach(item => {
        item.addEventListener('click', function () {
            const time = parseInt(this.dataset.time);
            video.currentTime = time;
            video.play();
        });
    });
}

function saveProgress(videoId, pos) {
    const formData = new FormData();
    formData.append('video_id', videoId);
    formData.append('position', pos);

    fetch('update_progress.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data && !data.success) console.error('Progress save error:', data.error);
        })
        .catch(err => console.error('Progress sync error:', err));
}

function highlightChapter(currentTime, chapters) {
    let activeIndex = -1;
    chapters.forEach((item, index) => {
        const startTime = parseInt(item.dataset.time);
        const nextItem = chapters[index + 1];
        const endTime = nextItem ? parseInt(nextItem.dataset.time) : Infinity;

        if (currentTime >= startTime && currentTime < endTime) {
            activeIndex = index;
        }
    });

    chapters.forEach((item, index) => {
        if (index === activeIndex) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}
