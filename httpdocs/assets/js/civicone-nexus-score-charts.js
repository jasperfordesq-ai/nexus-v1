/**
 * Nexus Score Charts Component JavaScript
 * Chart initialization and interactions
 * CivicOne Theme
 */

// Animate bars on load
document.addEventListener('DOMContentLoaded', function() {
    const bars = document.querySelectorAll('.bar-fill');
    bars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 200);
    });
});
