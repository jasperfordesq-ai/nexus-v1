/**
 * Nexus Resize Handler
 * Prevents animation jank during window resize
 */

(function() {
    'use strict';

    let resizeTimer;

    function handleResize() {
        // Add resizing class to disable transitions
        document.documentElement.classList.add('resizing');

        // Clear existing timer
        clearTimeout(resizeTimer);

        // Re-enable transitions after resize stops
        resizeTimer = setTimeout(function() {
            document.documentElement.classList.remove('resizing');
        }, 250);
    }

    // Listen for resize events
    window.addEventListener('resize', handleResize, { passive: true });

    // Handle orientation change on mobile
    window.addEventListener('orientationchange', function() {
        handleResize();

        // Extra delay for orientation change
        setTimeout(function() {
            document.documentElement.classList.remove('resizing');
        }, 500);
    }, { passive: true });

})();
