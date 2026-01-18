<?php
// Phoenix View: Poll Detail - Holographic Glassmorphism 2025
// ISOLATED LAYOUT: Uses #poll-holo-wrapper and html[data-theme] selectors.

// Fetch Like/Comment Counts for Display
$pollId = $poll['id'];
$userId = $_SESSION['user_id'] ?? 0;
$likesCount = 0;
$commentsCount = 0;
$isLiked = false;

try {
    $pdo = \Nexus\Core\Database::getInstance();

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = 'poll' AND target_id = ?");
    $stmt->execute([$pollId]);
    $likesResult = $stmt->fetch();
    $likesCount = (int)($likesResult['cnt'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM comments WHERE target_type = 'poll' AND target_id = ?");
    $stmt->execute([$pollId]);
    $commentsResult = $stmt->fetch();
    $commentsCount = (int)($commentsResult['cnt'] ?? 0);

    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = 'poll' AND target_id = ?");
        $stmt->execute([$userId, $pollId]);
        $likedResult = $stmt->fetch();
        $isLiked = !empty($likedResult);
    }
} catch (\Throwable $e) {
    error_log("Poll stats error: " . $e->getMessage());
}

$pageTitle = $poll['question'];
$hideHero = true;
$basePath = \Nexus\Core\TenantContext::getBasePath();

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
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

/* Content Reveal Animations */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInScale {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}

#poll-holo-wrapper .poll-header-section {
    animation: fadeInUp 0.6s ease-out;
}

#poll-holo-wrapper .poll-content-card {
    animation: fadeInScale 0.5s ease-out 0.2s both;
}

#poll-holo-wrapper .poll-social-section {
    animation: fadeInUp 0.4s ease-out 0.4s both;
}

/* Button Press States */
#poll-holo-wrapper .poll-btn:active,
#poll-holo-wrapper .social-btn:active,
#poll-holo-wrapper .share-btn:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
#poll-holo-wrapper .poll-btn,
#poll-holo-wrapper .social-btn,
#poll-holo-wrapper .share-btn,
#poll-holo-wrapper .poll-option-label {
    min-height: 44px;
}

#poll-holo-wrapper input[type="text"] {
    font-size: 16px !important; /* Prevent iOS zoom */
}

/* Focus Visible */
#poll-holo-wrapper .poll-btn:focus-visible,
#poll-holo-wrapper .social-btn:focus-visible,
#poll-holo-wrapper .share-btn:focus-visible,
#poll-holo-wrapper a:focus-visible,
#poll-holo-wrapper input:focus-visible {
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
    #poll-holo-wrapper .poll-btn,
    #poll-holo-wrapper .social-btn,
    #poll-holo-wrapper .share-btn {
        min-height: 48px;
    }
}

/* ============================================
   HOLOGRAPHIC GLASSMORPHISM POLL 2025
   Theme: Purple/Cyan Holographic (#8b5cf6 / #06b6d4)
   ============================================ */

#poll-holo-wrapper {
    --poll-primary: #8b5cf6;
    --poll-primary-rgb: 139, 92, 246;
    --poll-secondary: #06b6d4;
    --poll-secondary-rgb: 6, 182, 212;
    --poll-accent: #f472b6;
    --poll-accent-rgb: 244, 114, 182;
    --poll-success: #22c55e;
    --poll-danger: #ef4444;
    position: relative;
    min-height: 100vh;
    padding: 140px 20px 100px;
    overflow: hidden;
}

/* Animated Holographic Background */
#poll-holo-wrapper::before {
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
#poll-holo-wrapper::after {
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
#poll-holo-wrapper .poll-inner {
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 10;
}

/* ===== POLL HEADER ===== */
.poll-header-section {
    text-align: center;
    margin-bottom: 40px;
}

