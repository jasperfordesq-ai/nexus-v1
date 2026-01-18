<?php
// CivicOne View: Our Story
$pageTitle = 'Our Story';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<style>
/* ========================================
   OUR STORY - GLASSMORPHISM 2025
   Theme: Amber/Gold (#f59e0b)
   ======================================== */

#story-glass-wrapper {
    --story-theme: #f59e0b;
    --story-theme-rgb: 245, 158, 11;
    --glass-bg: rgba(255, 255, 255, 0.25);
    --glass-border: rgba(255, 255, 255, 0.3);
    --glass-shadow: rgba(245, 158, 11, 0.15);
    min-height: 100vh;
    padding: 0;
    margin: -2rem -1rem;
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 25%, #fcd34d 50%, #f59e0b 75%, #d97706 100%);
    background-size: 400% 400%;
    animation: storyGradientShift 15s ease infinite;
}

@keyframes storyGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

#story-glass-wrapper .story-inner {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1.5rem 4rem;
}

/* Page Header */
#story-glass-wrapper .story-page-header {
    text-align: center;
    margin-bottom: 2.5rem;
    padding-top: 1rem;
}

#story-glass-wrapper .story-page-header h1 {
    font-size: 2.75rem;
    font-weight: 800;
    color: white;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#story-glass-wrapper .story-page-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.15rem;
    margin: 0;
}

/* Timeline Line */
#story-glass-wrapper .story-timeline {
    position: relative;
    padding-left: 40px;
}

#story-glass-wrapper .story-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.6) 0%, rgba(255, 255, 255, 0.2) 100%);
    border-radius: 3px;
}

/* Glass Card */
#story-glass-wrapper .story-glass-card {
    position: relative;
    background: var(--glass-bg);
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    box-shadow:
        0 8px 32px var(--glass-shadow),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    overflow: hidden;
    margin-bottom: 2rem;
    transition: all 0.3s ease;
}

#story-glass-wrapper .story-glass-card:hover {
    transform: translateY(-4px) translateX(4px);
    box-shadow:
        0 16px 48px var(--glass-shadow),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

/* Timeline Dot */
#story-glass-wrapper .story-glass-card::before {
    content: '';
    position: absolute;
    left: -33px;
    top: 28px;
    width: 16px;
    height: 16px;
    background: white;
    border: 3px solid var(--story-theme);
    border-radius: 50%;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    z-index: 2;
}

/* Card Header */
#story-glass-wrapper .card-header {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.1) 100%);
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    gap: 12px;
}

#story-glass-wrapper .card-header .icon {
    font-size: 1.5rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.15));
}

#story-glass-wrapper .card-header h2 {
    color: white;
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}

/* Card Body */
#story-glass-wrapper .card-body {
    padding: 1.5rem;
}

#story-glass-wrapper .card-body p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.05rem;
    line-height: 1.7;
    margin: 0 0 1rem 0;
}

#story-glass-wrapper .card-body p:last-child {
    margin-bottom: 0;
}

/* Highlight Quote */
#story-glass-wrapper .story-quote {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
    border-left: 4px solid white;
    padding: 1.25rem 1.5rem;
    margin: 1.5rem 0;
    border-radius: 0 12px 12px 0;
}

#story-glass-wrapper .story-quote p {
    font-style: italic;
    font-size: 1.1rem;
    color: white;
    margin: 0;
}

/* Stats Bar */
#story-glass-wrapper .stats-bar {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

#story-glass-wrapper .stat-chip {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 16px;
    padding: 1.25rem 1rem;
    text-align: center;
    transition: all 0.3s ease;
}

#story-glass-wrapper .stat-chip:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.25);
}

#story-glass-wrapper .stat-chip .stat-icon {
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.15));
}

#story-glass-wrapper .stat-chip .stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: white;
    display: block;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

#story-glass-wrapper .stat-chip .stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.85);
    font-weight: 600;
}

/* Mission Card - Special */
#story-glass-wrapper .mission-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0.15) 100%);
    text-align: center;
    padding: 2.5rem;
}

#story-glass-wrapper .mission-card::before {
    display: none;
}

#story-glass-wrapper .mission-card h3 {
    color: white;
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

#story-glass-wrapper .mission-card p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.15rem;
    line-height: 1.7;
    margin: 0;
    max-width: 600px;
    margin: 0 auto;
}

/* ========================================
   DARK MODE
   ======================================== */
[data-theme="dark"] #story-glass-wrapper {
    --glass-bg: rgba(15, 23, 42, 0.6);
    --glass-border: rgba(255, 255, 255, 0.1);
    --glass-shadow: rgba(0, 0, 0, 0.3);
    background: linear-gradient(135deg, #92400e 0%, #b45309 25%, #d97706 50%, #f59e0b 75%, #b45309 100%);
    background-size: 400% 400%;
}

[data-theme="dark"] #story-glass-wrapper .story-glass-card,
[data-theme="dark"] #story-glass-wrapper .stat-chip,
[data-theme="dark"] #story-glass-wrapper .mission-card {
    background: var(--glass-bg);
    border-color: var(--glass-border);
}

[data-theme="dark"] #story-glass-wrapper .story-timeline::before {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0.1) 100%);
}

[data-theme="dark"] #story-glass-wrapper .story-glass-card::before {
    background: var(--story-theme);
    border-color: #fbbf24;
}

