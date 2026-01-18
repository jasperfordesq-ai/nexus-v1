<?php
// Phoenix View: Blog Show - Holographic Glassmorphism 2025
$pageTitle = $post['title'];
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Calculate reading time
$wordCount = str_word_count(strip_tags($post['content']));
$readingTime = max(1, ceil($wordCount / 200));
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<style>
/* ============================================
   GOLD STANDARD - Native App Features
   ============================================ */

/* Offline Banner */
.offline-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10001;
    padding: 12px 20px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transform: translateY(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.offline-banner.visible {
    transform: translateY(0);
}

/* Content Reveal Animation */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInScale {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}

#article-holo-wrapper .article-header {
    animation: fadeInUp 0.6s ease-out;
}

#article-holo-wrapper .article-content-card {
    animation: fadeInScale 0.5s ease-out 0.2s both;
}

#article-holo-wrapper .article-footer {
    animation: fadeInUp 0.4s ease-out 0.4s both;
}

/* Button Press States */
#article-holo-wrapper .article-btn:active,
#article-holo-wrapper .share-btn:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
#article-holo-wrapper .article-btn,
#article-holo-wrapper .share-btn {
    min-height: 44px;
}

/* Focus Visible */
#article-holo-wrapper .article-btn:focus-visible,
#article-holo-wrapper .share-btn:focus-visible,
#article-holo-wrapper a:focus-visible {
    outline: 3px solid rgba(139, 92, 246, 0.6);
    outline-offset: 3px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    #article-holo-wrapper .article-btn,
    #article-holo-wrapper .share-btn {
        min-height: 48px;
    }
}

/* ============================================
   HOLOGRAPHIC GLASSMORPHISM ARTICLE 2025
   Theme: Purple/Cyan Holographic (#8b5cf6 / #06b6d4)
   ============================================ */

#article-holo-wrapper {
    --article-primary: #8b5cf6;
    --article-primary-rgb: 139, 92, 246;
    --article-secondary: #06b6d4;
    --article-secondary-rgb: 6, 182, 212;
    --article-accent: #f472b6;
    --article-accent-rgb: 244, 114, 182;
    position: relative;
    min-height: 100vh;
    padding: 140px 20px 100px;
    overflow: hidden;
}

/* Animated Holographic Background */
#article-holo-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -2;
    background: linear-gradient(135deg,
        #0f0c29 0%,
        #302b63 50%,
        #24243e 100%);
}

/* Holographic Gradient Overlay */
#article-holo-wrapper::after {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background:
        radial-gradient(ellipse at 20% 0%, rgba(139, 92, 246, 0.3) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 0%, rgba(6, 182, 212, 0.25) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 100%, rgba(244, 114, 182, 0.2) 0%, transparent 50%);
    animation: holoShift 20s ease-in-out infinite alternate;
}

@keyframes holoShift {
    0% { opacity: 1; filter: hue-rotate(0deg); }
    50% { opacity: 0.85; filter: hue-rotate(15deg); }
    100% { opacity: 1; filter: hue-rotate(-15deg); }
}

/* Floating Holographic Orbs */
.holo-orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.4;
    pointer-events: none;
    z-index: -1;
    animation: orbFloat 25s ease-in-out infinite;
}

.holo-orb-1 {
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.6) 0%, transparent 70%);
    top: -150px;
    left: -150px;
}

.holo-orb-2 {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.5) 0%, transparent 70%);
    top: 40%;
    right: -100px;
    animation-delay: -5s;
}

.holo-orb-3 {
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(244, 114, 182, 0.4) 0%, transparent 70%);
    bottom: 10%;
    left: 20%;
    animation-delay: -10s;
}

@keyframes orbFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(30px, -30px) scale(1.05); }
    50% { transform: translate(-20px, 20px) scale(0.95); }
    75% { transform: translate(20px, 30px) scale(1.02); }
}

/* Inner Container */
#article-holo-wrapper .article-inner {
    max-width: 900px;
    margin: 0 auto;
    position: relative;
    z-index: 10;
}

