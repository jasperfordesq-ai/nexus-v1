<?php
// CivicOne View: Strategic Plan
// Tenant-specific: Hour Timebank only
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Strategic Plan 2026-2030';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>
<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-report-pages.css?v=<?= time() ?>">

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card report-header">
        <h1 class="report-title">Strategic Plan 2026 - 2030</h1>
        <div class="report-divider"></div>
        <p class="report-subtitle">
            The Power of an Hour: Building a Resilient, Connected Ireland.
        </p>
        <div class="report-header-actions">
            <a href="/uploads/tenants/hour-timebank/Timebank-Ireland-Strategic-Plan-2026-2030.pdf" target="_blank" class="civic-btn report-download-btn">
                Download Official PDF
            </a>
        </div>
    </div>

    <div class="report-grid">

        <!-- Sidebar TOC -->
        <div class="civic-card report-toc">
            <h3 class="report-toc-title">Contents</h3>
            <ul class="report-toc-list">
                <li><a href="#executive-summary" class="report-toc-link">1. Executive Summary</a></li>
                <li><a href="#vision" class="report-toc-link">2. Vision & Mission</a></li>
                <li><a href="#analysis" class="report-toc-link">3. SWOT Analysis</a></li>
                <li><a href="#pillars" class="report-toc-link">4. Strategic Pillars</a></li>
                <li><a href="#roadmap" class="report-toc-link">5. Roadmap (Year 1)</a></li>
                <li><a href="#risk" class="report-toc-link">6. Risk & Mitigation</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div>

            <!-- 1. Executive Summary -->
            <div id="executive-summary" class="civic-card report-section">
                <h2 class="report-section-title">1. Executive Summary</h2>
                <p>This five-year strategic plan (2026-2030) outlines a clear and ambitious path for hOUR Timebank (TBI) to transition from a single, high-impact regional organisation into a resilient and scalable national network.</p>
                <p>Our strategy is built on a foundation of proven, exceptional social value. A 2023 Social Impact Study quantified our SROI at <strong>1:16</strong>—for every €1 invested, €16 in tangible social value is returned.</p>

                <h3 class="report-section-subtitle">Two Primary Goals:</h3>
                <ol class="report-goal-list">
                    <li class="report-goal-item"><strong>Sustainable Growth:</strong> Scale from 245 to 2,500+ members by 2030, establishing a "Centre of Excellence" in West Cork.</li>
                    <li><strong>Maximising Social Impact:</strong> Deepen community value by investing in technology and diversifying our financial model.</li>
                </ol>
            </div>

            <!-- 2. Vision -->
            <div id="vision" class="civic-card report-section">
                <h2 class="report-section-title">2. Our 2026 Vision</h2>

                <div class="report-mission-box">
                    <h3 class="report-box-title">Our Mission</h3>
                    <p class="report-box-text">To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received.</p>
                </div>

                <div class="report-vision-box">
                    <h3 class="report-box-title">Our Vision</h3>
                    <p class="report-box-text">An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient communities.</p>
                </div>
            </div>

            <!-- 3. SWOT -->
            <div id="analysis" class="civic-card report-section">
                <h2 class="report-section-title">3. SWOT Analysis</h2>

                <div class="report-swot-grid">
                    <div class="report-swot-box report-swot-box--strengths">
                        <h4 class="report-swot-title report-swot-title--strengths">Strengths</h4>
                        <ul class="report-swot-list">
                            <li><strong>Proven Impact:</strong> Independently validated 1:16 SROI.</li>
                            <li><strong>Partnerships:</strong> WCDP and Rethink Ireland.</li>
                            <li><strong>Lean Operations:</strong> Effective low-cost model.</li>
                        </ul>
                    </div>
                    <div class="report-swot-box report-swot-box--weaknesses">
                        <h4 class="report-swot-title report-swot-title--weaknesses">Weaknesses</h4>
                        <ul class="report-swot-list">
                            <li><strong>Financial Instability:</strong> Funding gap after shop closure.</li>
                            <li><strong>Human Resources:</strong> Reliance on key individuals.</li>
                            <li><strong>No Physical Hub:</strong> Lack of central premises.</li>
                        </ul>
                    </div>
                    <div class="report-swot-box report-swot-box--opportunities">
                        <h4 class="report-swot-title report-swot-title--opportunities">Opportunities</h4>
                        <ul class="report-swot-list">
                            <li><strong>Public Sector Contracts:</strong> HSE Social Prescribing.</li>
                            <li><strong>Hybrid Models:</strong> B2B Timebanking.</li>
                            <li><strong>"Loneliness Epidemic":</strong> Powerful narrative.</li>
                        </ul>
                    </div>
                    <div class="report-swot-box report-swot-box--threats">
                        <h4 class="report-swot-title report-swot-title--threats">Threats</h4>
                        <ul class="report-swot-list">
                            <li><strong>Funding Cliff:</strong> Securing long-term coordinator funding.</li>
                            <li><strong>Volunteer Burnout:</strong> Unsustainable burden.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 4. Pillars -->
            <div id="pillars" class="civic-card report-section">
                <h2 class="report-section-title">4. Strategic Pillars</h2>

                <h3 class="report-pillar-title">Pillar 1: Roots & Reach (Growth)</h3>
                <table class="report-table">
                    <tr>
                        <th>Key Initiatives</th>
                        <th>Year 1 Priorities</th>
                        <th>KPIs</th>
                    </tr>
                    <tr>
                        <td><strong>1.1: West Cork Centre</strong></td>
                        <td>Secure funding for Hub Coordinator.</td>
                        <td>Monthly Hours &gt; 200</td>
                    </tr>
                    <tr>
                        <td><strong>1.2: National Plan</strong></td>
                        <td>"Hub-in-a-Box" toolkit.</td>
                        <td>Toolkit completed.</td>
                    </tr>
                </table>

                <h3 class="report-pillar-title">Pillar 2: Financial Resilience</h3>
                <table class="report-table report-table--no-margin">
                    <tr>
                        <th>Key Initiatives</th>
                        <th>Year 1 Priorities</th>
                        <th>KPIs</th>
                    </tr>
                    <tr>
                        <td><strong>2.1: Core Funding</strong></td>
                        <td>Develop "Case for Support" (SROI).</td>
                        <td>Core costs funded 2026-28.</td>
                    </tr>
                    <tr>
                        <td><strong>2.2: Public Contracts</strong></td>
                        <td>Pilot in West Cork for Social Prescribing.</td>
                        <td>1x Pilot Contract.</td>
                    </tr>
                </table>
            </div>

            <!-- 5. Roadmap -->
            <div id="roadmap" class="civic-card report-section">
                <h2 class="report-section-title">5. Roadmap: Year 1</h2>

                <table class="report-table report-table--no-margin">
                    <thead>
                        <tr>
                            <th>Initiative</th>
                            <th class="text-center">Q1</th>
                            <th class="text-center">Q2</th>
                            <th class="text-center">Q3</th>
                            <th class="text-center">Q4</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="font-bold">Fund Coordinator</td>
                            <td class="text-center"><span class="report-badge report-badge--submit">SUBMIT</span></td>
                            <td class="text-center"><span class="report-badge report-badge--secure">SECURE</span></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                        </tr>
                        <tr>
                            <td class="font-bold">Re-Engagement</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"><span class="report-badge report-badge--launch">LAUNCH</span></td>
                            <td class="text-center"><span class="report-badge report-badge--ongoing">ONGOING</span></td>
                        </tr>
                        <tr>
                            <td class="font-bold">Multi-Year Grants</td>
                            <td class="text-center"><span class="report-badge report-badge--submit">SUBMIT</span></td>
                            <td class="text-center"><span class="report-badge report-badge--pitch">PITCH</span></td>
                            <td class="text-center"><span class="report-badge report-badge--secure">SECURE</span></td>
                            <td class="text-center"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
