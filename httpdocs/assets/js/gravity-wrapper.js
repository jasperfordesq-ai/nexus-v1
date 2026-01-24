/**
 * Anti-Gravity Safety Wrapper
 * 
 * This script ensures that the Google Anti-Gravity physics engine only runs
 * when the correct DOM elements are present (i.e., on the Default layout).
 */

document.addEventListener('DOMContentLoaded', function () {
    // Check if the current layout is 'default' (or checks for specific DOM elements)
    // We can rely on a data-attribute on the body or check for the container.

    // Assuming the physics engine expects specific containers.
    // If we are in 'Modern' layout, these might be missing.

    const gravityContainer = document.querySelector('body'); // Or specific container

    // Check for a flag or class that indicates we should run gravity
    // For now, we will check if we are NOT in the modern layout (or if we ARE in the default).
    // Let's add a data attribute to the body in the Default Header to make this explicit.

    const isDefaultLayout = document.body.getAttribute('data-layout') === 'default';

    if (isDefaultLayout && typeof $.fn.jGravity === 'function') {
        console.warn('Gravity: Ready');

        // Add Gravity Toggle to Footer
        const footer = document.querySelector('footer.container small');
        if (footer) {
            const separator = document.createTextNode(' • ');
            const gravityLink = document.createElement('a');
            gravityLink.href = '#';
            gravityLink.innerText = 'Gravity';
            gravityLink.className = 'gravity-link';
            gravityLink.onclick = function (e) {
                e.preventDefault();
                $('body').jGravity({
                    target: 'body',
                    ignoreClass: 'ignoreMe',
                    weight: 25,
                    depth: 5,
                    drag: true
                });
            };
            footer.appendChild(separator);
            footer.appendChild(gravityLink);
        }
    } else {
        console.warn('Gravity: Disabled (Layout differs or jGravity missing)');
    }
});
