/**
 * Federation Review Form - Interactive JavaScript
 * Handles star rating, character counting, and form submission
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get DOM elements
    const stars = document.querySelectorAll('#star-rating .star-btn');
    const ratingInput = document.getElementById('rating-input');
    const ratingText = document.getElementById('rating-text');
    const submitBtn = document.getElementById('submit-btn');
    const commentTextarea = document.getElementById('comment');
    const charCount = document.getElementById('char-count');
    const charCountText = document.getElementById('char-count-text');
    const reviewForm = document.getElementById('review-form');

    // Rating labels for user feedback
    const ratingLabels = {
        1: 'Poor - Had significant issues',
        2: 'Fair - Below expectations',
        3: 'Good - Met expectations',
        4: 'Very Good - Above expectations',
        5: 'Excellent - Outstanding experience!'
    };

    /**
     * Update star display based on rating
     */
    function updateStars(rating) {
        stars.forEach(star => {
            const starRating = parseInt(star.dataset.rating);
            const isActive = starRating <= rating;

            star.classList.toggle('active', isActive);
            star.setAttribute('aria-checked', isActive ? 'true' : 'false');

            const icon = star.querySelector('i');
            icon.classList.toggle('fas', isActive);
            icon.classList.toggle('far', !isActive);
        });
    }

    /**
     * Update rating text display
     */
    function updateRatingText(rating, isPermanent = false) {
        if (rating > 0) {
            ratingText.textContent = ratingLabels[rating];
            if (isPermanent) {
                ratingText.classList.remove('text-muted');
                ratingText.classList.add('text-primary', 'fw-bold');
            }
        } else {
            ratingText.textContent = 'Click to rate';
            ratingText.classList.add('text-muted');
            ratingText.classList.remove('text-primary', 'fw-bold');
        }
    }

    /**
     * Enable/disable submit button based on rating
     */
    function updateSubmitButton() {
        const hasRating = parseInt(ratingInput.value) > 0;
        submitBtn.disabled = !hasRating;
        submitBtn.setAttribute('aria-disabled', !hasRating);
    }

    /**
     * Announce to screen readers
     */
    function announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('role', 'status');
        announcement.setAttribute('aria-live', 'polite');
        announcement.className = 'sr-only';
        announcement.textContent = message;
        document.body.appendChild(announcement);
        setTimeout(() => announcement.remove(), 1000);
    }

    // Star rating click handler
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            ratingInput.value = rating;

            updateStars(rating);
            updateRatingText(rating, true);
            updateSubmitButton();

            // Announce to screen readers
            announceToScreenReader(`${rating} star${rating > 1 ? 's' : ''} selected. ${ratingLabels[rating]}`);

            // Add a subtle pulse effect to the form
            reviewForm.style.transform = 'scale(1.005)';
            setTimeout(() => {
                reviewForm.style.transform = 'scale(1)';
            }, 200);
        });

        // Hover preview
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            stars.forEach(s => {
                s.classList.toggle('hovered', parseInt(s.dataset.rating) <= rating);
            });
            updateRatingText(rating, false);
        });

        // Reset preview on mouse leave
        star.addEventListener('mouseleave', function() {
            stars.forEach(s => s.classList.remove('hovered'));
            const currentRating = parseInt(ratingInput.value);
            updateRatingText(currentRating, currentRating > 0);
        });

        // Keyboard navigation support
        star.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }

            // Arrow key navigation
            let targetRating = parseInt(this.dataset.rating);
            if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
                e.preventDefault();
                targetRating = Math.min(5, targetRating + 1);
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
                e.preventDefault();
                targetRating = Math.max(1, targetRating - 1);
            }

            if (targetRating !== parseInt(this.dataset.rating)) {
                const targetStar = document.querySelector(`.star-btn[data-rating="${targetRating}"]`);
                if (targetStar) {
                    targetStar.focus();
                }
            }
        });
    });

    // Character counter for comment
    if (commentTextarea && charCount) {
        commentTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;

            // Remove previous warning classes
            charCountText.classList.remove('char-warning', 'char-danger');

            // Visual feedback when approaching limit
            if (length > 1900) {
                charCountText.classList.add('char-danger');
            } else if (length > 1500) {
                charCountText.classList.add('char-warning');
            }

            // Add a subtle animation on milestone counts
            if (length % 100 === 0 && length > 0) {
                charCountText.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    charCountText.style.transform = 'scale(1)';
                }, 200);
            }
        });

        // Add focus effect
        commentTextarea.addEventListener('focus', function() {
            charCountText.style.opacity = '1';
        });

        commentTextarea.addEventListener('blur', function() {
            if (this.value.length === 0) {
                charCountText.style.opacity = '0.7';
            }
        });
    }

    // Form submission handler
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            const rating = parseInt(ratingInput.value);

            // Validate rating
            if (rating < 1 || rating > 5) {
                e.preventDefault();
                alert('Please select a rating between 1 and 5 stars before submitting.');

                // Focus on star rating
                const firstStar = document.querySelector('.star-btn');
                if (firstStar) {
                    firstStar.focus();
                }
                return false;
            }

            // Disable submit button and show loading state
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-disabled', 'true');
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

            // Disable all form inputs
            const formElements = reviewForm.querySelectorAll('input, textarea, button');
            formElements.forEach(el => el.disabled = true);

            // Prevent double submission
            reviewForm.onsubmit = function() {
                return false;
            };
        });
    }

    // Initialize button state
    updateSubmitButton();

    // Add smooth scroll if user lands on page with error
    if (window.location.hash === '#review-form') {
        setTimeout(() => {
            reviewForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }

    // Add subtle entrance animations
    const reviewCard = document.querySelector('.review-card');
    if (reviewCard) {
        setTimeout(() => {
            reviewCard.style.opacity = '1';
            reviewCard.style.transform = 'translateY(0)';
        }, 50);
    }

    // Offline banner logic
    const offlineBanner = document.getElementById('offlineBanner');
    if (offlineBanner) {
        const updateOnlineStatus = function() {
            if (!navigator.onLine) {
                offlineBanner.classList.add('visible');
                document.body.classList.add('is-offline');
                submitBtn.disabled = true;
                submitBtn.title = 'Cannot submit while offline';
            } else {
                offlineBanner.classList.remove('visible');
                document.body.classList.remove('is-offline');
                updateSubmitButton(); // Re-check if button should be enabled
                submitBtn.title = '';
            }
        };

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus();
    }
});
