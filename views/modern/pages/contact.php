<?php
/**
 * Contact Page - Modern Theme
 */
$pageTitle = 'Contact Us';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'This Community';
$tenantEmail = '';
$tSlug = '';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
    $tenantEmail = $t['contact_email'] ?? $t['email'] ?? '';
    $tSlug = $t['slug'] ?? '';
}
$isHourTimebank = ($tSlug === 'hour-timebank' || $tSlug === 'hour_timebank');
?>

<style>
#contact-wrapper {
    --contact-theme: #059669;
    --contact-theme-rgb: 5, 150, 105;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #contact-wrapper {
        padding-top: 120px;
    }
}

#contact-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
}

[data-theme="light"] #contact-wrapper::before {
    background: linear-gradient(135deg,
        rgba(5, 150, 105, 0.08) 0%,
        rgba(16, 185, 129, 0.08) 50%,
        rgba(5, 150, 105, 0.08) 100%);
}

[data-theme="dark"] #contact-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(5, 150, 105, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
}

#contact-wrapper .contact-inner {
    max-width: 1000px;
    margin: 0 auto;
}

#contact-wrapper .contact-header {
    text-align: center;
    margin-bottom: 3rem;
}

#contact-wrapper .contact-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
}

#contact-wrapper .contact-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    max-width: 600px;
    margin: 0 auto;
}

#contact-wrapper .contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

@media (max-width: 768px) {
    #contact-wrapper .contact-grid {
        grid-template-columns: 1fr;
    }
}

#contact-wrapper .contact-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
}

[data-theme="light"] #contact-wrapper .contact-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(5, 150, 105, 0.15);
    box-shadow: 0 8px 32px rgba(5, 150, 105, 0.1);
}

[data-theme="dark"] #contact-wrapper .contact-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(5, 150, 105, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#contact-wrapper .contact-card h2 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 1.5rem 0;
}

#contact-wrapper .form-group {
    margin-bottom: 1.25rem;
}

#contact-wrapper .form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--htb-text-main);
}

#contact-wrapper .form-group input,
#contact-wrapper .form-group select,
#contact-wrapper .form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.2s ease;
}

[data-theme="light"] #contact-wrapper .form-group input,
[data-theme="light"] #contact-wrapper .form-group select,
[data-theme="light"] #contact-wrapper .form-group textarea {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(5, 150, 105, 0.2);
    color: var(--htb-text-main);
}

[data-theme="dark"] #contact-wrapper .form-group input,
[data-theme="dark"] #contact-wrapper .form-group select,
[data-theme="dark"] #contact-wrapper .form-group textarea {
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(5, 150, 105, 0.3);
    color: var(--htb-text-main);
}

#contact-wrapper .form-group input:focus,
#contact-wrapper .form-group select:focus,
#contact-wrapper .form-group textarea:focus {
    outline: none;
    border-color: var(--contact-theme);
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
}

#contact-wrapper .form-group textarea {
    resize: vertical;
    min-height: 120px;
}

#contact-wrapper .submit-btn {
    width: 100%;
    padding: 0.875rem 1.5rem;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    background: linear-gradient(135deg, var(--contact-theme) 0%, #047857 100%);
    color: white;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
}

#contact-wrapper .submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(5, 150, 105, 0.4);
}

#contact-wrapper .info-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

#contact-wrapper .info-item .info-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

[data-theme="light"] #contact-wrapper .info-item .info-icon {
    background: rgba(5, 150, 105, 0.1);
    color: var(--contact-theme);
}

[data-theme="dark"] #contact-wrapper .info-item .info-icon {
    background: rgba(5, 150, 105, 0.2);
    color: #34d399;
}

#contact-wrapper .info-item .info-content h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.25rem 0;
}

#contact-wrapper .info-item .info-content p {
    color: var(--htb-text-muted);
    margin: 0;
    line-height: 1.5;
}

@media (max-width: 768px) {
    #contact-wrapper .contact-header h1 {
        font-size: 2rem;
    }
}

#contact-wrapper .contact-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

#contact-wrapper .contact-alert-success {
    background: rgba(5, 150, 105, 0.15);
    border: 1px solid rgba(5, 150, 105, 0.3);
    color: #059669;
}

[data-theme="dark"] #contact-wrapper .contact-alert-success {
    background: rgba(5, 150, 105, 0.2);
    color: #34d399;
}

#contact-wrapper .contact-alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}

[data-theme="dark"] #contact-wrapper .contact-alert-error {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}
</style>

<?php
// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
$formData = $_SESSION['contact_form_data'] ?? [];
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['contact_form_data']);
?>

<div id="contact-wrapper">
    <div class="contact-inner">

        <div class="contact-header">
            <h1>Contact Us</h1>
            <p>We'd love to hear from you! Whether you have questions, feedback, or just want to say hello.</p>
        </div>

        <?php if ($flashSuccess): ?>
        <div class="contact-alert contact-alert-success">
            <i class="fa-solid fa-check-circle"></i>
            <span><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
        <div class="contact-alert contact-alert-error">
            <i class="fa-solid fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($flashError) ?></span>
        </div>
        <?php endif; ?>

        <div class="contact-grid">

            <div class="contact-card">
                <h2>Send a Message</h2>
                <form action="<?= $basePath ?>/contact/submit" method="POST">
                    <?php if (class_exists('\Nexus\Core\Csrf')): ?>
                        <?= \Nexus\Core\Csrf::input() ?>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" name="name" id="name" required
                               value="<?= htmlspecialchars($formData['name'] ?? $_SESSION['user_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" required
                               value="<?= htmlspecialchars($formData['email'] ?? $_SESSION['user_email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <?php $selectedSubject = $formData['subject'] ?? 'General Inquiry'; ?>
                        <select name="subject" id="subject">
                            <option value="General Inquiry" <?= $selectedSubject === 'General Inquiry' ? 'selected' : '' ?>>General Inquiry</option>
                            <option value="Support" <?= $selectedSubject === 'Support' ? 'selected' : '' ?>>Support</option>
                            <option value="Partnership" <?= $selectedSubject === 'Partnership' ? 'selected' : '' ?>>Partnership</option>
                            <option value="Feedback" <?= $selectedSubject === 'Feedback' ? 'selected' : '' ?>>Feedback</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea name="message" id="message" rows="5" required><?= htmlspecialchars($formData['message'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fa-solid fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>

            <div class="contact-card">
                <h2>Get in Touch</h2>

                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-envelope"></i></div>
                    <div class="info-content">
                        <h4>Email</h4>
                        <p><?= $tenantEmail ?: 'Contact us through the form' ?></p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-clock"></i></div>
                    <div class="info-content">
                        <h4>Response Time</h4>
                        <p>We typically respond within 24-48 hours</p>
                    </div>
                </div>

                <?php if ($isHourTimebank): ?>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-question-circle"></i></div>
                    <div class="info-content">
                        <h4>Need Help?</h4>
                        <p>Check our <a href="<?= $basePath ?>/faq" style="color: var(--contact-theme);">FAQ</a> for quick answers to common questions.</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-heart"></i></div>
                    <div class="info-content">
                        <h4>Community</h4>
                        <p>Join our community of members who share skills and support each other.</p>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
