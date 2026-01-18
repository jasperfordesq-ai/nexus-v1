<?php
// Fallback Register View (Unified)
// This file acts as a bridge to the consolidated layout-aware registration view.

require dirname(__DIR__) . '/modern/auth/register.php';
return;
?>

<article class="glass-panel" style="max-width: 500px; margin: 0 auto;">
    <header>
        <h1>Register</h1>
    </header>
    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" method="POST">
        <?= Nexus\Core\Csrf::input() ?>

        <!-- Bot Protection -->
        <input type="text" name="website" style="display:none !important;" tabindex="-1" autocomplete="off">
        <input type="hidden" name="registration_start" value="<?= time() ?>">

        <label for="first_name">First Name</label>
        <input type="text" id="first_name" name="first_name" placeholder="e.g. Alice" required autocomplete="given-name">

        <label for="last_name">Last Name (Admin Only)</label>
        <input type="text" id="last_name" name="last_name" placeholder="e.g. Smith" required autocomplete="family-name">

        <label for="profile_type">Profile Type</label>
        <select name="profile_type" id="profile_type" required onchange="toggleOrgField()">
            <option value="individual">Individual</option>
            <option value="organisation">Organisation</option>
        </select>

        <div id="org_field_container" style="display: none;">
            <label for="organization_name">Organisation Name</label>
            <input type="text" id="organization_name" name="organization_name" placeholder="e.g. Acme Corp" autocomplete="organization">
        </div>

        <script>
            function toggleOrgField() {
                const type = document.getElementById('profile_type').value;
                const container = document.getElementById('org_field_container');
                if (type === 'organisation') {
                    container.style.display = 'block';
                } else {
                    container.style.display = 'none';
                }
            }
        </script>

        <label for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="e.g. alice@example.com" required autocomplete="email">

        <label for="phone">Phone (Irish +353/08x)</label>
        <input type="tel" id="phone" name="phone" placeholder="e.g. 087 123 4567" autocomplete="tel">

        <label for="location">Location</label>
        <input type="text" id="location" name="location" placeholder="City/Town (Ireland)" class="mapbox-location-input-v2" autocomplete="address-level2">
        <input type="hidden" name="location_lat">
        <input type="hidden" name="location_lng">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
        <small style="display:block; margin-top:5px; color:#666;">Must be 12+ chars, include Upper, Lower, Number, Symbol.</small>

        <button type="submit" style="margin-top:20px;">Create Account</button>
    </form>
    <footer>
        <small>Already have an account? <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login">Login here</a></small>
    </footer>
</article>

<?php  ?>