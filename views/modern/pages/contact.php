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