.poll-back-link {
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

.poll-back-link:hover {
    background: rgba(139, 92, 246, 0.2);
    border-color: rgba(139, 92, 246, 0.4);
    color: #c4b5fd;
    transform: translateX(-5px);
}

.poll-back-link i {
    transition: transform 0.3s ease;
}

.poll-back-link:hover i {
    transform: translateX(-4px);
}

.poll-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(6, 182, 212, 0.15));
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 50px;
    color: var(--poll-secondary);
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 25px;
    backdrop-filter: blur(10px);
}

.poll-badge i {
    font-size: 1rem;
}

.poll-title {
    font-size: clamp(1.75rem, 4vw, 2.75rem);
    font-weight: 900;
    line-height: 1.2;
    margin: 0 0 20px;
    background: linear-gradient(135deg, #ffffff 0%, #c4b5fd 50%, #67e8f9 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 0 80px rgba(139, 92, 246, 0.5);
}

.poll-description {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.7);
    max-width: 600px;
    margin: 0 auto 25px;
    line-height: 1.6;
}

.poll-manage-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.poll-manage-btn:hover {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(6, 182, 212, 0.2));
    border-color: rgba(139, 92, 246, 0.4);
    transform: translateY(-2px);
}

/* ===== POLL CONTENT CARD ===== */
.poll-content-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    padding: 50px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    position: relative;
    margin-bottom: 30px;
}

/* Holographic Border Glow */
.poll-content-card::before {
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

/* ===== VOTING OPTIONS ===== */
.poll-options {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.poll-option-label {
    display: flex;
    align-items: center;
    padding: 20px 28px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    color: rgba(255, 255, 255, 0.9);
}

.poll-option-label::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(6, 182, 212, 0.05));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.poll-option-label:hover {
    border-color: rgba(139, 92, 246, 0.4);
    transform: translateX(8px);
}

.poll-option-label:hover::before {
    opacity: 1;
}

.poll-option-label input[type="radio"] {
    appearance: none;
    -webkit-appearance: none;
    width: 24px;
    height: 24px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    margin-right: 20px;
    display: grid;
    place-content: center;
    transition: 0.2s all;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.poll-option-label input[type="radio"]::before {
    content: "";
    width: 12px;
    height: 12px;
    border-radius: 50%;
    transform: scale(0);
    transition: 0.2s transform cubic-bezier(0.175, 0.885, 0.32, 1.275);
    background: #fff;
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
}

.poll-option-label input[type="radio"]:checked {
    border-color: var(--poll-primary);
    background: var(--poll-primary);
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.4);
}

.poll-option-label input[type="radio"]:checked::before {
    transform: scale(1);
}

.poll-option-label:has(input:checked) {
    border-color: rgba(139, 92, 246, 0.5);
    background: rgba(139, 92, 246, 0.1);
}

.option-text {
    font-size: 1.1rem;
    font-weight: 500;
    position: relative;
    z-index: 1;
}

/* ===== POLL RESULTS ===== */
.poll-results-header {
    text-align: center;
    margin-bottom: 35px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.2rem;
    font-weight: 400;
}

.poll-results-header i {
    font-size: 1.4rem;
    margin-left: 8px;
    color: var(--poll-secondary);
}

.result-item {
    margin-bottom: 24px;
}

.result-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.9);
}

.result-label {
    font-weight: 600;
}

.result-stats {
    font-weight: 500;
    color: var(--poll-secondary);
}

.progress-track {
    height: 16px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--poll-primary), var(--poll-secondary), var(--poll-accent));
    background-size: 200% 100%;
    animation: gradientShift 3s ease infinite;
    border-radius: 10px;
    position: relative;
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.4);
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.poll-total-votes {
    text-align: center;
    margin-top: 40px;
    padding-top: 25px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
}

.poll-total-votes strong {
    color: var(--poll-secondary);
    font-weight: 700;
}

/* ===== LOGIN PROMPT ===== */
.poll-login-prompt {
    text-align: center;
    padding: 50px 30px;
}

.poll-login-prompt p {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 25px;
}

