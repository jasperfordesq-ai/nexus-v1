<?php
// Phoenix View: Contact Us
$pageTitle = 'Contact Us';
$hideHero = true;

require __DIR__ . '/../../../..' . '/layouts/modern/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<style>
/* ============================================
   GOLD STANDARD - Native App Features
   ============================================ */

/* Offline Banner */
.offline-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10001;
    padding: 12px 20px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transform: translateY(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.offline-banner.visible {
    transform: translateY(0);
}

/* Content Reveal Animation */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#contact-glass-wrapper .nexus-welcome-hero {
    animation: fadeInUp 0.4s ease-out;
}

#contact-glass-wrapper .contact-form-card {
    animation: fadeInUp 0.4s ease-out 0.1s both;
}

#contact-glass-wrapper .contact-info-card {
    animation: fadeInUp 0.4s ease-out 0.2s both;
}

/* Button Press States */
#contact-glass-wrapper .submit-btn:active,
#contact-glass-wrapper .nexus-smart-btn:active,
#contact-glass-wrapper .quick-link:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
#contact-glass-wrapper .submit-btn,
#contact-glass-wrapper .nexus-smart-btn,
#contact-glass-wrapper .quick-link,
#contact-glass-wrapper .form-group input,
#contact-glass-wrapper .form-group textarea {
    min-height: 44px;
}

/* iOS Zoom Prevention */
#contact-glass-wrapper .form-group input,
#contact-glass-wrapper .form-group textarea {
    font-size: 16px !important;
}

/* Focus Visible */
#contact-glass-wrapper .submit-btn:focus-visible,
#contact-glass-wrapper .nexus-smart-btn:focus-visible,
#contact-glass-wrapper .quick-link:focus-visible,
#contact-glass-wrapper .form-group input:focus-visible,
#contact-glass-wrapper .form-group textarea:focus-visible {
    outline: 3px solid rgba(2, 132, 199, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    #contact-glass-wrapper .submit-btn,
    #contact-glass-wrapper .nexus-smart-btn,
    #contact-glass-wrapper .quick-link {
        min-height: 48px;
    }
}
</style>

<style>
/* ========================================
   CONTACT - GLASSMORPHISM 2025
   Theme: Sky Blue (#0284c7)
   ======================================== */

#contact-glass-wrapper {
    --contact-theme: #0284c7;
    --contact-theme-rgb: 2, 132, 199;
    --contact-theme-light: #0ea5e9;
    --contact-theme-dark: #0369a1;
    --contact-pink: #db2777;
    --contact-pink-rgb: 219, 39, 119;
    position: relative;
    min-height: 100vh;
    padding: 2rem 1rem 4rem;
    margin-top: 120px;
}

/* ===================================
   NEXUS WELCOME HERO - Gold Standard
   =================================== */
#contact-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(2, 132, 199, 0.12) 0%,
        rgba(14, 165, 233, 0.12) 50%,
        rgba(56, 189, 248, 0.08) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 20px;
    padding: 28px 24px;
    margin-bottom: 30px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(2, 132, 199, 0.1);
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
}

[data-theme="dark"] #contact-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(2, 132, 199, 0.15) 0%,
        rgba(14, 165, 233, 0.15) 50%,
        rgba(56, 189, 248, 0.1) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

#contact-glass-wrapper .nexus-welcome-title {
    font-size: 1.75rem;
    font-weight: 800;
    background: linear-gradient(135deg, #0284c7, #0ea5e9, #38bdf8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 8px 0;
    line-height: 1.2;
}

#contact-glass-wrapper .nexus-welcome-subtitle {
    font-size: 0.95rem;
    color: var(--htb-text-muted);
    margin: 0 0 20px 0;
    line-height: 1.5;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

#contact-glass-wrapper .nexus-smart-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

#contact-glass-wrapper .nexus-smart-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

#contact-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

#contact-glass-wrapper .nexus-smart-btn-primary {
    background: linear-gradient(135deg, #0284c7, #0ea5e9);
    color: white;
    box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
}

#contact-glass-wrapper .nexus-smart-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(2, 132, 199, 0.45);
}

#contact-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(255, 255, 255, 0.5);
    color: var(--htb-text-main);
    border-color: rgba(2, 132, 199, 0.3);
}

[data-theme="dark"] #contact-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(14, 165, 233, 0.4);
}

