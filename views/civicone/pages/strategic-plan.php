<?php
// CivicOne View: Strategic Plan
$pageTitle = 'Strategic Plan 2026-2030';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 40px;">
        <h1 style="margin-bottom: 15px; font-size: 2.5rem; color: var(--skin-primary); text-transform: uppercase;">Strategic Plan 2026 - 2030</h1>
        <div style="width: 80px; height: 4px; background: var(--skin-primary); margin: 0 auto 20px;"></div>
        <p style="font-size: 1.3rem; max-width: 800px; margin: 0 auto; color: #555; line-height: 1.6;">
            The Power of an Hour: Building a Resilient, Connected Ireland.
        </p>
        <div style="margin-top: 30px;">
            <a href="/uploads/tenants/hour-timebank/Timebank-Ireland-Strategic-Plan-2026-2030.pdf" target="_blank" class="civic-btn" style="background: white; border: 2px solid var(--skin-primary); color: var(--skin-primary);">
                Download Official PDF
            </a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 40px; align-items: start;">

        <!-- Sidebar TOC -->
        <div class="civic-card" style="position: sticky; top: 20px;">
            <h3 style="color: var(--skin-primary); margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee;">Contents</h3>
            <ul style="list-style: none; padding: 0; margin: 0; line-height: 2;">
                <li><a href="#executive-summary" style="text-decoration: none; color: #555;">1. Executive Summary</a></li>
                <li><a href="#vision" style="text-decoration: none; color: #555;">2. Vision & Mission</a></li>
                <li><a href="#analysis" style="text-decoration: none; color: #555;">3. SWOT Analysis</a></li>
                <li><a href="#pillars" style="text-decoration: none; color: #555;">4. Strategic Pillars</a></li>
                <li><a href="#roadmap" style="text-decoration: none; color: #555;">5. Roadmap (Year 1)</a></li>
                <li><a href="#risk" style="text-decoration: none; color: #555;">6. Risk & Mitigation</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div>

            <!-- 1. Executive Summary -->
            <div id="executive-summary" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">1. Executive Summary</h2>
                <p>This five-year strategic plan (2026-2030) outlines a clear and ambitious path for hOUR Timebank (TBI) to transition from a single, high-impact regional organisation into a resilient and scalable national network.</p>
                <p>Our strategy is built on a foundation of proven, exceptional social value. A 2023 Social Impact Study quantified our SROI at <strong>1:16</strong>—for every €1 invested, €16 in tangible social value is returned.</p>

                <h3 style="color: #333; margin-top: 30px;">Two Primary Goals:</h3>
                <ol style="line-height: 1.6;">
                    <li style="margin-bottom: 10px;"><strong>Sustainable Growth:</strong> Scale from 245 to 2,500+ members by 2030, establishing a "Centre of Excellence" in West Cork.</li>
                    <li><strong>Maximising Social Impact:</strong> Deepen community value by investing in technology and diversifying our financial model.</li>
                </ol>
            </div>

            <!-- 2. Vision -->
            <div id="vision" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">2. Our 2026 Vision</h2>

                <div style="background: #eef2ff; border: 1px solid #d1d5db; border-radius: 8px; padding: 25px; text-align: center; margin-bottom: 30px;">
                    <h3 style="color: var(--skin-primary); margin-top: 0;">Our Mission</h3>
                    <p style="font-style: italic; font-size: 1.1rem; color: #555; margin-bottom: 0;">To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received.</p>
                </div>

                <div style="background: #ecfeff; border: 1px solid #d1d5db; border-radius: 8px; padding: 25px; text-align: center;">
                    <h3 style="color: var(--skin-primary); margin-top: 0;">Our Vision</h3>
                    <p style="font-style: italic; font-size: 1.1rem; color: #555; margin-bottom: 0;">An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient communities.</p>
                </div>
            </div>

            <!-- 3. SWOT -->
            <div id="analysis" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">3. SWOT Analysis</h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: #f0fdf4; padding: 20px; border-radius: 8px; border-top: 4px solid #10b981;">
                        <h4 style="margin-top: 0; color: #047857;">Strengths</h4>
                        <ul style="padding-left: 20px; font-size: 0.9rem;">
                            <li><strong>Proven Impact:</strong> Independently validated 1:16 SROI.</li>
                            <li><strong>Partnerships:</strong> WCDP and Rethink Ireland.</li>
                            <li><strong>Lean Operations:</strong> Effective low-cost model.</li>
                        </ul>
                    </div>
                    <div style="background: #fef2f2; padding: 20px; border-radius: 8px; border-top: 4px solid #ef4444;">
                        <h4 style="margin-top: 0; color: #b91c1c;">Weaknesses</h4>
                        <ul style="padding-left: 20px; font-size: 0.9rem;">
                            <li><strong>Financial Instability:</strong> Funding gap after shop closure.</li>
                            <li><strong>Human Resources:</strong> Reliance on key individuals.</li>
                            <li><strong>No Physical Hub:</strong> Lack of central premises.</li>
                        </ul>
                    </div>
                    <div style="background: #eff6ff; padding: 20px; border-radius: 8px; border-top: 4px solid #2563eb;">
                        <h4 style="margin-top: 0; color: #1d4ed8;">Opportunities</h4>
                        <ul style="padding-left: 20px; font-size: 0.9rem;">
                            <li><strong>Public Sector Contracts:</strong> HSE Social Prescribing.</li>
                            <li><strong>Hybrid Models:</strong> B2B Timebanking.</li>
                            <li><strong>"Loneliness Epidemic":</strong> Powerful narrative.</li>
                        </ul>
                    </div>
                    <div style="background: #fffbeb; padding: 20px; border-radius: 8px; border-top: 4px solid #f59e0b;">
                        <h4 style="margin-top: 0; color: #b45309;">Threats</h4>
                        <ul style="padding-left: 20px; font-size: 0.9rem;">
                            <li><strong>Funding Cliff:</strong> Securing long-term coordinator funding.</li>
                            <li><strong>Volunteer Burnout:</strong> Unsustainable burden.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 4. Pillars -->
            <div id="pillars" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">4. Strategic Pillars</h2>

                <h3 style="color: var(--skin-primary);">Pillar 1: Roots & Reach (Growth)</h3>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 10px; text-align: left;">Key Initiatives</th>
                        <th style="padding: 10px; text-align: left;">Year 1 Priorities</th>
                        <th style="padding: 10px; text-align: left;">KPIs</th>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;"><strong>1.1: West Cork Centre</strong></td>
                        <td style="padding: 10px;">Secure funding for Hub Coordinator.</td>
                        <td style="padding: 10px;">Monthly Hours > 200</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;"><strong>1.2: National Plan</strong></td>
                        <td style="padding: 10px;">"Hub-in-a-Box" toolkit.</td>
                        <td style="padding: 10px;">Toolkit completed.</td>
                    </tr>
                </table>

                <h3 style="color: var(--skin-primary);">Pillar 2: Financial Resilience</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 10px; text-align: left;">Key Initiatives</th>
                        <th style="padding: 10px; text-align: left;">Year 1 Priorities</th>
                        <th style="padding: 10px; text-align: left;">KPIs</th>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;"><strong>2.1: Core Funding</strong></td>
                        <td style="padding: 10px;">Develop "Case for Support" (SROI).</td>
                        <td style="padding: 10px;">Core costs funded 2026-28.</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;"><strong>2.2: Public Contracts</strong></td>
                        <td style="padding: 10px;">Pilot in West Cork for Social Prescribing.</td>
                        <td style="padding: 10px;">1x Pilot Contract.</td>
                    </tr>
                </table>
            </div>

            <!-- 5. Roadmap -->
            <div id="roadmap" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">5. Roadmap: Year 1</h2>

                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 10px; text-align: left;">Initiative</th>
                            <th style="padding: 10px; text-align: center;">Q1</th>
                            <th style="padding: 10px; text-align: center;">Q2</th>
                            <th style="padding: 10px; text-align: center;">Q3</th>
                            <th style="padding: 10px; text-align: center;">Q4</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; font-weight: bold;">Fund Coordinator</td>
                            <td style="padding: 10px; text-align: center;"><span style="background: #06b6d4; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold;">SUBMIT</span></td>
                            <td style="padding: 10px; text-align: center;"><span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold;">SECURE</span></td>
                            <td style="padding: 10px; text-align: center;"></td>
                            <td style="padding: 10px; text-align: center;"></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; font-weight: bold;">Re-Engagement</td>
                            <td style="padding: 10px; text-align: center;"></td>
                            <td style="padding: 10px; text-align: center;"></td>
                            <td style="padding: 10px; text-align: center;"><span style="background: #7c3aed; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold;">LAUNCH</span></td>
                            <td style="padding: 10px; text-align: center;"><span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold;">ONGOING</span></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; font-weight: bold;">Multi-Year Grants</td>
                            <td style="padding: 10px; text-align: center;"><span style="background: #06b6d4; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold;">SUBMIT</span></td>
                            <td style="padding: 10px; text-align: center;"><span style="background: #06b6d4; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold;">PITCH</span></td>
                            <td style="padding: 10px; text-align: center;"><span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold;">SECURE</span></td>
                            <td style="padding: 10px; text-align: center;"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>