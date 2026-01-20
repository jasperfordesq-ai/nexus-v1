/**
 * Federation Onboarding Wizard - JavaScript
 * WCAG 2.1 AA Compliant
 */
(function() {
    'use strict';

    var config = window.federationOnboardingConfig || {};
    var basePath = config.basePath || '';
    var csrfToken = config.csrfToken || '';

    // State
    var currentStep = 1;
    var enableFederation = true;
    var privacyLevel = 'social';

    // Elements
    var steps = document.querySelectorAll('.wizard-step');
    var progressSteps = document.querySelectorAll('.progress-step');
    var progressLines = document.querySelectorAll('.progress-line');

    function showStep(stepNum) {
        steps.forEach(function(s) {
            s.classList.remove('active');
            s.setAttribute('hidden', '');
        });

        var step = document.querySelector('.wizard-step[data-step="' + stepNum + '"]');
        if (step) {
            step.classList.add('active');
            step.removeAttribute('hidden');
        }

        // Update progress
        progressSteps.forEach(function(ps, i) {
            var num = i + 1;
            ps.classList.remove('active', 'completed');
            ps.removeAttribute('aria-current');

            if (num < stepNum) {
                ps.classList.add('completed');
                ps.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i><span class="visually-hidden">Step ' + num + ' completed</span>';
            } else if (num === stepNum) {
                ps.classList.add('active');
                ps.setAttribute('aria-current', 'step');
                if (num < 4) {
                    ps.innerHTML = '<span class="visually-hidden">Step </span>' + num;
                }
            } else {
                if (num < 4) {
                    ps.innerHTML = '<span class="visually-hidden">Step </span>' + num;
                }
            }
        });

        progressLines.forEach(function(pl, i) {
            if (i + 1 < stepNum) {
                pl.classList.add('completed');
            } else {
                pl.classList.remove('completed');
            }
        });

        currentStep = stepNum;

        // Focus first focusable element in new step
        if (step) {
            var focusable = step.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable) {
                setTimeout(function() { focusable.focus(); }, 100);
            }
        }
    }

    // Option card selection with keyboard support
    document.querySelectorAll('.option-group').forEach(function(group) {
        group.querySelectorAll('.option-card').forEach(function(card) {
            card.addEventListener('click', function() {
                group.querySelectorAll('.option-card').forEach(function(c) {
                    c.classList.remove('selected');
                    c.setAttribute('aria-checked', 'false');
                });
                card.classList.add('selected');
                card.setAttribute('aria-checked', 'true');
            });

            card.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    card.click();
                }
            });
        });
    });

    // Step 1: Enable federation choice
    var step1Next = document.getElementById('step1Next');
    if (step1Next) {
        step1Next.addEventListener('click', function() {
            var selected = document.querySelector('.wizard-step[data-step="1"] .option-card.selected');
            enableFederation = selected && selected.dataset.value === 'yes';

            if (enableFederation) {
                showStep(2);
            } else {
                showStep('declined');
            }
        });
    }

    // Step 2: Privacy level
    var step2Back = document.getElementById('step2Back');
    var step2Next = document.getElementById('step2Next');

    if (step2Back) {
        step2Back.addEventListener('click', function() { showStep(1); });
    }

    if (step2Next) {
        step2Next.addEventListener('click', function() {
            var selected = document.querySelector('#privacyOptions .option-card.selected');
            privacyLevel = selected ? selected.dataset.value : 'social';

            // Auto-set toggles based on privacy level
            var toggleLocation = document.getElementById('toggleLocation');
            var toggleSkills = document.getElementById('toggleSkills');
            var toggleMessaging = document.getElementById('toggleMessaging');
            var toggleTransactions = document.getElementById('toggleTransactions');

            if (privacyLevel === 'discovery') {
                toggleLocation.checked = false;
                toggleSkills.checked = false;
                toggleMessaging.checked = false;
                toggleTransactions.checked = false;
            } else if (privacyLevel === 'social') {
                toggleLocation.checked = true;
                toggleSkills.checked = true;
                toggleMessaging.checked = true;
                toggleTransactions.checked = false;
            } else if (privacyLevel === 'economic') {
                toggleLocation.checked = true;
                toggleSkills.checked = true;
                toggleMessaging.checked = true;
                toggleTransactions.checked = true;
            }

            showStep(3);
        });
    }

    // Step 3: Fine-tune and save
    var step3Back = document.getElementById('step3Back');
    var step3Next = document.getElementById('step3Next');

    if (step3Back) {
        step3Back.addEventListener('click', function() { showStep(2); });
    }

    if (step3Next) {
        step3Next.addEventListener('click', function() {
            var btn = step3Next;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';

            var data = {
                federation_optin: true,
                privacy_level: privacyLevel,
                service_reach: 'local_only',
                show_location: document.getElementById('toggleLocation').checked,
                show_skills: document.getElementById('toggleSkills').checked,
                messaging_enabled: document.getElementById('toggleMessaging').checked,
                transactions_enabled: document.getElementById('toggleTransactions').checked
            };

            fetch(basePath + '/federation/onboarding/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(data)
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (result.success) {
                    showStep(4);
                    launchConfetti();
                } else {
                    throw new Error(result.error || 'Failed to save');
                }
            })
            .catch(function(error) {
                alert('Failed to save settings. Please try again.');
                btn.disabled = false;
                btn.innerHTML = 'Finish Setup <i class="fa-solid fa-check" aria-hidden="true"></i>';
            });
        });
    }

    // Confetti animation
    function launchConfetti() {
        var canvas = document.getElementById('confetti');
        if (!canvas) return;

        var ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        var pieces = [];
        var colors = ['#8b5cf6', '#7c3aed', '#10b981', '#f59e0b', '#ec4899'];

        for (var i = 0; i < 150; i++) {
            pieces.push({
                x: canvas.width / 2,
                y: canvas.height / 2,
                vx: (Math.random() - 0.5) * 20,
                vy: (Math.random() - 0.5) * 20 - 10,
                color: colors[Math.floor(Math.random() * colors.length)],
                size: Math.random() * 8 + 4,
                rotation: Math.random() * 360
            });
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            var active = false;
            pieces.forEach(function(p) {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.5;
                p.rotation += 5;

                if (p.y < canvas.height + 50) {
                    active = true;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rotation * Math.PI / 180);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
                    ctx.restore();
                }
            });

            if (active) {
                requestAnimationFrame(animate);
            } else {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }

        animate();
    }

    // Offline indicator
    var banner = document.getElementById('offlineBanner');
    if (banner) {
        window.addEventListener('online', function() { banner.classList.remove('visible'); });
        window.addEventListener('offline', function() { banner.classList.add('visible'); });
        if (!navigator.onLine) banner.classList.add('visible');
    }
})();
