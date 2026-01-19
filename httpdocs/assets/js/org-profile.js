/**
 * Organization Profile - Premium Micro-interactions
 * Gold Standard FDS Holographic Glassmorphism
 */

(function() {
    'use strict';

    // Initialize AOS (Animate On Scroll)
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true,
            offset: 50,
            delay: 100
        });
    }

    // Counter Animation for Stats
    function animateCounter(element) {
        const target = parseInt(element.getAttribute('data-count'));
        if (!target || isNaN(target)) return;

        const duration = 2000;
        const steps = 60;
        const increment = target / steps;
        const stepDuration = duration / steps;
        let current = 0;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target;
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current);
            }
        }, stepDuration);
    }

    // Initialize counter animations when stat cards come into view
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target.querySelector('[data-count]');
                if (counter && !counter.classList.contains('animated')) {
                    counter.classList.add('animated');
                    animateCounter(counter);
                }
            }
        });
    }, observerOptions);

    // Observe stat cards
    document.addEventListener('DOMContentLoaded', () => {
        const statCards = document.querySelectorAll('.org-stat-card');
        statCards.forEach(card => observer.observe(card));

        // Add parallax effect to hero banner
        const heroBanner = document.querySelector('.org-hero-banner');
        if (heroBanner) {
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const rate = scrolled * 0.3;
                heroBanner.style.transform = `translateY(${rate}px)`;
            });
        }

        // Add hover ripple effect to cards
        const cards = document.querySelectorAll('.org-opp-card, .org-stat-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function(e) {
                const ripple = document.createElement('div');
                ripple.className = 'card-ripple';
                ripple.style.left = e.clientX - this.getBoundingClientRect().left + 'px';
                ripple.style.top = e.clientY - this.getBoundingClientRect().top + 'px';
                this.appendChild(ripple);

                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add shine effect on profile card
        const profileCard = document.querySelector('.org-profile-card');
        if (profileCard) {
            profileCard.addEventListener('mousemove', (e) => {
                const rect = profileCard.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) * 100;
                const y = ((e.clientY - rect.top) / rect.height) * 100;

                profileCard.style.setProperty('--mouse-x', `${x}%`);
                profileCard.style.setProperty('--mouse-y', `${y}%`);
            });
        }

        // Add stagger animation to opportunity cards
        const oppCards = document.querySelectorAll('.org-opp-card');
        oppCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
    });

    // Performance: Reduce motion for users who prefer it
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.documentElement.style.setProperty('--animation-duration', '0.01ms');
    }

})();
