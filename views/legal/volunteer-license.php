<?php
$hero_title = "Volunteer Module Agreement";
$hero_subtitle = "Terms and conditions for registered organizations.";
$hero_gradient = 'htb-hero-gradient-purple';
$hero_type = 'Legal';


?>

<div class="htb-container" style="max-width: 900px; padding: 40px 20px; margin: 0 auto; width: 100%; float: none;">

    <div class="htb-card">
        <div class="htb-card-body" style="padding: 40px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; border-bottom: 2px solid #e5e7eb; padding-bottom: 20px;">
                <h2 style="margin: 0; font-family: 'Outfit', sans-serif; color: #111827;">Registered Organization License</h2>
                <button onclick="window.print()" class="htb-btn htb-btn-secondary" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-print"></i> Print Agreement
                </button>
            </div>

            <p style="font-size: 1.1rem; color: #4b5563; line-height: 1.6;">
                This document constitutes a binding agreement between <strong>Project NEXUS</strong> and the entity registering as a <strong>Volunteer Organization</strong>. By creating an organization profile, you acknowledge and agree to the following terms:
            </p>

            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 30px; margin: 30px 0;">
                <h4 style="color: #374151; margin-top: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-check-circle" style="color: #10b981;"></i>
                    Key Certifications
                </h4>
                <ul style="list-style: none; padding: 0; margin-top: 15px; font-size: 1.05rem; color: #374151;">
                    <li style="margin-bottom: 15px; padding-left: 30px; position: relative;">
                        <i class="fa-solid fa-caret-right" style="position: absolute; left: 0; top: 4px; color: #9ca3af;"></i>
                        You utilize this platform solely on behalf of a <strong>legitimate, registered non-profit, charity, or community group</strong> operating within Ireland.
                    </li>
                    <li style="margin-bottom: 15px; padding-left: 30px; position: relative;">
                        <i class="fa-solid fa-caret-right" style="position: absolute; left: 0; top: 4px; color: #9ca3af;"></i>
                        You possess a valid <strong>Registered Charity Number (RCN)</strong> or equivalent constitution where applicable, which may be requested for verification.
                    </li>
                    <li style="margin-bottom: 15px; padding-left: 30px; position: relative;">
                        <i class="fa-solid fa-caret-right" style="position: absolute; left: 0; top: 4px; color: #9ca3af;"></i>
                        You understand that this module is for <strong>professional volunteer recruitment</strong> purposes only.
                    </li>
                    <li style="margin-bottom: 0; padding-left: 30px; position: relative;">
                        <i class="fa-solid fa-caret-right" style="position: absolute; left: 0; top: 4px; color: #9ca3af;"></i>
                        <strong>Personal aid requests</strong> or solicitations for individual financial gain are strictly forbidden and will result in immediate account termination.
                    </li>
                </ul>
            </div>

            <p style="font-size: 0.95rem; color: #6b7280; text-align: center; margin-top: 40px; font-style: italic;">
                Last Revised: <?= date('F Y') ?> &nbsp;&bull;&nbsp; Project NEXUS Legal Department
            </p>
        </div>
    </div>

</div>

<?php  ?>