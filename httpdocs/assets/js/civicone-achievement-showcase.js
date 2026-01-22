/**
 * Achievement Showcase Component JavaScript
 * Handles tab switching and interactions
 * CivicOne Theme
 */

// Category filter functionality
document.querySelectorAll('.category-tag').forEach(tag => {
    tag.addEventListener('click', function() {
        const category = this.dataset.category;

        // Update active state
        document.querySelectorAll('.category-tag').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        // Filter badges (implement based on badge data attributes)
        console.log('Filter by category:', category);
    });
});