#contact-glass-wrapper .nexus-smart-btn-outline:hover {
    background: linear-gradient(135deg, #0284c7, #0ea5e9);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

@media (max-width: 640px) {
    #contact-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; }
    #contact-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
    #contact-glass-wrapper .nexus-smart-buttons { gap: 8px; }
    #contact-glass-wrapper .nexus-smart-btn { padding: 10px 14px; font-size: 0.8rem; }
}

#contact-glass-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #contact-glass-wrapper::before {
    background: linear-gradient(135deg,
        rgba(2, 132, 199, 0.08) 0%,
        rgba(14, 165, 233, 0.08) 25%,
        rgba(56, 189, 248, 0.08) 50%,
        rgba(125, 211, 252, 0.08) 75%,
        rgba(2, 132, 199, 0.08) 100%);
    background-size: 400% 400%;
    animation: contactGradientShift 15s ease infinite;
}

[data-theme="dark"] #contact-glass-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(2, 132, 199, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(14, 165, 233, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(56, 189, 248, 0.05) 0%, transparent 70%);
}

@keyframes contactGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

#contact-glass-wrapper .contact-inner {
    max-width: 1100px;
    margin: 0 auto;
}

/* Page Header */
#contact-glass-wrapper .contact-page-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

#contact-glass-wrapper .contact-page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#contact-glass-wrapper .contact-page-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    margin: 0;
    max-width: 600px;
    margin: 0 auto;
}

/* Contact Grid */
#contact-glass-wrapper .contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    align-items: start;
}

/* Form Card */
#contact-glass-wrapper .contact-form-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
    transition: all 0.3s ease;
}

[data-theme="light"] #contact-glass-wrapper .contact-form-card {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(2, 132, 199, 0.15);
    box-shadow: 0 8px 32px rgba(2, 132, 199, 0.1);
}

[data-theme="dark"] #contact-glass-wrapper .contact-form-card {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(2, 132, 199, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#contact-glass-wrapper .contact-form-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Success Message */
#contact-glass-wrapper .success-message {
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

[data-theme="light"] #contact-glass-wrapper .success-message {
    background: rgba(5, 150, 105, 0.15);
    border: 1px solid rgba(5, 150, 105, 0.3);
    color: #065f46;
}

[data-theme="dark"] #contact-glass-wrapper .success-message {
    background: rgba(5, 150, 105, 0.2);
    border: 1px solid rgba(5, 150, 105, 0.4);
    color: #6ee7b7;
}

#contact-glass-wrapper .success-message .success-icon {
    font-size: 1.25rem;
}

/* Form Styles */
#contact-glass-wrapper .form-group {
    margin-bottom: 1.25rem;
}

#contact-glass-wrapper .form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--htb-text-main);
    font-size: 0.95rem;
}

#contact-glass-wrapper .form-group input,
#contact-glass-wrapper .form-group textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    font-family: inherit;
}

[data-theme="light"] #contact-glass-wrapper .form-group input,
[data-theme="light"] #contact-glass-wrapper .form-group textarea {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(2, 132, 199, 0.2);
    color: var(--htb-text-main);
}

[data-theme="dark"] #contact-glass-wrapper .form-group input,
[data-theme="dark"] #contact-glass-wrapper .form-group textarea {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(2, 132, 199, 0.3);
    color: var(--htb-text-main);
}

#contact-glass-wrapper .form-group input:focus,
#contact-glass-wrapper .form-group textarea:focus {
    outline: none;
    border-color: var(--contact-theme);
}

[data-theme="light"] #contact-glass-wrapper .form-group input:focus,
[data-theme="light"] #contact-glass-wrapper .form-group textarea:focus {
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

[data-theme="dark"] #contact-glass-wrapper .form-group input:focus,
[data-theme="dark"] #contact-glass-wrapper .form-group textarea:focus {
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.25);
}

#contact-glass-wrapper .form-group input::placeholder,
#contact-glass-wrapper .form-group textarea::placeholder {
    color: var(--htb-text-muted);
    opacity: 0.7;
}

#contact-glass-wrapper .form-group textarea {
    resize: vertical;
    min-height: 120px;
}

/* Submit Button */
#contact-glass-wrapper .submit-btn {
    width: 100%;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--contact-theme) 0%, var(--contact-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(2, 132, 199, 0.3);
    margin-top: 1.5rem;
}

#contact-glass-wrapper .submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(2, 132, 199, 0.4);
}

