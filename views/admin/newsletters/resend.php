<?php
/**
 * Resend to Non-Openers View
 */
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Hero settings for modern layout
$hTitle = 'Resend to Non-Openers';
$hSubtitle = 'Send a follow-up to recipients who didn\'t open: ' . htmlspecialchars($newsletter['subject'] ?? 'Newsletter');
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Newsletter';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}

$canResend = $resendInfo['can_resend'] ?? false;
$daysRemaining = $resendInfo['days_remaining'] ?? 0;
$nonOpenerCount = $resendInfo['non_opener_count'] ?? 0;
$nonOpenerPercent = $resendInfo['non_opener_percent'] ?? 0;
$daysSinceSent = $resendInfo['days_since_sent'] ?? 0;
$totalSent = $resendInfo['total_sent'] ?? 0;
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 700px; margin: 0 auto;">

    <!-- Navigation -->
    <div style="margin-bottom: 24px;">
        <a href="<?= $basePath ?>/admin-legacy/newsletters/stats/<?= $newsletter['id'] ?>" style="color: #6b7280; text-decoration: none; font-size: 0.9rem;">
            <i class="fa-solid fa-arrow-left"></i> Back to Analytics
        </a>
    </div>

    <!-- Original Newsletter Info -->
    <div class="nexus-card" style="margin-bottom: 24px;">
        <h3 style="margin: 0 0 15px 0; font-size: 1.1rem; color: #374151;">Original Newsletter</h3>
        <div style="background: #f9fafb; padding: 15px; border-radius: 8px;">
            <div style="font-weight: 600; margin-bottom: 5px;"><?= htmlspecialchars($newsletter['subject']) ?></div>
            <div style="color: #6b7280; font-size: 0.9rem;">
                Sent on <?= date('F j, Y \a\t g:i A', strtotime($newsletter['sent_at'])) ?>
            </div>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="nexus-card" style="margin-bottom: 24px;">
        <h3 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #374151;">Engagement Summary</h3>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
            <div style="text-align: center; padding: 20px; background: #f0fdf4; border-radius: 10px;">
                <div style="font-size: 2rem; font-weight: 700; color: #16a34a;"><?= number_format($totalSent) ?></div>
                <div style="color: #6b7280; font-size: 0.85rem;">Total Sent</div>
            </div>
            <div style="text-align: center; padding: 20px; background: #fef3c7; border-radius: 10px;">
                <div style="font-size: 2rem; font-weight: 700; color: #d97706;"><?= number_format($nonOpenerCount) ?></div>
                <div style="color: #6b7280; font-size: 0.85rem;">Did Not Open</div>
            </div>
            <div style="text-align: center; padding: 20px; background: #fee2e2; border-radius: 10px;">
                <div style="font-size: 2rem; font-weight: 700; color: #dc2626;"><?= $nonOpenerPercent ?>%</div>
                <div style="color: #6b7280; font-size: 0.85rem;">Non-Open Rate</div>
            </div>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.2rem;">
                <?= $canResend ? '<i class="fa-solid fa-check-circle" style="color: #16a34a;"></i>' : '<i class="fa-solid fa-clock" style="color: #d97706;"></i>' ?>
            </span>
            <div>
                <?php if ($canResend): ?>
                    <strong style="color: #16a34a;">Ready to resend!</strong>
                    <span style="color: #6b7280;"><?= $daysSinceSent ?> days have passed since the original send.</span>
                <?php else: ?>
                    <strong style="color: #d97706;">Please wait <?= $daysRemaining ?> more day<?= $daysRemaining !== 1 ? 's' : '' ?></strong>
                    <span style="color: #6b7280;">before resending to non-openers.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($canResend && $nonOpenerCount > 0): ?>
    <!-- Resend Form -->
    <div class="nexus-card">
        <h3 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #374151;">
            <i class="fa-solid fa-paper-plane" style="color: #6366f1;"></i> Send Follow-Up
        </h3>

        <div style="background: #eff6ff; color: #1e40af; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
            <strong>Tip:</strong> Consider using a different subject line to catch their attention.
            "Did you see this?" or "Reminder: [Original Subject]" often work well.
        </div>

        <form action="<?= $basePath ?>/admin-legacy/newsletters/resend/<?= $newsletter['id'] ?>" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">
                    New Subject Line
                </label>
                <input type="text"
                       name="subject"
                       value="Reminder: <?= htmlspecialchars($newsletter['subject']) ?>"
                       style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;"
                       required>
                <div style="color: #6b7280; font-size: 0.85rem; margin-top: 5px;">
                    This will be the subject line for the follow-up email
                </div>
            </div>

            <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="color: #6b7280;">Recipients:</span>
                    <strong><?= number_format($nonOpenerCount) ?> non-openers</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #6b7280;">Content:</span>
                    <span>Same as original newsletter</span>
                </div>
            </div>

            <div style="display: flex; gap: 15px;">
                <button type="submit"
                        style="flex: 1; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border: none; padding: 14px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem;">
                    <i class="fa-solid fa-paper-plane"></i>
                    Send to <?= number_format($nonOpenerCount) ?> Non-Openers
                </button>
                <a href="<?= $basePath ?>/admin-legacy/newsletters/stats/<?= $newsletter['id'] ?>"
                   style="padding: 14px 24px; background: #f3f4f6; color: #374151; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
    <?php elseif ($nonOpenerCount === 0): ?>
    <!-- No Non-Openers -->
    <div class="nexus-card" style="text-align: center; padding: 40px;">
        <div style="font-size: 3rem; margin-bottom: 15px;">
            <i class="fa-solid fa-face-smile-beam" style="color: #16a34a;"></i>
        </div>
        <h3 style="margin: 0 0 10px 0; color: #16a34a;">Great news!</h3>
        <p style="color: #6b7280; margin: 0;">
            Everyone who received this newsletter has opened it. No follow-up needed!
        </p>
    </div>
    <?php else: ?>
    <!-- Wait Period -->
    <div class="nexus-card" style="text-align: center; padding: 40px;">
        <div style="font-size: 3rem; margin-bottom: 15px;">
            <i class="fa-solid fa-hourglass-half" style="color: #d97706;"></i>
        </div>
        <h3 style="margin: 0 0 10px 0; color: #374151;">Waiting Period</h3>
        <p style="color: #6b7280; margin: 0 0 20px 0;">
            You can resend to <?= number_format($nonOpenerCount) ?> non-openers in <?= $daysRemaining ?> day<?= $daysRemaining !== 1 ? 's' : '' ?>.
            <br>This gives recipients enough time to open the original email.
        </p>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/stats/<?= $newsletter['id'] ?>"
           style="display: inline-block; padding: 12px 24px; background: #f3f4f6; color: #374151; border-radius: 8px; text-decoration: none; font-weight: 600;">
            <i class="fa-solid fa-arrow-left"></i> Back to Analytics
        </a>
    </div>
    <?php endif; ?>

    </div>
</div>

<style>
    .newsletter-admin-wrapper {
        position: relative;
        z-index: 20;
        padding: 0 40px 60px;
    }

    @media (min-width: 601px) {
        .newsletter-admin-wrapper {
            padding-top: 140px;
        }
    }

    @media (max-width: 600px) {
        .newsletter-admin-wrapper {
            padding: 120px 15px 100px 15px;
        }
    }
</style>

<?php
else {
    require __DIR__ . '/../../layouts/modern/footer.php';
}
?>
