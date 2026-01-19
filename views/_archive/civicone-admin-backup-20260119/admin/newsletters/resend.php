<?php
/**
 * Resend to Non-Openers View - Gold Standard Admin UI
 * Holographic Glassmorphism Dark Theme
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin page configuration
$adminPageTitle = 'Resend to Non-Openers';
$adminPageSubtitle = htmlspecialchars($newsletter['subject'] ?? 'Newsletter');
$adminPageIcon = 'fa-solid fa-rotate-right';

// Extract data
$canResend = $resendInfo['can_resend'] ?? false;
$daysRemaining = $resendInfo['days_remaining'] ?? 0;
$nonOpenerCount = $resendInfo['non_opener_count'] ?? 0;
$nonOpenerPercent = $resendInfo['non_opener_percent'] ?? 0;
$daysSinceSent = $resendInfo['days_since_sent'] ?? 0;
$totalSent = $resendInfo['total_sent'] ?? 0;

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<style>
    .resend-wrapper {
        padding: 0 40px 60px;
        position: relative;
        z-index: 10;
    }

    .resend-container {
        max-width: 700px;
        margin: 0 auto;
    }

    /* Back link */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.9rem;
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        color: #a5b4fc;
    }

    /* Glass Card */
    .glass-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        backdrop-filter: blur(20px);
        margin-bottom: 24px;
        padding: 24px;
    }

    .card-title {
        margin: 0 0 15px 0;
        font-size: 1.1rem;
        color: #ffffff;
        font-weight: 600;
    }

    .card-title i {
        margin-right: 8px;
        color: #a5b4fc;
    }

    /* Original Newsletter Box */
    .original-box {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 18px;
        border-radius: 10px;
    }

    .original-subject {
        font-weight: 600;
        margin-bottom: 5px;
        color: #ffffff;
    }

    .original-date {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.9rem;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }

    .stat-box {
        text-align: center;
        padding: 20px;
        border-radius: 12px;
    }

    .stat-box.green {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.15) 100%);
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .stat-box.amber {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.15) 100%);
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .stat-box.red {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.15) 100%);
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
    }

    .stat-value.green { color: #6ee7b7; }
    .stat-value.amber { color: #fcd34d; }
    .stat-value.red { color: #fca5a5; }

    .stat-label {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        margin-top: 4px;
    }

    /* Status Bar */
    .status-bar {
        margin-top: 20px;
        padding: 15px 18px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .status-bar.ready {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.1) 100%);
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .status-bar.wait {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.1) 100%);
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .status-icon {
        font-size: 1.3rem;
    }

    .status-icon.ready { color: #6ee7b7; }
    .status-icon.wait { color: #fcd34d; }

    .status-text strong.ready { color: #6ee7b7; }
    .status-text strong.wait { color: #fcd34d; }

    .status-text span {
        color: rgba(255, 255, 255, 0.6);
    }

    /* Tip Box */
    .tip-box {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(37, 99, 235, 0.1) 100%);
        border: 1px solid rgba(59, 130, 246, 0.3);
        color: #93c5fd;
        padding: 15px 18px;
        border-radius: 10px;
        margin-bottom: 24px;
        font-size: 0.9rem;
    }

    .tip-box strong {
        color: #60a5fa;
    }

    /* Form */
    .form-group {
        margin-bottom: 24px;
    }

    .form-label {
        display: block;
        margin-bottom: 10px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
    }

    .form-input {
        width: 100%;
        padding: 14px 16px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        color: #ffffff;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-input:focus {
        outline: none;
        border-color: rgba(99, 102, 241, 0.5);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }

    .form-input::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .form-hint {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        margin-top: 8px;
    }

    /* Summary Box */
    .summary-box {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 18px;
        border-radius: 10px;
        margin-bottom: 24px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
    }

    .summary-row:not(:last-child) {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .summary-label {
        color: rgba(255, 255, 255, 0.5);
    }

    .summary-value {
        color: #ffffff;
        font-weight: 500;
    }

    /* Buttons */
    .btn-group {
        display: flex;
        gap: 15px;
    }

    .btn-primary {
        flex: 1;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        border: none;
        padding: 14px 24px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        font-size: 1rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    }

    .btn-secondary {
        padding: 14px 24px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.8);
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }

    /* Empty/Status States */
    .state-card {
        text-align: center;
        padding: 50px 30px;
    }

    .state-icon {
        font-size: 3.5rem;
        margin-bottom: 20px;
    }

    .state-icon.success { color: #6ee7b7; }
    .state-icon.wait { color: #fcd34d; }

    .state-title {
        margin: 0 0 12px 0;
        font-size: 1.3rem;
    }

    .state-title.success { color: #6ee7b7; }
    .state-title.default { color: #ffffff; }

    .state-text {
        color: rgba(255, 255, 255, 0.5);
        margin: 0 0 24px 0;
        line-height: 1.6;
    }

    @media (max-width: 600px) {
        .resend-wrapper {
            padding: 0 20px 40px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .btn-group {
            flex-direction: column;
        }
    }
</style>

<div class="resend-wrapper">
    <div class="resend-container">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/admin/newsletters/stats/<?= $newsletter['id'] ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Analytics
        </a>

        <!-- Original Newsletter Info -->
        <div class="glass-card">
            <h3 class="card-title">Original Newsletter</h3>
            <div class="original-box">
                <div class="original-subject"><?= htmlspecialchars($newsletter['subject']) ?></div>
                <div class="original-date">
                    Sent on <?= date('F j, Y \a\t g:i A', strtotime($newsletter['sent_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Stats Summary -->
        <div class="glass-card">
            <h3 class="card-title">Engagement Summary</h3>

            <div class="stats-grid">
                <div class="stat-box green">
                    <div class="stat-value green"><?= number_format($totalSent) ?></div>
                    <div class="stat-label">Total Sent</div>
                </div>
                <div class="stat-box amber">
                    <div class="stat-value amber"><?= number_format($nonOpenerCount) ?></div>
                    <div class="stat-label">Did Not Open</div>
                </div>
                <div class="stat-box red">
                    <div class="stat-value red"><?= $nonOpenerPercent ?>%</div>
                    <div class="stat-label">Non-Open Rate</div>
                </div>
            </div>

            <div class="status-bar <?= $canResend ? 'ready' : 'wait' ?>">
                <span class="status-icon <?= $canResend ? 'ready' : 'wait' ?>">
                    <i class="fa-solid <?= $canResend ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                </span>
                <div class="status-text">
                    <?php if ($canResend): ?>
                        <strong class="ready">Ready to resend!</strong>
                        <span><?= $daysSinceSent ?> days have passed since the original send.</span>
                    <?php else: ?>
                        <strong class="wait">Please wait <?= $daysRemaining ?> more day<?= $daysRemaining !== 1 ? 's' : '' ?></strong>
                        <span>before resending to non-openers.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($canResend && $nonOpenerCount > 0): ?>
        <!-- Resend Form -->
        <div class="glass-card">
            <h3 class="card-title">
                <i class="fa-solid fa-paper-plane"></i> Send Follow-Up
            </h3>

            <div class="tip-box">
                <strong>Tip:</strong> Consider using a different subject line to catch their attention.
                "Did you see this?" or "Reminder: [Original Subject]" often work well.
            </div>

            <form action="<?= $basePath ?>/admin/newsletters/resend/<?= $newsletter['id'] ?>" method="POST">
                <?= Csrf::input() ?>

                <div class="form-group">
                    <label class="form-label">New Subject Line</label>
                    <input type="text"
                           name="subject"
                           class="form-input"
                           value="Reminder: <?= htmlspecialchars($newsletter['subject']) ?>"
                           required>
                    <div class="form-hint">
                        This will be the subject line for the follow-up email
                    </div>
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span class="summary-label">Recipients:</span>
                        <span class="summary-value"><?= number_format($nonOpenerCount) ?> non-openers</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Content:</span>
                        <span class="summary-value">Same as original newsletter</span>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        Send to <?= number_format($nonOpenerCount) ?> Non-Openers
                    </button>
                    <a href="<?= $basePath ?>/admin/newsletters/stats/<?= $newsletter['id'] ?>" class="btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <?php elseif ($nonOpenerCount === 0): ?>
        <!-- No Non-Openers -->
        <div class="glass-card state-card">
            <div class="state-icon success">
                <i class="fa-solid fa-face-smile-beam"></i>
            </div>
            <h3 class="state-title success">Great news!</h3>
            <p class="state-text">
                Everyone who received this newsletter has opened it. No follow-up needed!
            </p>
        </div>

        <?php else: ?>
        <!-- Wait Period -->
        <div class="glass-card state-card">
            <div class="state-icon wait">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <h3 class="state-title default">Waiting Period</h3>
            <p class="state-text">
                You can resend to <?= number_format($nonOpenerCount) ?> non-openers in <?= $daysRemaining ?> day<?= $daysRemaining !== 1 ? 's' : '' ?>.
                <br>This gives recipients enough time to open the original email.
            </p>
            <a href="<?= $basePath ?>/admin/newsletters/stats/<?= $newsletter['id'] ?>" class="btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back to Analytics
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