/* ===== BUTTONS ===== */
.poll-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 18px 36px;
    background: linear-gradient(135deg, var(--poll-primary) 0%, #7c3aed 100%);
    border: none;
    border-radius: 16px;
    color: white;
    font-size: 1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
    transition: all 0.3s ease;
    text-decoration: none;
}

.poll-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(139, 92, 246, 0.5);
}

.poll-btn-block {
    display: flex;
    width: 100%;
    max-width: 400px;
    margin: 40px auto 0;
}

/* ===== SOCIAL SECTION ===== */
.poll-social-section {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    padding: 30px 40px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    position: relative;
}

.poll-social-section::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 1px;
    background: linear-gradient(135deg,
        rgba(139, 92, 246, 0.2) 0%,
        rgba(6, 182, 212, 0.15) 50%,
        rgba(244, 114, 182, 0.2) 100%);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

.social-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.social-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.social-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.social-btn.liked {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.social-btn.liked:hover {
    background: rgba(239, 68, 68, 0.25);
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

.share-btn.share-feed:hover {
    background: linear-gradient(135deg, var(--poll-primary), var(--poll-secondary));
    border-color: var(--poll-primary);
    color: white;
}

/* ===== COMMENTS SECTION ===== */
.comments-wrapper {
    display: none;
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.comments-wrapper.visible {
    display: block;
}

.comments-list {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 20px;
    padding-right: 10px;
}

.comments-list::-webkit-scrollbar {
    width: 6px;
}

.comments-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
}

.comments-list::-webkit-scrollbar-thumb {
    background: rgba(139, 92, 246, 0.3);
    border-radius: 3px;
}

.comment-item {
    padding: 16px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.comment-item:last-child {
    border-bottom: none;
}

.comment-header {
    display: flex;
    gap: 14px;
}

.comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(139, 92, 246, 0.3);
}

.comment-avatar-small {
    width: 32px;
    height: 32px;
}

.comment-body {
    flex: 1;
}

.comment-author {
    font-weight: 600;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 4px;
}

.comment-author-actions {
    display: inline-flex;
    gap: 8px;
    margin-left: 10px;
}

.comment-action-btn {
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.2s;
}

.comment-action-btn:hover {
    opacity: 1;
}

.comment-content {
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.5;
    margin-bottom: 8px;
}

.comment-meta {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.comment-reply-btn {
    color: var(--poll-secondary);
    cursor: pointer;
    font-weight: 600;
}

.comment-reply-btn:hover {
    text-decoration: underline;
}

.comment-reactions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.reaction-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.reaction-badge.active {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.4);
}

.reaction-badge:hover {
    background: rgba(255, 255, 255, 0.1);
}

.reaction-picker-toggle {
    padding: 4px 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.reaction-picker-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
}

.reaction-picker-dropdown {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 0;
    background: rgba(30, 27, 75, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 8px;
    z-index: 100;
    white-space: nowrap;
    backdrop-filter: blur(10px);
    margin-bottom: 8px;
}

.reaction-picker-dropdown.visible {
    display: block;
}

.reaction-picker-dropdown span {
    cursor: pointer;
    padding: 6px 8px;
    font-size: 1.3rem;
    border-radius: 8px;
    transition: background 0.2s;
}

.reaction-picker-dropdown span:hover {
    background: rgba(255, 255, 255, 0.1);
}

.reply-form {
    display: none;
    margin-top: 12px;
}

.reply-form.visible {
    display: flex;
    gap: 10px;
}

.comment-replies {
    margin-left: 54px;
    margin-top: 12px;
}

/* Comment Form */
.comment-form {
    display: flex;
    gap: 12px;
}

.comment-input {
    flex: 1;
    padding: 14px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.comment-input:focus {
    outline: none;
    border-color: rgba(139, 92, 246, 0.5);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.15);
}

.comment-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.comment-submit-btn {
    padding: 14px 24px;
    background: linear-gradient(135deg, var(--poll-primary), var(--poll-secondary));
    border: none;
    border-radius: 14px;
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.comment-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4);
}

.comment-login-prompt {
    text-align: center;
    color: rgba(255, 255, 255, 0.6);
}

.comment-login-prompt a {
    color: var(--poll-secondary);
    text-decoration: none;
    font-weight: 600;
}

.comment-login-prompt a:hover {
    text-decoration: underline;
}

.comments-empty {
    text-align: center;
    padding: 30px;
    color: rgba(255, 255, 255, 0.5);
}

/* ===== LIGHT MODE ===== */
[data-theme="light"] #poll-holo-wrapper::before {
    background: linear-gradient(135deg, #e0e7ff 0%, #ddd6fe 50%, #cffafe 100%);
}

[data-theme="light"] #poll-holo-wrapper::after {
    background:
        radial-gradient(ellipse at 20% 0%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 0%, rgba(6, 182, 212, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 100%, rgba(244, 114, 182, 0.1) 0%, transparent 50%);
}

[data-theme="light"] .holo-orb {
    opacity: 0.25;
}

[data-theme="light"] .poll-title {
    background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 50%, #0891b2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

[data-theme="light"] .poll-back-link,
[data-theme="light"] .poll-description {
    color: rgba(0, 0, 0, 0.6);
}

[data-theme="light"] .poll-back-link:hover {
    color: #7c3aed;
}

[data-theme="light"] .poll-badge {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(6, 182, 212, 0.1));
    border-color: rgba(139, 92, 246, 0.25);
}

[data-theme="light"] .poll-content-card,
[data-theme="light"] .poll-social-section {
    background: rgba(255, 255, 255, 0.7);
    border-color: rgba(139, 92, 246, 0.15);
}

[data-theme="light"] .poll-option-label {
    background: rgba(255, 255, 255, 0.5);
    border-color: rgba(139, 92, 246, 0.15);
    color: #1e1b4b;
}

[data-theme="light"] .poll-option-label:hover {
    border-color: rgba(139, 92, 246, 0.4);
    background: rgba(139, 92, 246, 0.05);
}

[data-theme="light"] .poll-option-label input[type="radio"] {
    border-color: rgba(139, 92, 246, 0.4);
}

[data-theme="light"] .poll-option-label:has(input:checked) {
    background: rgba(139, 92, 246, 0.1);
}

[data-theme="light"] .option-text {
    color: #1e1b4b;
}

[data-theme="light"] .result-header {
    color: #1e1b4b;
}

[data-theme="light"] .poll-results-header {
    color: #1e1b4b;
}

[data-theme="light"] .progress-track {
    background: rgba(139, 92, 246, 0.1);
    border-color: rgba(139, 92, 246, 0.15);
}

[data-theme="light"] .poll-total-votes {
    border-color: rgba(139, 92, 246, 0.1);
    color: rgba(0, 0, 0, 0.5);
}

[data-theme="light"] .poll-login-prompt p {
    color: #1e1b4b;
}

[data-theme="light"] .poll-manage-btn,
[data-theme="light"] .social-btn,
[data-theme="light"] .share-btn {
    background: rgba(255, 255, 255, 0.8);
    border-color: rgba(139, 92, 246, 0.2);
    color: #4c1d95;
}

[data-theme="light"] .social-btn.liked {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}

[data-theme="light"] .comment-input {
    background: rgba(255, 255, 255, 0.8);
    border-color: rgba(139, 92, 246, 0.2);
    color: #1e1b4b;
}

[data-theme="light"] .comment-input::placeholder {
    color: rgba(0, 0, 0, 0.4);
}

[data-theme="light"] .comment-author {
    color: #1e1b4b;
}

[data-theme="light"] .comment-content {
    color: #374151;
}

[data-theme="light"] .comment-meta {
    color: rgba(0, 0, 0, 0.5);
}

[data-theme="light"] .comments-wrapper {
    border-color: rgba(139, 92, 246, 0.1);
}

[data-theme="light"] .comment-item {
    border-color: rgba(139, 92, 246, 0.08);
}

[data-theme="light"] .reaction-badge,
[data-theme="light"] .reaction-picker-toggle {
    background: rgba(139, 92, 246, 0.08);
    border-color: rgba(139, 92, 246, 0.15);
}

[data-theme="light"] .reaction-picker-dropdown {
    background: rgba(255, 255, 255, 0.95);
    border-color: rgba(139, 92, 246, 0.2);
}

[data-theme="light"] .comments-empty,
[data-theme="light"] .comment-login-prompt {
    color: rgba(0, 0, 0, 0.5);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    #poll-holo-wrapper {
        padding: 120px 16px 60px;
    }

    .poll-content-card,
    .poll-social-section {
        padding: 30px 20px;
        border-radius: 20px;
    }

    .poll-title {
        font-size: 1.5rem;
    }

    .poll-option-label {
        padding: 16px 20px;
    }

    .option-text {
        font-size: 1rem;
    }

    .social-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .social-btn {
        justify-content: center;
    }

    .comment-form {
        flex-direction: column;
    }

    .comment-submit-btn {
        width: 100%;
    }

    .comment-replies {
        margin-left: 30px;
    }

    .holo-orb-1,
    .holo-orb-2,
    .holo-orb-3 {
        opacity: 0.2;
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(20px)) {
    .poll-content-card,
    .poll-social-section {
        background: rgba(30, 27, 75, 0.95);
    }

    [data-theme="light"] .poll-content-card,
    [data-theme="light"] .poll-social-section {
        background: rgba(255, 255, 255, 0.95);
    }
}
</style>

<div id="poll-holo-wrapper">
    <!-- Holographic Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="poll-inner">

        <!-- Poll Header -->
        <header class="poll-header-section">
            <a href="<?= $basePath ?>/polls" class="poll-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Polls
            </a>

            <div class="poll-badge">
                <i class="fa-solid fa-chart-bar"></i>
                Community Poll
            </div>

            <h1 class="poll-title"><?= htmlspecialchars($poll['question']) ?></h1>

            <?php if (!empty($poll['description'])): ?>
                <p class="poll-description"><?= nl2br(htmlspecialchars($poll['description'])) ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id']) && ($poll['user_id'] == $_SESSION['user_id'] || !empty($_SESSION['is_super_admin']))): ?>
                <a href="<?= $basePath ?>/polls/<?= $poll['id'] ?>/edit" class="poll-manage-btn">
                    <i class="fa-solid fa-gear"></i>
                    Manage Poll
                </a>
            <?php endif; ?>
        </header>

        <!-- Poll Content Card -->
        <div class="poll-content-card">
            <?php if ($hasVoted): ?>
                <!-- Results View -->
                <div class="poll-results">
                    <h3 class="poll-results-header">
                        Thank you for voting!
                        <i class="fa-solid fa-chart-pie"></i>
                    </h3>

                    <?php foreach ($options as $opt): ?>
                        <?php $percent = $totalVotes > 0 ? round(($opt['vote_count'] / $totalVotes) * 100) : 0; ?>
                        <div class="result-item">
                            <div class="result-header">
                                <span class="result-label"><?= htmlspecialchars($opt['label']) ?></span>
                                <span class="result-stats"><?= $percent ?>% (<?= $opt['vote_count'] ?>)</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: <?= $percent ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="poll-total-votes">
                        Total Votes: <strong><?= $totalVotes ?></strong>
                    </div>
                </div>

            <?php else: ?>
                <!-- Voting Form -->
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="poll-login-prompt">
                        <p>Join the community to cast your vote.</p>
                        <a href="<?= $basePath ?>/login" class="poll-btn">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            Login to Vote
                        </a>
                    </div>
                <?php else: ?>
                    <form action="<?= $basePath ?>/polls/vote" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">

                        <div class="poll-options">
                            <?php foreach ($options as $opt): ?>
                                <label class="poll-option-label">
                                    <input type="radio" name="option_id" value="<?= $opt['id'] ?>" required>
                                    <span class="option-text"><?= htmlspecialchars($opt['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="poll-btn poll-btn-block">
                            <i class="fa-solid fa-check-to-slot"></i>
                            Submit Vote
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Social Section -->
        <div class="poll-social-section">
            <div class="social-actions">
                <button id="likeBtn" onclick="pollToggleLike()" class="social-btn <?= $isLiked ? 'liked' : '' ?>">
                    <span id="likeIcon"><?= $isLiked ? '❤️' : '🤍' ?></span>
                    <span id="likesCount"><?= $likesCount ?></span> Likes
                </button>

                <button onclick="pollToggleComments()" class="social-btn">
                    <i class="fa-regular fa-comment"></i>
                    <span id="commentsCount"><?= $commentsCount ?></span> Comments
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <button onclick="shareToFeed()" class="share-btn share-feed" title="Share to Feed">
                        <i class="fa-solid fa-share-nodes"></i>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Comments Section -->
            <div id="commentsSection" class="comments-wrapper">
                <div id="commentsList" class="comments-list">
                    <div class="comments-empty">
                        <i class="fa-regular fa-comments"></i>
                        <p>Loading comments...</p>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <form id="commentForm" class="comment-form" onsubmit="pollSubmitComment(event)">
                        <input type="text" id="commentInput" class="comment-input" placeholder="Write a comment..." required>
                        <button type="submit" class="comment-submit-btn">Post</button>
                    </form>
                <?php else: ?>
                    <p class="comment-login-prompt">
                        <a href="<?= $basePath ?>/login">Login</a> to comment
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
const pollId = <?= $pollId ?>;
const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
let isLiked = <?= $isLiked ? 'true' : 'false' ?>;
let commentsLoaded = false;
let availableReactions = [];
const API_BASE = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/social';

// Toggle Like
async function pollToggleLike() {
    const btn = document.getElementById('likeBtn');
    const icon = document.getElementById('likeIcon');
    const countEl = document.getElementById('likesCount');

    try {
        const response = await fetch(API_BASE + '/like', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target_type: 'poll',
                target_id: pollId
            })
        });

        const data = await response.json();

        if (data.error) {
            if (data.redirect) window.location.href = data.redirect;
            else console.error(data.error);
            return;
        }

        isLiked = (data.status === 'liked');
        countEl.textContent = data.likes_count;
        icon.textContent = isLiked ? '❤️' : '🤍';
        btn.classList.toggle('liked', isLiked);

        // Haptic feedback
        if (navigator.vibrate) navigator.vibrate(50);
    } catch (err) {
        console.error('Like error:', err);
    }
}

// Toggle Comments
function pollToggleComments() {
    // Check if mobile (screen width <= 768px or touch device)
    const isMobile = window.innerWidth <= 768 || ('ontouchstart' in window);

    if (isMobile && typeof openMobileCommentSheet === 'function') {
        // Use mobile drawer on mobile devices
        openMobileCommentSheet('poll', pollId, '');
        return;
    }

    // Desktop: use inline comments section
    const section = document.getElementById('commentsSection');
    section.classList.toggle('visible');

    if (section.classList.contains('visible') && !commentsLoaded) {
        loadComments();
    }
}

// Load Comments
async function loadComments() {
    const list = document.getElementById('commentsList');

    try {
        const response = await fetch(API_BASE + '/comments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'fetch',
                target_type: 'poll',
                target_id: pollId
            })
        });

        const data = await response.json();

        if (data.error) {
            list.innerHTML = '<div class="comments-empty"><p>Failed to load comments</p></div>';
            return;
        }

        commentsLoaded = true;
        availableReactions = data.available_reactions || [];

        if (!data.comments || data.comments.length === 0) {
            list.innerHTML = '<div class="comments-empty"><i class="fa-regular fa-comments"></i><p>No comments yet. Be the first to comment!</p></div>';
            return;
        }

        list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');
    } catch (err) {
        console.error('Comments error:', err);
        list.innerHTML = '<div class="comments-empty"><p>Failed to load comments</p></div>';
    }
}

