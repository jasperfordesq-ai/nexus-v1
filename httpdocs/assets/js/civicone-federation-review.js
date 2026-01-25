/**
 * CivicOne Federation Review Form
 * Star rating and character counter functionality
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    var starRating = document.getElementById('star-rating');
    var ratingInput = document.getElementById('rating-input');
    var ratingText = document.getElementById('rating-text');
    var submitBtn = document.getElementById('submit-btn');
    var commentInput = document.getElementById('comment');
    var charCount = document.getElementById('char-count');

    var ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    if (starRating) {
        var stars = starRating.querySelectorAll('button');

        stars.forEach(function(star) {
            star.addEventListener('click', function() {
                var rating = parseInt(this.dataset.rating);
                ratingInput.value = rating;

                stars.forEach(function(s, i) {
                    var icon = s.querySelector('i');
                    s.setAttribute('aria-checked', i < rating ? 'true' : 'false');
                    icon.className = i < rating ? 'fa-solid fa-star fa-lg' : 'far fa-star fa-lg';
                    icon.classList.toggle('civicone-star-selected', i < rating);
                });

                ratingText.textContent = ratingLabels[rating];
                submitBtn.disabled = false;
                submitBtn.setAttribute('aria-disabled', 'false');
            });

            star.addEventListener('mouseenter', function() {
                var rating = parseInt(this.dataset.rating);
                stars.forEach(function(s, i) {
                    var icon = s.querySelector('i');
                    icon.className = i < rating ? 'fa-solid fa-star fa-lg' : 'far fa-star fa-lg';
                    icon.classList.toggle('civicone-star-selected', i < rating);
                });
            });
        });

        starRating.addEventListener('mouseleave', function() {
            var currentRating = parseInt(ratingInput.value);
            stars.forEach(function(s, i) {
                var icon = s.querySelector('i');
                icon.className = i < currentRating ? 'fa-solid fa-star fa-lg' : 'far fa-star fa-lg';
                icon.classList.toggle('civicone-star-selected', i < currentRating);
            });
        });
    }

    if (commentInput && charCount) {
        commentInput.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    }
})();