/* ===== ARTICLE HEADER ===== */
.article-header {
    text-align: center;
    margin-bottom: 40px;
}

.article-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 50px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    margin-bottom: 30px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.article-back-link:hover {
    background: rgba(139, 92, 246, 0.2);
    border-color: rgba(139, 92, 246, 0.4);
    color: #c4b5fd;
    transform: translateX(-5px);
}

.article-back-link i {
    transition: transform 0.3s ease;
}

.article-back-link:hover i {
    transform: translateX(-4px);
}

.article-meta-top {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.article-date {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--article-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.article-reading-time {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

.article-author {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
}

.article-author-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--article-primary), var(--article-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.85rem;
}

.article-title {
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 900;
    line-height: 1.15;
    margin: 0 0 20px;
    background: linear-gradient(135deg, #ffffff 0%, #c4b5fd 50%, #67e8f9 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 0 80px rgba(139, 92, 246, 0.5);
}

/* ===== FEATURED IMAGE ===== */
.article-featured-image {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    margin-bottom: 40px;
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.5),
        0 0 40px rgba(139, 92, 246, 0.15);
}

.article-featured-image img {
    width: 100%;
    height: auto;
    display: block;
}

.article-featured-image::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 2px;
    background: linear-gradient(135deg,
        rgba(139, 92, 246, 0.5) 0%,
        rgba(6, 182, 212, 0.3) 50%,
        rgba(244, 114, 182, 0.5) 100%);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

/* ===== CONTENT CARD ===== */
.article-content-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    padding: 50px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    position: relative;
}

/* Holographic Border Glow */
.article-content-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 1px;
    background: linear-gradient(135deg,
        rgba(139, 92, 246, 0.3) 0%,
        rgba(6, 182, 212, 0.2) 50%,
        rgba(244, 114, 182, 0.3) 100%);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

/* ===== ARTICLE BODY TYPOGRAPHY ===== */
.article-body {
    font-size: 1.125rem;
    line-height: 1.85;
    color: rgba(255, 255, 255, 0.9);
}

.article-body h2 {
    font-size: 1.75rem;
    font-weight: 800;
    margin: 2.5rem 0 1rem;
    background: linear-gradient(135deg, #ffffff, #c4b5fd);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.article-body h3 {
    font-size: 1.35rem;
    font-weight: 700;
    color: #ffffff;
    margin: 2rem 0 0.75rem;
}

.article-body p {
    margin-bottom: 1.5rem;
}

.article-body strong {
    color: #ffffff;
    font-weight: 600;
}

.article-body a {
    color: var(--article-secondary);
    text-decoration: underline;
    text-decoration-color: rgba(6, 182, 212, 0.4);
    text-underline-offset: 3px;
    transition: all 0.2s ease;
}

.article-body a:hover {
    color: #67e8f9;
    text-decoration-color: #67e8f9;
}

.article-body ul,
.article-body ol {
    margin: 1.5rem 0;
    padding-left: 1.5rem;
}

.article-body li {
    margin-bottom: 0.75rem;
}

.article-body blockquote {
    position: relative;
    background: rgba(139, 92, 246, 0.1);
    border-left: 4px solid var(--article-primary);
    padding: 24px 30px;
    margin: 2rem 0;
    border-radius: 0 16px 16px 0;
    font-style: italic;
    color: rgba(255, 255, 255, 0.85);
}

.article-body blockquote::before {
    content: '"';
    position: absolute;
    top: 10px;
    left: 15px;
    font-size: 3rem;
    color: rgba(139, 92, 246, 0.3);
    font-family: Georgia, serif;
    line-height: 1;
}

.article-body img {
    max-width: 100%;
    border-radius: 16px;
    margin: 2rem 0;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
}

.article-body pre,
.article-body code {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 8px;
    font-family: 'Fira Code', 'Monaco', monospace;
}

.article-body code {
    padding: 3px 8px;
    font-size: 0.9em;
    color: #67e8f9;
}

.article-body pre {
    padding: 20px;
    overflow-x: auto;
    margin: 1.5rem 0;
}

.article-body pre code {
    padding: 0;
    background: none;
}

/* ===== ARTICLE FOOTER ===== */
.article-footer {
    margin-top: 50px;
    padding-top: 30px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.article-footer-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}

.article-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.article-btn:hover {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(6, 182, 212, 0.2));
    border-color: rgba(139, 92, 246, 0.4);
    transform: translateX(-5px);
}

.article-btn i {
    transition: transform 0.3s ease;
}

.article-btn:hover i {
    transform: translateX(-4px);
}

/* ===== SHARE BUTTONS ===== */
.share-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.share-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
}

