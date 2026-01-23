/**
 * Nexus Score Dashboard Component JavaScript
 * Dashboard initialization and interactions
 * CivicOne Theme
 */

// Animate progress bars on load
document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.category-progress-fill, .milestone-progress-fill');
    progressBars.forEach(bar => {
        // eslint-disable-next-line no-restricted-syntax -- dynamic progress width animation
        const width = bar.style.width;
        // eslint-disable-next-line no-restricted-syntax -- dynamic progress width animation
        bar.style.width = '0%';
        setTimeout(() => {
            // eslint-disable-next-line no-restricted-syntax -- dynamic progress width animation
            bar.style.width = width;
        }, 100);
    });
});
