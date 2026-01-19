<?php
// CivicOne View: Impact Report
// Tenant-specific: Hour Timebank only
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Social Impact Report';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 40px;">
        <h1 style="margin-bottom: 15px; font-size: 2.5rem; color: var(--skin-primary); text-transform: uppercase;">Social Impact Study</h1>
        <div style="width: 80px; height: 4px; background: var(--skin-primary); margin: 0 auto 20px;"></div>
        <p style="font-size: 1.3rem; margin: 0 auto; color: #555;">
            hOUR Timebank (Full Report)
        </p>
    </div>

    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 40px; align-items: start;">

        <!-- Sidebar TOC -->
        <div class="civic-card" style="position: sticky; top: 20px;">
            <h3 style="color: var(--skin-primary); margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee;">Table of Contents</h3>
            <ul style="list-style: none; padding: 0; margin: 0; line-height: 2;">
                <li><a href="#introduction" style="text-decoration: none; color: #555;">1. Introduction & Context</a></li>
                <li><a href="#literature" style="text-decoration: none; color: #555;">2. Literature Review</a></li>
                <li><a href="#activity" style="text-decoration: none; color: #555;">3. TBI 2021-22 Activity</a></li>
                <li><a href="#impact" style="text-decoration: none; color: #555;">4. Impact & Demographics</a></li>
                <li><a href="#sroi" style="text-decoration: none; color: #555;">5. SROI Calculation</a></li>
                <li><a href="#discussion" style="text-decoration: none; color: #555;">6. Discussion & Learning</a></li>
                <li><a href="#recommendations" style="text-decoration: none; color: #555;">7. Recommendations</a></li>
                <li><a href="#references" style="text-decoration: none; color: #555;">8. Bibliography</a></li>
            </ul>

            <div style="margin-top: 30px; display: grid; gap: 15px;">
                <a href="/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Executive-Summary-Design-Version-Final.pdf" target="_blank" class="civic-btn" style="text-align: center; font-size: 0.9rem;">
                    Download Executive Summary
                </a>
                <a href="/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Final-Full-Report-May-23.pdf" target="_blank" class="civic-btn" style="text-align: center; background: #fff; color: #555; border: 1px solid #ddd; font-size: 0.9rem;">
                    Download Full PDF
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div>

            <!-- 1. Introduction -->
            <div id="introduction" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">1. Introduction and Context</h2>

                <h3 style="color: #333; margin-top: 30px;">1.1 Introduction</h3>
                <p>As part of their ongoing Social Inclusion & Community Activation Programme (SICAP) support for hOUR Timebank (TBI), West Cork Development Partnership (WCDP) commissioned this social impact study for the twelve-month period, November 1st, 2021, to October 31st, 2022.</p>

                <h3 style="color: #333; margin-top: 30px;">1.2 Objectives of the Study</h3>
                <p>The objectives of the study were to:</p>
                <ul style="line-height: 1.6;">
                    <li>Demonstrate the social impact of TBI through measuring the outcomes for its members including many vulnerable and hard to reach groups.</li>
                    <li>Capture how TBI engages with its participants in an effective and tangible way.</li>
                    <li>Tell the story of the journey of change experienced by TBI members.</li>
                    <li>Enhance the value proposition for TBI to strengthen applications to statutory and philanthropic funders.</li>
                    <li>Provide structure, direction and give confidence to those considering expanding and replicating the initiative in other areas.</li>
                    <li>Identify key learning and recommendations.</li>
                </ul>

                <h3 style="color: #333; margin-top: 30px;">1.4 hOUR Timebank (TBI)</h3>
                <p>TBI is a group of people who help and support each other by sharing services, skills, talents, and knowledge. Its vision is of an interconnected community where meaningful relationships strengthen resilience, solidarity, and prosperity. Members provide services voluntarily enabling them to give and receive time and no money is exchanged. Through this exchange, TBI appreciates the value of every member and recognises all have needs as well as gifts to share. It supports basic needs to be met that mitigates deprivation and stress resulting in a better quality of life and stronger connections among citizens.</p>

                <div style="background: #fdf2f8; border-left: 4px solid var(--skin-primary); padding: 20px; margin: 30px 0; border-radius: 0 8px 8px 0;">
                    <h4 style="margin-top: 0; color: var(--skin-primary);">Case Study 1: Monica</h4>
                    <p style="font-style: italic;">Monica found out about TBI through the outreach mental health team. She lives alone in a rural area with no family support... Since getting involved in TBI, Monica feels much more connected to the community.</p>
                    <p style="font-weight: bold; margin-bottom: 0;">"Contact is important for both giver and receiver... Giving is good for the soul and being in contact with new people is lovely."</p>
                </div>

                <h3 style="color: #333; margin-top: 30px;">1.5 Methodology</h3>
                <p>A mixed method approach was adopted for data collection, capturing both quantitative and qualitative data. This included:</p>
                <ul style="line-height: 1.6;">
                    <li>Web-based survey eliciting 30 responses from TBI members.</li>
                    <li>Semi-structured 1-1 interviews with a further ten TBI members.</li>
                    <li>Semi structured interviews with external stakeholders.</li>
                </ul>

                <div style="background: #fdf2f8; border-left: 4px solid var(--skin-primary); padding: 20px; margin: 30px 0; border-radius: 0 8px 8px 0;">
                    <h4 style="margin-top: 0; color: var(--skin-primary);">Case Study 2: John</h4>
                    <p style="font-style: italic;">John lives in a rural and remote area in West Cork. John receives support with transport through TBI... Through a Meitheal, John's cottage was painted, and some handyman jobs were completed.</p>
                    <p style="font-weight: bold; margin-bottom: 0;">"I think belonging to timebank has helped change my life... I recommend anyone should join timebank and offer a service."</p>
                </div>
            </div>

            <!-- 2. Literature -->
            <div id="literature" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">2. Literature Review</h2>

                <h3 style="color: #333; margin-top: 30px;">2.2 Timebanking in Ireland</h3>
                <p>In a 2020 study, Isaac Hurley found that out of fifty-seven Community Currencies (CC) established in Ireland between 2000 and 2020, only three were still operational. Hurley concludes that TBI has great potential and represented a "clear step up in evolution of Irish CCs towards more broadly appreciated and professional systems."</p>

                <h3 style="color: #333; margin-top: 30px;">2.3 Timebanking in the UK</h3>
                <p>Under the New Labour government (1997-2010), Timebanks (TBs) were viewed as a tool to address social exclusion. A 2014 Cambridge University evaluation found TBs were successful in investing in community capacity and supporting social capital.</p>

                <div style="background: #fdf2f8; border-left: 4px solid var(--skin-primary); padding: 20px; margin: 30px 0; border-radius: 0 8px 8px 0;">
                    <h4 style="margin-top: 0; color: var(--skin-primary);">Case Study 3: Brenda</h4>
                    <p style="font-style: italic; margin-bottom: 0;">Brenda summed up the impact of TBI as "crucial for settling back into life in West Cork after being away for 25 years."</p>
                </div>
            </div>

            <!-- 3. Activity -->
            <div id="activity" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">3. hOUR Timebank (TBI) 2021-22</h2>

                <h3 style="color: #333;">3.2 User Group Information</h3>
                <p>There was a significant increase in enabled users from 219 to 391 over the year. 95% of enabled users are resident in County Cork.</p>

                <h3 style="color: #333; margin-top: 30px;">3.3 Activity</h3>
                <p>The currency used is time credits in units of one hour. 2868 hours were exchanged via 797 transactions. By the end of October 2022, there was more than one million hours of time credits in the Community Treasure Chest.</p>

                <table style="width: 100%; border-collapse: collapse; margin: 30px 0; background: #fff;">
                    <tr style="background: var(--skin-primary); color: white;">
                        <th style="padding: 15px; text-align: left;">Metric</th>
                        <th style="padding: 15px; text-align: left;">Value</th>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 15px;">Gross Income</td>
                        <td style="padding: 15px; font-weight: bold;">1,941.10 Time Credits</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 15px;">Number of Incoming Transfers</td>
                        <td style="padding: 15px; font-weight: bold;">559</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 15px;">Number of Logins</td>
                        <td style="padding: 15px; font-weight: bold;">1,560</td>
                    </tr>
                    <tr>
                        <td style="padding: 15px;">Balance of Community Account</td>
                        <td style="padding: 15px; font-weight: bold;">1,007,748.95</td>
                    </tr>
                </table>

                <div style="background: #fdf2f8; border-left: 4px solid var(--skin-primary); padding: 20px; margin: 30px 0; border-radius: 0 8px 8px 0;">
                    <h4 style="margin-top: 0; color: var(--skin-primary);">Case Study 4: Delores</h4>
                    <p style="font-style: italic; margin-bottom: 0;">Delores had an accident and felt very isolated during Covid... The home clean enabled her to get a council grant for home improvements, and the whole experience helped improve her mental well-being.</p>
                </div>
            </div>

            <!-- 4. Impact -->
            <div id="impact" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">4. Impact</h2>
                <p>This section explores the impact for members, the primary TBI stakeholder. 40 members were consulted.</p>

                <h3 style="color: #333; margin-top: 30px;">Profile</h3>
                <p>Nearly 60% of respondents were aged 56 or older. This reflects the wider TBI membership, though a younger profile is emerging.</p>

                <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="margin-top: 0; text-align: center; margin-bottom: 20px;">Members Age Bands</h4>
                    <div style="margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                        <span>56 - 65</span> <span style="font-weight: bold;">37.5%</span>
                    </div>
                    <div style="background: #ddd; height: 10px; border-radius: 5px; margin-bottom: 15px; overflow: hidden;">
                        <div style="background: var(--skin-primary); width: 37.5%; height: 100%;"></div>
                    </div>

                    <div style="margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                        <span>66+</span> <span style="font-weight: bold;">20.0%</span>
                    </div>
                    <div style="background: #ddd; height: 10px; border-radius: 5px; margin-bottom: 15px; overflow: hidden;">
                        <div style="background: #06b6d4; width: 20%; height: 100%;"></div>
                    </div>
                    <div style="margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                        <span>36 - 45</span> <span style="font-weight: bold;">17.5%</span>
                    </div>
                    <div style="background: #ddd; height: 10px; border-radius: 5px; margin-bottom: 15px; overflow: hidden;">
                        <div style="background: #9333ea; width: 17.5%; height: 100%;"></div>
                    </div>
                </div>

                <h3 style="color: #333; margin-top: 30px;">Outcomes</h3>
                <p>95% indicated they felt more socially connected due to TBI.</p>
                <p>100% of respondents felt their well-being had improved.</p>

                <div style="background: #fdf2f8; border-left: 4px solid var(--skin-primary); padding: 20px; margin: 30px 0; border-radius: 0 8px 8px 0;">
                    <h4 style="margin-top: 0; color: var(--skin-primary);">Case Study 5: Elaine</h4>
                    <p style="font-style: italic; margin-bottom: 0;">Elaine is single with no family, lives remotely, and struggles with mental health issues and chronic pain. She describes TBI as "the most important support in life."</p>
                </div>
            </div>

            <!-- 5. SROI -->
            <div id="sroi" class="civic-card" style="margin-bottom: 40px; background: linear-gradient(135deg, var(--skin-primary), #4a044e); color: white;">
                <h2 style="color: white; margin-top: 0; border-bottom: 2px solid rgba(255,255,255,0.2); padding-bottom: 15px; margin-bottom: 20px;">5. Calculating the SROI</h2>
                <p>We estimated proxy costs for outcomes (e.g., improved health = cost of community counselling). The total input was €50,000.</p>

                <h3 style="color: rgba(255,255,255,0.9); margin-top: 30px;">5.4 SROI Results</h3>
                <div style="text-align: center; padding: 20px;">
                    <p style="font-size: 1.2rem; opacity: 0.9;">Social Return on Investment</p>
                    <div style="font-size: 5rem; font-weight: bold; line-height: 1;">€16</div>
                    <p style="font-size: 1.2rem; opacity: 0.9;">generated for every €1 invested.</p>
                    <p style="margin-top: 20px; font-style: italic; opacity: 0.8;">Based on a Total Present Value of €803,184 created against the input of €50,000.</p>
                </div>
            </div>

            <!-- 6. Discussion -->
            <div id="discussion" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">6. Discussion & Learning</h2>
                <p>Members with little disposable income get services they could not afford. We estimate that for every €1 invested, €16 is generated in social value. We believe this is a conservative valuation.</p>
                <p>TBI has shown a commitment to compliance, evidenced by its charitable status (CRA) and CLG registration.</p>
            </div>

            <!-- 7. Recommendations -->
            <div id="recommendations" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">7. Recommendations</h2>

                <h3 style="color: #333;">Sustainable Funding</h3>
                <p>We recommend TBI use the SROI findings (1:16) to approach funders. The initial ask should be to increase the Broker role.</p>

                <h3 style="color: #333; margin-top: 30px;">Increasing membership</h3>
                <p>The community account has over 1 million time credits; this is a great opportunity to increase membership. TBI should work with WCDP to identify impactful projects to use these credits.</p>
            </div>

            <!-- 8. References -->
            <div id="references" class="civic-card" style="margin-bottom: 40px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">8. Bibliography</h2>
                <ol style="line-height: 1.6; color: #555;">
                    <li>Bretherton, Joanne and Pleace, Nicholas (2014) An evaluation of the Broadway Skills Exchange Time Bank.</li>
                    <li>Burgess, G. (2014) Evaluation of the Cambridgeshire Timebanks.</li>
                    <li>Hurley, Isaac (2020) Uncovering Ireland's Monetary Ecology.</li>
                </ol>
            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>