#contact-glass-wrapper .submit-btn:active {
    transform: translateY(0);
}

/* Info Column */
#contact-glass-wrapper .contact-info-column {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Info Card */
#contact-glass-wrapper .contact-info-card {
    padding: 1.5rem;
    border-radius: 16px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

#contact-glass-wrapper .contact-info-card:hover {
    transform: translateY(-3px);
}

/* Coordinator Card */
#contact-glass-wrapper .contact-info-card.coordinator {
    border-left: 4px solid var(--contact-theme);
}

[data-theme="light"] #contact-glass-wrapper .contact-info-card.coordinator {
    background: linear-gradient(135deg, rgba(2, 132, 199, 0.1) 0%, rgba(2, 132, 199, 0.03) 100%);
    border: 1px solid rgba(2, 132, 199, 0.2);
    border-left: 4px solid var(--contact-theme);
}

[data-theme="dark"] #contact-glass-wrapper .contact-info-card.coordinator {
    background: linear-gradient(135deg, rgba(2, 132, 199, 0.2) 0%, rgba(2, 132, 199, 0.05) 100%);
    border: 1px solid rgba(2, 132, 199, 0.3);
    border-left: 4px solid var(--contact-theme);
}

#contact-glass-wrapper .contact-info-card.coordinator h3 {
    color: var(--contact-theme);
}

[data-theme="dark"] #contact-glass-wrapper .contact-info-card.coordinator h3 {
    color: var(--contact-theme-light);
}

/* Address Card */
#contact-glass-wrapper .contact-info-card.address {
    border-left: 4px solid var(--contact-pink);
}

[data-theme="light"] #contact-glass-wrapper .contact-info-card.address {
    background: linear-gradient(135deg, rgba(219, 39, 119, 0.1) 0%, rgba(219, 39, 119, 0.03) 100%);
    border: 1px solid rgba(219, 39, 119, 0.2);
    border-left: 4px solid var(--contact-pink);
}

[data-theme="dark"] #contact-glass-wrapper .contact-info-card.address {
    background: linear-gradient(135deg, rgba(219, 39, 119, 0.2) 0%, rgba(219, 39, 119, 0.05) 100%);
    border: 1px solid rgba(219, 39, 119, 0.3);
    border-left: 4px solid var(--contact-pink);
}

#contact-glass-wrapper .contact-info-card.address h3 {
    color: var(--contact-pink);
}

/* Info Card Content */
#contact-glass-wrapper .contact-info-card h3 {
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

#contact-glass-wrapper .contact-info-card p {
    color: var(--htb-text-muted);
    margin: 0 0 0.5rem 0;
    line-height: 1.6;
    font-size: 0.95rem;
}

#contact-glass-wrapper .contact-info-card p:last-child {
    margin-bottom: 0;
}

#contact-glass-wrapper .contact-info-card strong {
    color: var(--htb-text-main);
}

#contact-glass-wrapper .contact-info-card a {
    color: var(--contact-theme);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
}

#contact-glass-wrapper .contact-info-card a:hover {
    color: var(--contact-theme-light);
    text-decoration: underline;
}

/* Quick Links */
#contact-glass-wrapper .quick-links {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

#contact-glass-wrapper .quick-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

[data-theme="light"] #contact-glass-wrapper .quick-link {
    background: rgba(255, 255, 255, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(2, 132, 199, 0.15);
}

[data-theme="dark"] #contact-glass-wrapper .quick-link {
    background: rgba(30, 41, 59, 0.6);
    color: var(--htb-text-main);
    border: 1px solid rgba(2, 132, 199, 0.2);
}

#contact-glass-wrapper .quick-link:hover {
    transform: translateY(-2px);
    border-color: var(--contact-theme);
}

[data-theme="light"] #contact-glass-wrapper .quick-link:hover {
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);
}

[data-theme="dark"] #contact-glass-wrapper .quick-link:hover {
    background: rgba(30, 41, 59, 0.8);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Footer Card */
#contact-glass-wrapper .contact-footer-card {
    text-align: center;
    padding: 1.25rem;
    border-radius: 12px;
}

[data-theme="light"] #contact-glass-wrapper .contact-footer-card {
    background: rgba(255, 255, 255, 0.5);
    border: 1px solid rgba(2, 132, 199, 0.1);
}

[data-theme="dark"] #contact-glass-wrapper .contact-footer-card {
    background: rgba(30, 41, 59, 0.4);
    border: 1px solid rgba(2, 132, 199, 0.15);
}