.share-buttons {
    display: flex;
    gap: 10px;
}

.share-btn {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.share-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

.share-btn.facebook:hover {
    background: #1877f2;
    border-color: #1877f2;
    color: white;
}

.share-btn.twitter:hover {
    background: #000000;
    border-color: #000000;
    color: white;
}

.share-btn.linkedin:hover {
    background: #0a66c2;
    border-color: #0a66c2;
    color: white;
}

.share-btn.whatsapp:hover {
    background: #25d366;
    border-color: #25d366;
    color: white;
}

.share-btn.email:hover {
    background: linear-gradient(135deg, var(--article-primary), var(--article-secondary));
    border-color: var(--article-primary);
    color: white;
}

.share-btn.copy:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
    color: white;
}

.share-btn svg {
    width: 18px;
    height: 18px;
    fill: currentColor;
}

/* ===== LIGHT MODE ===== */
[data-theme="light"] #article-holo-wrapper::before {
    background: linear-gradient(135deg, #e0e7ff 0%, #ddd6fe 50%, #cffafe 100%);
}

[data-theme="light"] #article-holo-wrapper::after {
    background:
        radial-gradient(ellipse at 20% 0%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 0%, rgba(6, 182, 212, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 100%, rgba(244, 114, 182, 0.1) 0%, transparent 50%);
}

