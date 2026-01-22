/**
 * Nexus Score Dashboard Component JavaScript
 * Dashboard initialization and interactions
 * CivicOne Theme
 */

// Animate progress bars on load
document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.category-progress-fill, .milestone-progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
});
