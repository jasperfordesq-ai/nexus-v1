/**
 * Nexus Score Charts Component JavaScript
 * Chart initialization and interactions
 * CivicOne Theme
 */

// Animate bars on load
document.addEventListener('DOMContentLoaded', function() {
    const bars = document.querySelectorAll('.bar-fill');
    bars.forEach(bar => {
        // eslint-disable-next-line no-restricted-syntax -- dynamic chart width animation
        const width = bar.style.width;
        // eslint-disable-next-line no-restricted-syntax -- dynamic chart width animation
        bar.style.width = '0%';
        setTimeout(() => {
            // eslint-disable-next-line no-restricted-syntax -- dynamic chart width animation
            bar.style.width = width;
        }, 200);
    });
});
