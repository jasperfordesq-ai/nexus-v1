<?php
// Partial: Social Login Buttons
// Checks if social login is enabled in env/config
$config = json_decode(\Nexus\Core\TenantContext::get()['configuration'] ?? '{}', true);
$socialEnabled = !empty($config['social_login']['enabled']) && $config['social_login']['enabled'];
?>

<?php if ($socialEnabled): ?>
    <div class="social-login-separator" style="display: flex; align-items: center; margin: 20px 0; color: #94a3b8; font-size: 0.85rem;">
        <span style="flex: 1; height: 1px; background: #e2e8f0;"></span>
        <span style="padding: 0 10px;">or continue with</span>
        <span style="flex: 1; height: 1px; background: #e2e8f0;"></span>
    </div>

    <div class="social-login-buttons" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <?php if (!empty($config['social_login']['providers']['google']['client_id'])): ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login/oauth/google" class="nexus-btn nexus-btn-social" style="background: white; border: 1px solid #e2e8f0; color: #475569; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.2s;">
                <i class="fab fa-google" style="color: #DB4437; font-size: 20px;"></i>
                <span>Google</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($config['social_login']['providers']['facebook']['client_id'])): ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login/oauth/facebook" class="nexus-btn nexus-btn-social" style="background: white; border: 1px solid #e2e8f0; color: #475569; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.2s;">
                <i class="fab fa-facebook-f" style="color: #1877f2; font-size: 20px;"></i>
                <span>Facebook</span>
            </a>
        <?php endif; ?>
    </div>

    <style>
        .nexus-btn-social:hover {
            background-color: #f8fafc !important;
            border-color: #cbd5e1 !important;
            transform: translateY(-1px);
        }
    </style>
<?php endif; ?>