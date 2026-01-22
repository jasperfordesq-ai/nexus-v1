/**
 * CivicOne Resources Download - Automatic Download Timer
 * WCAG 2.1 AA Compliant
 * Countdown timer with automatic file download
 */

(function() {
    'use strict';

    const countdown = document.getElementById('countdown');
    const statusEl = document.getElementById('downloadStatus');
    const downloadUrl = document.getElementById('manualDownload')?.href;

    if (!countdown || !statusEl || !downloadUrl) return;

    let seconds = 5;

    const timer = setInterval(() => {
        seconds--;
        countdown.textContent = seconds;

        if (seconds <= 0) {
            clearInterval(timer);
            triggerDownload();
        }
    }, 1000);

    function triggerDownload() {
        statusEl.textContent = 'Starting download...';
        statusEl.classList.add('success');

        // Create hidden iframe for download
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = downloadUrl;
        document.body.appendChild(iframe);

        setTimeout(() => {
            statusEl.textContent = 'Download started!';
            countdown.textContent = '✓';
        }, 500);

        // Redirect back after 5 seconds
        setTimeout(() => {
            statusEl.textContent = 'Redirecting to resources...';
            const basePath = window.location.pathname.split('/resources/')[0];
            setTimeout(() => {
                window.location.href = basePath + '/resources';
            }, 1000);
        }, 4000);
    }

    // Manual download trigger
    document.getElementById('manualDownload')?.addEventListener('click', function(e) {
        e.preventDefault();
        clearInterval(timer);
        triggerDownload();
    });

})();