[data-theme="dark"] #story-glass-wrapper .story-page-header h1,
[data-theme="dark"] #story-glass-wrapper .card-header h2,
[data-theme="dark"] #story-glass-wrapper .mission-card h3 {
    color: #fef3c7;
}

[data-theme="dark"] #story-glass-wrapper .story-page-header p,
[data-theme="dark"] #story-glass-wrapper .card-body p,
[data-theme="dark"] #story-glass-wrapper .mission-card p {
    color: rgba(254, 243, 199, 0.85);
}

[data-theme="dark"] #story-glass-wrapper .stat-chip .stat-value {
    color: #fef3c7;
}

[data-theme="dark"] #story-glass-wrapper .stat-chip .stat-label {
    color: rgba(254, 243, 199, 0.8);
}

[data-theme="dark"] #story-glass-wrapper .story-quote {
    background: rgba(255, 255, 255, 0.1);
    border-left-color: #fbbf24;
}

[data-theme="dark"] #story-glass-wrapper .story-quote p {
    color: #fef3c7;
}

[data-theme="dark"] #story-glass-wrapper .card-header {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    #story-glass-wrapper .story-inner {
        padding: 1.5rem 1rem 3rem;
    }

    #story-glass-wrapper .story-page-header h1 {
        font-size: 2rem;
    }

    #story-glass-wrapper .story-timeline {
        padding-left: 30px;
    }

    #story-glass-wrapper .story-timeline::before {
        left: 10px;
    }

    #story-glass-wrapper .story-glass-card::before {
        left: -28px;
        width: 14px;
        height: 14px;
    }

    #story-glass-wrapper .stats-bar {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    #story-glass-wrapper .stat-chip {
        display: flex;
        align-items: center;
        gap: 1rem;
        text-align: left;
        padding: 1rem 1.25rem;
    }

    #story-glass-wrapper .stat-chip .stat-icon {
        margin-bottom: 0;
        font-size: 1.5rem;
    }

    #story-glass-wrapper .stat-chip .stat-value {
        font-size: 1.25rem;
    }

    #story-glass-wrapper .mission-card {
        padding: 2rem 1.5rem;
    }

    @keyframes storyGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    #story-glass-wrapper .story-glass-card,
    #story-glass-wrapper .stat-chip,
    #story-glass-wrapper .mission-card {
        background: rgba(245, 158, 11, 0.85);
    }

    [data-theme="dark"] #story-glass-wrapper .story-glass-card,
    [data-theme="dark"] #story-glass-wrapper .stat-chip,
    [data-theme="dark"] #story-glass-wrapper .mission-card {
        background: rgba(15, 23, 42, 0.9);
    }
}
</style>

<div id="story-glass-wrapper">
    <div class="story-inner">

        <!-- Page Header -->
        <div class="story-page-header">
            <h1>📖 Our Story</h1>
            <p>The history of our community and how we came to be</p>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-chip">
                <div class="stat-icon">🗓️</div>
                <div>
                    <span class="stat-value">2015</span>
                    <span class="stat-label">Year Founded</span>
                </div>
            </div>
            <div class="stat-chip">
                <div class="stat-icon">👥</div>
                <div>
                    <span class="stat-value">500+</span>
                    <span class="stat-label">Active Members</span>
                </div>
            </div>
            <div class="stat-chip">
                <div class="stat-icon">⏱️</div>
                <div>
                    <span class="stat-value">10K+</span>
                    <span class="stat-label">Hours Exchanged</span>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="story-timeline">

            <!-- Origin Story -->
            <div class="story-glass-card">
                <div class="card-header">
                    <span class="icon">🌱</span>
                    <h2>From Humble Beginnings</h2>
                </div>
                <div class="card-body">
                    <p>Founded with the belief that community connection is the antidote to isolation, our Timebank has grown from a small group of neighbors to a thriving network.</p>
                    <p>We started with simple exchanges: a ride to the doctor, a loaf of homemade bread. Today, we facilitate thousands of hours of exchange every year.</p>
                </div>
            </div>

            <!-- Growth -->
            <div class="story-glass-card">
                <div class="card-header">
                    <span class="icon">🚀</span>
                    <h2>Growing Together</h2>
                </div>
                <div class="card-body">
                    <p>What began as a handful of neighbors helping each other has blossomed into a vibrant community of hundreds of members, each bringing unique skills and gifts to share.</p>
                    <div class="story-quote">
                        <p>"Every person has something valuable to offer, and everyone's time is worth the same."</p>
                    </div>
                    <p>This simple principle has guided us from the beginning and continues to shape everything we do.</p>
                </div>
            </div>

            <!-- Today -->
            <div class="story-glass-card">
                <div class="card-header">
                    <span class="icon">✨</span>
                    <h2>Where We Are Today</h2>
                </div>
                <div class="card-body">
                    <p>Our community continues to grow stronger every day. From tutoring and transportation to home repairs and companionship, we've built a network where everyone's contributions matter.</p>
                    <p>We're not just exchanging services — we're building relationships, fostering trust, and creating a more connected neighborhood for everyone.</p>
                </div>
            </div>

        </div>

        <!-- Mission Card -->
        <div class="story-glass-card mission-card">
            <h3>🎯 Our Mission</h3>
            <p>To strengthen our community by recognizing that everyone has gifts to share, everyone's contributions are valued equally, and together we can build a more connected and resilient neighborhood.</p>
        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