[data-theme="light"] .article-title {
    background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 50%, #0891b2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

[data-theme="light"] .article-back-link,
[data-theme="light"] .article-date,
[data-theme="light"] .article-reading-time,
[data-theme="light"] .article-author {
    color: rgba(0, 0, 0, 0.6);
}

[data-theme="light"] .article-content-card {
    background: rgba(255, 255, 255, 0.7);
    border-color: rgba(139, 92, 246, 0.15);
}

[data-theme="light"] .article-body {
    color: #1e1b4b;
}

[data-theme="light"] .article-body h2 {
    background: linear-gradient(135deg, #4c1d95, #7c3aed);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

[data-theme="light"] .article-body h3 {
    color: #1e1b4b;
}

[data-theme="light"] .article-body strong {
    color: #1e1b4b;
}

[data-theme="light"] .article-btn,
[data-theme="light"] .share-btn {
    background: rgba(255, 255, 255, 0.8);
    border-color: rgba(139, 92, 246, 0.2);
    color: #4c1d95;
}

[data-theme="light"] .share-label {
    color: rgba(0, 0, 0, 0.5);
}

[data-theme="light"] .holo-orb {
    opacity: 0.3;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    #article-holo-wrapper {
        padding: 120px 16px 60px;
    }

    .article-content-card {
        padding: 30px 20px;
        border-radius: 20px;
    }

    .article-body {
        font-size: 1rem;
    }

    .article-footer-inner {
        flex-direction: column;
        align-items: stretch;
        gap: 24px;
    }

    .share-section {
        justify-content: center;
    }

    .article-btn {
        justify-content: center;
    }

    .holo-orb-1,
    .holo-orb-2,
    .holo-orb-3 {
        opacity: 0.25;
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(20px)) {
    .article-content-card {
        background: rgba(30, 27, 75, 0.95);
    }

    [data-theme="light"] .article-content-card {
        background: rgba(255, 255, 255, 0.95);
    }
}
</style>

<div id="article-holo-wrapper">
    <!-- Holographic Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="article-inner">

        <!-- Article Header -->
        <header class="article-header">
            <a href="<?= $basePath ?>/blog" class="article-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                Back to News
            </a>

            <div class="article-meta-top">
                <span class="article-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?= date('F j, Y', strtotime($post['created_at'])) ?>
                </span>
                <span class="article-reading-time">
                    <i class="fa-regular fa-clock"></i>
                    <?= $readingTime ?> min read
                </span>
                <?php if (!empty($post['author_name'])): ?>
                    <span class="article-author">
                        <span class="article-author-avatar">
                            <?= strtoupper(substr($post['author_name'], 0, 1)) ?>
                        </span>
                        <?= htmlspecialchars($post['author_name']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <h1 class="article-title"><?= htmlspecialchars($post['title']) ?></h1>
        </header>

        <!-- Featured Image -->
        <?php if (!empty($post['featured_image'])): ?>
            <div class="article-featured-image">
                <img src="<?= htmlspecialchars($post['featured_image']) ?>" loading="lazy" alt="<?= htmlspecialchars($post['title']) ?>">
            </div>
        <?php endif; ?>

        <!-- Content Card -->
        <article class="article-content-card">
            <div class="article-body">
                <?= $post['content'] ?>
            </div>

            <!-- Article Footer -->
            <footer class="article-footer">
                <div class="article-footer-inner">
                    <a href="<?= $basePath ?>/blog" class="article-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to News
                    </a>

                    <?php
                    $shareUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                    $shareTitle = urlencode($post['title']);
                    $shareUrlEncoded = urlencode($shareUrl);
                    ?>

                    <div class="share-section">
                        <span class="share-label">Share:</span>
                        <div class="share-buttons">
                            <!-- Facebook -->
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEncoded ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="share-btn facebook" title="Share on Facebook">
                                <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>

                            <!-- Twitter/X -->
                            <a href="https://twitter.com/intent/tweet?text=<?= $shareTitle ?>&url=<?= $shareUrlEncoded ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="share-btn twitter" title="Share on X">
                                <svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>

                            <!-- LinkedIn -->
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrlEncoded ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="share-btn linkedin" title="Share on LinkedIn">
                                <svg viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            </a>

                            <!-- WhatsApp -->
                            <a href="https://api.whatsapp.com/send?text=<?= $shareTitle ?>%20<?= $shareUrlEncoded ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="share-btn whatsapp" title="Share on WhatsApp">
                                <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </a>

                            <!-- Email -->
                            <a href="mailto:?subject=<?= $shareTitle ?>&body=Check%20this%20out:%20<?= $shareUrlEncoded ?>"
                               class="share-btn email" title="Share via Email">
                                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                            </a>

                            <!-- Copy Link -->
                            <button class="share-btn copy" title="Copy link" id="copyLinkBtn">
                                <svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </footer>
        </article>

    </div>
</div>

<script>
// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Button Press States
document.querySelectorAll('#article-holo-wrapper .article-btn, #article-holo-wrapper .share-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Copy Link Functionality
document.getElementById('copyLinkBtn')?.addEventListener('click', function() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        const originalSVG = this.innerHTML;
        this.innerHTML = '<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
        this.style.background = '#22c55e';
        this.style.borderColor = '#22c55e';
        this.style.color = 'white';

        setTimeout(() => {
            this.innerHTML = originalSVG;
            this.style.background = '';
            this.style.borderColor = '';
            this.style.color = '';
        }, 2000);
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#302b63';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#302b63');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();

// Parallax effect on orbs
(function initParallaxOrbs() {
    const orbs = document.querySelectorAll('.holo-orb');
    if (orbs.length === 0) return;

    let ticking = false;

    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                const scrollY = window.scrollY;
                orbs.forEach((orb, index) => {
                    const speed = 0.03 * (index + 1);
                    orb.style.transform = `translateY(${scrollY * speed}px)`;
                });
                ticking = false;
            });
            ticking = true;
        }
    });
})();
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