// Render Comment
function renderComment(c, depth) {
    const isEdited = c.is_edited ? '<span style="font-size: 0.75rem; opacity: 0.6;"> (edited)</span>' : '';
    const ownerActions = c.is_owner ? `
        <span class="comment-author-actions">
            <span class="comment-action-btn" onclick="pollEditComment(${c.id}, '${escapeHtml(c.content)}')" title="Edit">✏️</span>
            <span class="comment-action-btn" onclick="pollDeleteComment(${c.id})" title="Delete">🗑️</span>
        </span>
    ` : '';

    const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
        const isUserReaction = (c.user_reactions || []).includes(emoji);
        return `<span class="reaction-badge ${isUserReaction ? 'active' : ''}" onclick="pollToggleReaction(${c.id}, '${emoji}')">${emoji} ${count}</span>`;
    }).join('');

    const reactionPicker = isLoggedIn ? `
        <div style="position: relative; display: inline-block;">
            <span class="reaction-picker-toggle" onclick="pollShowReactionPicker(${c.id})">+</span>
            <div id="picker-${c.id}" class="reaction-picker-dropdown">
                ${availableReactions.map(e => `<span onclick="pollToggleReaction(${c.id}, '${e}')">${e}</span>`).join('')}
            </div>
        </div>
    ` : '';

    const replyButton = isLoggedIn ? `<span class="comment-reply-btn" onclick="pollShowReplyForm(${c.id})">Reply</span>` : '';

    const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

    const avatarClass = depth > 0 ? 'comment-avatar comment-avatar-small' : 'comment-avatar';

    return `
        <div class="comment-item" id="comment-${c.id}">
            <div class="comment-header">
                <img src="${c.author_avatar}" class="${avatarClass}" alt="" loading="lazy">
                <div class="comment-body">
                    <div class="comment-author">
                        ${escapeHtml(c.author_name)}${isEdited}${ownerActions}
                    </div>
                    <div class="comment-content" id="content-${c.id}">${formatContent(c.content)}</div>
                    <div class="comment-meta">
                        <span>${new Date(c.created_at).toLocaleString()}</span>
                        ${replyButton}
                    </div>
                    <div class="comment-reactions">
                        ${reactions}
                        ${reactionPicker}
                    </div>
                    <div id="reply-form-${c.id}" class="reply-form">
                        <input type="text" id="reply-input-${c.id}" class="comment-input" placeholder="Write a reply..." style="flex: 1;">
                        <button onclick="pollSubmitReply(${c.id})" class="comment-submit-btn">Reply</button>
                    </div>
                </div>
            </div>
            ${replies ? `<div class="comment-replies">${replies}</div>` : ''}
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatContent(content) {
    return escapeHtml(content).replace(/@(\w+)/g, '<span style="color: var(--poll-secondary); font-weight: 600;">@$1</span>');
}

function pollShowReactionPicker(commentId) {
    // Close all other pickers first
    document.querySelectorAll('.reaction-picker-dropdown.visible').forEach(p => p.classList.remove('visible'));
    const picker = document.getElementById(`picker-${commentId}`);
    picker.classList.toggle('visible');
}

function pollShowReplyForm(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    form.classList.toggle('visible');
    if (form.classList.contains('visible')) {
        document.getElementById(`reply-input-${commentId}`).focus();
    }
}

async function pollToggleReaction(commentId, emoji) {
    if (!isLoggedIn) { alert('Please log in to react'); return; }

    // Close picker
    document.querySelectorAll('.reaction-picker-dropdown.visible').forEach(p => p.classList.remove('visible'));

    try {
        const response = await fetch(API_BASE + '/reaction', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                comment_id: commentId,
                emoji: emoji
            })
        });
        const data = await response.json();
        if (data.error) { alert(data.error); return; }
        loadComments();
    } catch (err) {
        console.error('Reaction error:', err);
    }
}

async function pollSubmitReply(parentId) {
    const input = document.getElementById(`reply-input-${parentId}`);
    const content = input.value.trim();
    if (!content) return;

    try {
        const response = await fetch(API_BASE + '/reply', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target_type: 'poll',
                target_id: pollId,
                parent_id: parentId,
                content: content
            })
        });
        const data = await response.json();
        if (data.error) { alert(data.error); return; }
        input.value = '';
        document.getElementById(`reply-form-${parentId}`).classList.remove('visible');
        document.getElementById('commentsCount').textContent = parseInt(document.getElementById('commentsCount').textContent) + 1;
        loadComments();
    } catch (err) {
        console.error('Reply error:', err);
    }
}

async function pollDeleteComment(commentId) {
    if (!confirm('Delete this comment?')) return;

    try {
        const response = await fetch(API_BASE + '/delete-comment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                comment_id: commentId
            })
        });
        const data = await response.json();
        if (data.error) { alert(data.error); return; }
        document.getElementById('commentsCount').textContent = Math.max(0, parseInt(document.getElementById('commentsCount').textContent) - 1);
        loadComments();
    } catch (err) {
        console.error('Delete error:', err);
    }
}

function pollEditComment(commentId, currentContent) {
    const contentEl = document.getElementById(`content-${commentId}`);
    const originalHtml = contentEl.innerHTML;

    contentEl.innerHTML = `
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" class="comment-input" style="flex: 1; min-width: 200px;">
            <button onclick="saveEdit(${commentId})" class="comment-submit-btn">Save</button>
            <button onclick="cancelEdit(${commentId})" style="padding: 10px 16px; border-radius: 10px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: inherit; cursor: pointer;">Cancel</button>
        </div>
    `;
    document.getElementById(`edit-input-${commentId}`).focus();

    window.cancelEdit = function(id) {
        document.getElementById(`content-${id}`).innerHTML = originalHtml;
    };
}

async function saveEdit(commentId) {
    const input = document.getElementById(`edit-input-${commentId}`);
    const newContent = input.value.trim();
    if (!newContent) return;

    try {
        const response = await fetch(API_BASE + '/edit-comment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                comment_id: commentId,
                content: newContent
            })
        });
        const data = await response.json();
        if (data.error) { alert(data.error); return; }
        loadComments();
    } catch (err) {
        console.error('Edit error:', err);
    }
}

async function pollSubmitComment(e) {
    e.preventDefault();
    const input = document.getElementById('commentInput');
    const content = input.value.trim();
    if (!content) return;

    try {
        const response = await fetch(API_BASE + '/comments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'submit',
                target_type: 'poll',
                target_id: pollId,
                content: content
            })
        });

        const data = await response.json();

        if (data.error) {
            alert(data.error);
            return;
        }

        input.value = '';
        document.getElementById('commentsCount').textContent = parseInt(document.getElementById('commentsCount').textContent) + 1;
        commentsLoaded = false;
        loadComments();
    } catch (err) {
        console.error('Comment submit error:', err);
    }
}

async function shareToFeed() {
    if (!confirm('Share this poll to your feed?')) return;

    try {
        const response = await fetch(API_BASE + '/share', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                parent_type: 'poll',
                parent_id: pollId
            })
        });

        const data = await response.json();

        if (data.error) {
            alert(data.error);
            return;
        }

        if (data.status === 'success') {
            alert('Poll shared to your feed!');
        }
    } catch (err) {
        console.error('Share error:', err);
        alert('Failed to share poll');
    }
}

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

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('#poll-holo-wrapper .poll-btn, #poll-holo-wrapper .social-btn, #poll-holo-wrapper .share-btn').forEach(btn => {
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

// Close reaction pickers when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.reaction-picker-toggle') && !e.target.closest('.reaction-picker-dropdown')) {
        document.querySelectorAll('.reaction-picker-dropdown.visible').forEach(p => p.classList.remove('visible'));
    }
});
</script>

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
