<?php
// Mobile App Setup Page
$hTitle = 'Mobile App Setup';
$hSubtitle = 'Connect your device to the local development server';
$hGradient = 'htb-hero-gradient-orange';
$hType = 'Developer Tools';

// Determine Layout
$layout = layout(); // Fixed: centralized detection
$headerPath = dirname(__DIR__, 2) . "/views/layouts/$layout/header.php";
$footerPath = dirname(__DIR__, 2) . "/views/layouts/$layout/footer.php";

if (file_exists($headerPath)) require $headerPath;
else require dirname(__DIR__, 2) . '/views/layouts/default/header.php';
?>

<div class="container" style="padding: 40px 20px; max-width: 800px; margin: 0 auto;">

    <div class="htb-card" style="padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background: white;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="font-size: 2rem; margin-bottom: 10px;">📱 Connect Your Phone</h2>
            <p style="color: #666; font-size: 1.1rem;">Follow these steps to test the app on your physical device.</p>
        </div>

        <div style="display: grid; gap: 20px;">

            <!-- Step 1 -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #f97316;">
                <h3 style="margin: 0 0 10px 0; color: #f97316;">1. Ensure Network Access</h3>
                <p>Your phone must be on the same WiFi network as this computer.</p>
                <p><strong>Your Server IP:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">192.168.1.13</code></p>
            </div>

            <!-- Step 2 -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #f97316;">
                <h3 style="margin: 0 0 10px 0; color: #f97316;">2. Start the App Server</h3>
                <p>Open a terminal in your project's <code>mobile-app</code> folder and run:</p>
                <div style="background: #1e293b; color: #fff; padding: 15px; border-radius: 6px; font-family: monospace; overflow-x: auto;">
                    npx expo start
                </div>
            </div>

            <!-- Step 3 -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #f97316;">
                <h3 style="margin: 0 0 10px 0; color: #f97316;">3. Scan the code</h3>
                <p>A QR Code will appear in your <strong>terminal</strong> (not here on the website).</p>
                <ul>
                    <li><strong>Android:</strong> Open the "Expo Go" app and tap "Scan QR Code".</li>
                    <li><strong>iOS:</strong> Open the Camera app and scan the code.</li>
                </ul>
            </div>

        </div>

        <div style="margin-top: 30px; text-align: center; font-size: 0.9rem; color: #94a3b8;">
            <p>Note: Troubleshooting? Make sure Windows Firewall allows Node.js connections.</p>
        </div>
    </div>

</div>

<?php
if (file_exists($footerPath)) require $footerPath;
?>