#contact-glass-wrapper .contact-footer-card p {
    color: var(--htb-text-muted);
    font-size: 0.85rem;
    margin: 0;
    line-height: 1.6;
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    #contact-glass-wrapper {
        padding: 1.5rem 1rem 3rem;
    }

    #contact-glass-wrapper .contact-page-header h1 {
        font-size: 1.85rem;
    }

    #contact-glass-wrapper .contact-page-header p {
        font-size: 1rem;
    }

    #contact-glass-wrapper .contact-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    #contact-glass-wrapper .contact-form-card {
        padding: 1.5rem;
    }

    #contact-glass-wrapper .contact-form-card h2 {
        font-size: 1.35rem;
    }

    #contact-glass-wrapper .quick-links {
        grid-template-columns: 1fr;
    }

    @keyframes contactGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #contact-glass-wrapper .contact-form-card,
    [data-theme="light"] #contact-glass-wrapper .contact-info-card {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #contact-glass-wrapper .contact-form-card,
    [data-theme="dark"] #contact-glass-wrapper .contact-info-card {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

<div id="contact-glass-wrapper">
    <div class="contact-inner">

        <!-- Smart Welcome Hero Section -->
        <div class="nexus-welcome-hero">
            <h1 class="nexus-welcome-title">Contact Us</h1>
            <p class="nexus-welcome-subtitle">Whether you're looking to join, partner with us, or discuss a national strategy, we're here to help.</p>

            <div class="nexus-smart-buttons">
                <a href="mailto:jasper@hour-timebank.ie" class="nexus-smart-btn nexus-smart-btn-primary">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Email Us</span>
                </a>
                <a href="<?= $basePath ?>/faq" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-circle-question"></i>
                    <span>FAQ</span>
                </a>
                <a href="<?= $basePath ?>/about-story" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-book-open"></i>
                    <span>Our Story</span>
                </a>
                <a href="<?= $basePath ?>/register" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-rocket"></i>
                    <span>Join Now</span>
                </a>
            </div>
        </div>

        <!-- Contact Grid -->
        <div class="contact-grid">

            <!-- Form Card -->
            <div class="contact-form-card">
                <h2>✉️ Send Us a Message</h2>

                <?php if (isset($_GET['sent'])): ?>
                    <div class="success-message">
                        <span class="success-icon">✅</span>
                        <span><strong>Success!</strong> Your message has been sent. We'll get back to you soon.</span>
                    </div>
                <?php endif; ?>

                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact/send" method="POST">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" required placeholder="John Doe">
                    </div>

                    <div class="form-group">
                        <label for="email">Your Email</label>
                        <input type="email" id="email" name="email" required placeholder="john@example.com">
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" required placeholder="How can we help?">
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="5" required placeholder="Tell us more about your inquiry..."></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <span>📤</span> Send Message
                    </button>
                </form>
            </div>

            <!-- Info Column -->
            <div class="contact-info-column">

                <!-- Coordinator Card -->
                <div class="contact-info-card coordinator">
                    <h3>👤 Network Coordinator</h3>
                    <p><strong>Email:</strong> <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></p>
                    <p><strong>Phone:</strong> 083 451 3266</p>
                </div>

                <!-- Address Card -->
                <div class="contact-info-card address">
                    <h3>📍 Mailing Address</h3>
                    <p>
                        hOUR Timebank CLG<br>
                        21 Páirc Goodman,<br>
                        Skibbereen,<br>
                        Co. Cork<br>
                        P81 AK26
                    </p>
                </div>

                <!-- Quick Links -->
                <div class="quick-links">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/about-story" class="quick-link">
                        <span>📖</span> Our Story
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/faq" class="quick-link">
                        <span>❓</span> FAQ
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/partner" class="quick-link">
                        <span>🤝</span> Partner With Us
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="quick-link">
                        <span>🚀</span> Join Now
                    </a>
                </div>

                <!-- Footer Card -->
                <div class="contact-footer-card">
                    <p>&copy; 2025 hOUR Timebank CLG<br>Registered Charity Number 20162023</p>
                </div>

            </div>

        </div>

    </div>
</div>

<script>
// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to send your message.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.submit-btn, .nexus-smart-btn, .quick-link').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#0284c7';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#0284c7');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../../..' . '/layouts/modern/footer.php'; ?>
