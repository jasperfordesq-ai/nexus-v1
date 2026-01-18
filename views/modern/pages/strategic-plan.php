<?php
/**
 * Strategic Plan Page - Modern Theme
 * Strategic Plan 2026-2030
 */
$pageTitle = 'Strategic Plan 2026-2030';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

// Get tenant-specific PDF path if available
$pdfPath = '/uploads/tenants/hour-timebank/Timebank-Ireland-Strategic-Plan-2026-2030.pdf';
?>

<link rel="stylesheet" href="/assets/css/strategic-plan.css">

<div class="strategic-plan-wrapper">
    <div class="strategic-plan-inner">

        <!-- Header Section -->
        <div class="strategic-plan-header">
            <h1>Strategic Plan 2026 - 2030</h1>
            <div class="header-divider"></div>
            <p class="tagline">The Power of an Hour: Building a Resilient, Connected Ireland.</p>
            <a href="<?= htmlspecialchars($pdfPath) ?>" target="_blank" class="download-btn">
                <i class="fa-solid fa-file-pdf"></i>
                Download Official PDF
            </a>
        </div>

        <div class="strategic-plan-layout">

            <!-- Sidebar TOC -->
            <nav class="strategic-plan-toc">
                <h3>Contents</h3>
                <ul>
                    <li><a href="#executive-summary">1. Executive Summary</a></li>
                    <li><a href="#vision">2. Vision &amp; Mission</a></li>
                    <li><a href="#analysis">3. SWOT Analysis</a></li>
                    <li><a href="#pillars">4. Strategic Pillars</a></li>
                    <li><a href="#roadmap">5. Roadmap (Year 1)</a></li>
                    <li><a href="#risk">6. Risk &amp; Mitigation</a></li>
                </ul>
            </nav>

            <!-- Main Content -->
            <div class="strategic-plan-content">

                <!-- 1. Executive Summary -->
                <section id="executive-summary" class="strategic-plan-section">
                    <h2>1. Executive Summary</h2>
                    <p>This five-year strategic plan (2026-2030) outlines a clear and ambitious path for hOUR Timebank (TBI) to transition from a single, high-impact regional organisation into a resilient and scalable national network.</p>
                    <p>Our strategy is built on a foundation of proven, exceptional social value. A 2023 Social Impact Study quantified our SROI at <strong>1:16</strong>—for every €1 invested, €16 in tangible social value is returned.</p>

                    <h3>Two Primary Goals:</h3>
                    <ol>
                        <li><strong>Sustainable Growth:</strong> Scale from 245 to 2,500+ members by 2030, establishing a "Centre of Excellence" in West Cork.</li>
                        <li><strong>Maximising Social Impact:</strong> Deepen community value by investing in technology and diversifying our financial model.</li>
                    </ol>
                </section>

                <!-- 2. Vision -->
                <section id="vision" class="strategic-plan-section">
                    <h2>2. Our 2026 Vision</h2>

                    <div class="strategic-plan-highlight strategic-plan-highlight--mission">
                        <h3>Our Mission</h3>
                        <p>To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received.</p>
                    </div>

                    <div class="strategic-plan-highlight strategic-plan-highlight--vision">
                        <h3>Our Vision</h3>
                        <p>An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient communities.</p>
                    </div>
                </section>

                <!-- 3. SWOT -->
                <section id="analysis" class="strategic-plan-section">
                    <h2>3. SWOT Analysis</h2>

                    <div class="strategic-plan-swot">
                        <div class="strategic-plan-swot-item strategic-plan-swot-item--strengths">
                            <h4>Strengths</h4>
                            <ul>
                                <li><strong>Proven Impact:</strong> Independently validated 1:16 SROI.</li>
                                <li><strong>Partnerships:</strong> WCDP and Rethink Ireland.</li>
                                <li><strong>Lean Operations:</strong> Effective low-cost model.</li>
                            </ul>
                        </div>
                        <div class="strategic-plan-swot-item strategic-plan-swot-item--weaknesses">
                            <h4>Weaknesses</h4>
                            <ul>
                                <li><strong>Financial Instability:</strong> Funding gap after shop closure.</li>
                                <li><strong>Human Resources:</strong> Reliance on key individuals.</li>
                                <li><strong>No Physical Hub:</strong> Lack of central premises.</li>
                            </ul>
                        </div>
                        <div class="strategic-plan-swot-item strategic-plan-swot-item--opportunities">
                            <h4>Opportunities</h4>
                            <ul>
                                <li><strong>Public Sector Contracts:</strong> HSE Social Prescribing.</li>
                                <li><strong>Hybrid Models:</strong> B2B Timebanking.</li>
                                <li><strong>"Loneliness Epidemic":</strong> Powerful narrative.</li>
                            </ul>
                        </div>
                        <div class="strategic-plan-swot-item strategic-plan-swot-item--threats">
                            <h4>Threats</h4>
                            <ul>
                                <li><strong>Funding Cliff:</strong> Securing long-term coordinator funding.</li>
                                <li><strong>Volunteer Burnout:</strong> Unsustainable burden.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- 4. Pillars -->
                <section id="pillars" class="strategic-plan-section">
                    <h2>4. Strategic Pillars</h2>

                    <h3>Pillar 1: Roots &amp; Reach (Growth)</h3>
                    <table class="strategic-plan-table">
                        <thead>
                            <tr>
                                <th>Key Initiatives</th>
                                <th>Year 1 Priorities</th>
                                <th>KPIs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>1.1: West Cork Centre</strong></td>
                                <td>Secure funding for Hub Coordinator.</td>
                                <td>Monthly Hours > 200</td>
                            </tr>
                            <tr>
                                <td><strong>1.2: National Plan</strong></td>
                                <td>"Hub-in-a-Box" toolkit.</td>
                                <td>Toolkit completed.</td>
                            </tr>
                        </tbody>
                    </table>

                    <h3>Pillar 2: Financial Resilience</h3>
                    <table class="strategic-plan-table">
                        <thead>
                            <tr>
                                <th>Key Initiatives</th>
                                <th>Year 1 Priorities</th>
                                <th>KPIs</th>
                            </tr>
                        </thead>
                        <tbody>
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
                        </tbody>
                    </table>
                </section>

                <!-- 5. Roadmap -->
                <section id="roadmap" class="strategic-plan-section">
                    <h2>5. Roadmap: Year 1</h2>

                    <table class="strategic-plan-table">
                        <thead>
                            <tr>
                                <th>Initiative</th>
                                <th class="timeline-header">Q1</th>
                                <th class="timeline-header">Q2</th>
                                <th class="timeline-header">Q3</th>
                                <th class="timeline-header">Q4</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Fund Coordinator</strong></td>
                                <td class="timeline-cell"><span class="strategic-plan-badge strategic-plan-badge--submit">Submit</span></td>
                                <td class="timeline-cell"><span class="strategic-plan-badge strategic-plan-badge--secure">Secure</span></td>
                                <td class="timeline-cell"></td>
                                <td class="timeline-cell"></td>
                            </tr>
                            <tr>
                                <td><strong>Re-Engagement</strong></td>
                                <td class="timeline-cell"></td>
                                <td class="timeline-cell"></td>
                                <td class="timeline-cell"><span class="strategic-plan-badge strategic-plan-badge--launch">Launch</span></td>
                                <td class="timeline-cell"><span class="strategic-plan-badge strategic-plan-badge--ongoing">Ongoing</span></td>
                            </tr>
                            <tr>
                                <td><strong>Multi-Year Grants</strong></td>
                                <td class="timeline-cell"><span class="strategic-plan-badge strategic-plan-badge--submit">Submit</span></td>
                                <td class="timeline-cell"><span class="strategic-plan-badge strategic-plan-badge--pitch">Pitch</span></td>
                                <td class="timeline-cell"><span class="strategic-plan-badge strategic-plan-badge--secure">Secure</span></td>
                                <td class="timeline-cell"></td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <!-- 6. Risk & Mitigation -->
                <section id="risk" class="strategic-plan-section">
                    <h2>6. Risk &amp; Mitigation</h2>

                    <table class="strategic-plan-table">
                        <thead>
                            <tr>
                                <th>Risk</th>
                                <th>Impact</th>
                                <th>Mitigation Strategy</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Funding Gap</strong></td>
                                <td>High</td>
                                <td>Diversify funding sources; pursue multi-year grants; develop earned income streams.</td>
                            </tr>
                            <tr>
                                <td><strong>Key Person Dependency</strong></td>
                                <td>Medium</td>
                                <td>Document processes; cross-train volunteers; build succession planning.</td>
                            </tr>
                            <tr>
                                <td><strong>Volunteer Burnout</strong></td>
                                <td>Medium</td>
                                <td>Rotate responsibilities; recognize contributions; provide training and support.</td>
                            </tr>
                            <tr>
                                <td><strong>Technology Adoption</strong></td>
                                <td>Low</td>
                                <td>Phased rollout; user training; maintain offline alternatives.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
