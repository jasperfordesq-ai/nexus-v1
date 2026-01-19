# PDF Flyer Creation Guide

Documentation from the hOUR Timebank and Timebank Global flyer creation session.

## Overview

This guide covers creating high-quality A4 PDF flyers using HTML/CSS rendered via Chrome headless mode. The flyers use the FDS Light Mode Design System with **Lucide SVG icons** (outlined style, consistent stroke-width: 2), CMYK print optimization, and premium visual effects.

## Files Created

### hOUR Timebank (Ireland)
- **HTML Source**: `C:\Users\jaspe\AppData\Local\Temp\claude\c--xampp-htdocs-staging\...\scratchpad\hour_timebank_flyer.html`
- **PDF Output**: `hOUR-Timebank-v9.pdf`
- **Generator Script**: `scratchpad/generate_hour_pdf_v2.js` (modify htmlPath/pdfPath as needed)

### Timebank Global (Worldwide)
- **HTML Source**: `C:\Users\jaspe\AppData\Local\Temp\claude\c--xampp-htdocs-staging\...\scratchpad\timebank_global_flyer.html`
- **PDF Output**: `Timebank-Global.pdf`
- **Generator Script**: `scratchpad/generate_hour_pdf_v2.js` (same script, different paths)

## Technical Specifications

### Page Setup
```css
@page {
    size: A4;
    margin: 0;
}

.page {
    width: 210mm;
    height: 297mm;
    padding: 14mm 16mm 20mm 16mm;
    position: relative;
    overflow: hidden;
    page-break-after: always;
}
```

### PDF Generation (Chrome Headless)
```javascript
const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
execSync(`"${chromePath}" --headless --disable-gpu --print-to-pdf="${pdfPath}" --no-margins --print-to-pdf-no-header "${fileUrl}"`);
```

Key flags:
- `--headless`: Run without UI
- `--disable-gpu`: Prevent GPU issues
- `--print-to-pdf`: Output as PDF
- `--no-margins`: Use CSS margins instead
- `--print-to-pdf-no-header`: Remove default header/footer

## Design System

### FDS Light Mode Colors
```css
:root {
    --primary: #4f46e5;        /* Indigo */
    --primary-light: #6366f1;
    --primary-glow: rgba(79, 70, 229, 0.35);
    --magenta: #d946ef;
    --cyan: #0891b2;
    --emerald: #059669;
    --amber: #d97706;
    --violet: #7c3aed;

    --bg-light: #f8fafc;
    --bg-white: #ffffff;
    --text-primary: #1e293b;
    --text-secondary: #475569;
    --text-muted: #64748b;
    --text-light: #94a3b8;
    --border-light: rgba(148, 163, 184, 0.25);
}
```

### Typography

- **Headings**: Plus Jakarta Sans (Google Fonts) - weight 400-800
- **Numbers/Accents**: Space Grotesk (Google Fonts) - weight 500-700
- **Body**: Plus Jakarta Sans

### Lucide SVG Icons

Instead of emojis or CSS Unicode icons (which render inconsistently in PDFs), use Lucide SVG icons with consistent styling:

```css
/* Icon container with gradient background */
.icon-box {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    margin: 0 auto 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.12);
}

.icon-box-lg { width: 44px; height: 44px; border-radius: 12px; }
.icon-box-sm { width: 28px; height: 28px; border-radius: 8px; }

/* SVG styling - consistent stroke properties */
.icon-box svg {
    width: 20px;
    height: 20px;
    stroke: white;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
}

/* Gradient backgrounds */
.bg-indigo { background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%); }
.bg-pink { background: linear-gradient(135deg, #f472b6 0%, #ec4899 100%); }
.bg-emerald { background: linear-gradient(135deg, #34d399 0%, #10b981 100%); }
.bg-cyan { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
.bg-amber { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
.bg-magenta { background: linear-gradient(135deg, #d946ef 0%, #a855f7 100%); }
```

Example Lucide icon usage (inline SVG):

```html
<div class="icon-box bg-emerald">
    <svg viewBox="0 0 24 24">
        <path d="m16 3 4 4-4 4"/>
        <path d="M20 7H4"/>
        <path d="m8 21-4-4 4-4"/>
        <path d="M4 17h16"/>
    </svg>
</div>
```

Key icons used: Diamond, Home, ArrowLeftRight, Globe, Scale, Sparkles, Heart, AtSign, Bell, Trophy, Bot, Users, MapPin, Mail, Clock, Calendar, MessageSquare, Share2, BarChart3, Lock, Smartphone, etc.

## Common Issues & Fixes

### 1. Text Clipping in Stat Cards
**Problem**: Gradient text gets clipped when `overflow: hidden` is set.
**Solution**: Remove `overflow: hidden` from stat cards.

```css
/* BAD */
.stat-card {
    overflow: hidden;  /* Clips gradient text */
}

/* GOOD */
.stat-card {
    position: relative;
    /* No overflow: hidden */
}
```

### 2. Double Text Rendering in CTA
**Problem**: Chrome PDF rendering bug causes text with `text-shadow` to appear doubled.
**Solution**: Remove text-shadow in print media query.

```css
@media print {
    .cta-section h2 {
        text-shadow: none !important;
    }
}
```

### 3. Page 1 Cramped Layout
**Problem**: Too much content causing cramped appearance.
**Solution**: Reduce spacing values:

```css
/* Tighter hero */
.hero { margin-bottom: 18px; }
.hero h1 { font-size: 42px; line-height: 1.12; margin-bottom: 10px; }
.hero-tagline { font-size: 20px; margin-bottom: 8px; }
.hero p { font-size: 15px; line-height: 1.6; }

/* Tighter stats */
.stats-row { gap: 16px; margin-bottom: 18px; }
.stat-card { padding: 18px 30px; }
.stat-value { font-size: 34px; }
.stat-label { font-size: 11px; margin-top: 4px; }

/* Tighter intro */
.intro-grid { gap: 20px; }
.intro-text h2 { font-size: 20px; margin-bottom: 10px; }
.intro-text p { font-size: 13px; line-height: 1.65; margin-bottom: 8px; }
.story-card { padding: 16px; border-radius: 14px; }
.timeline-item { font-size: 12px; margin-bottom: 8px; gap: 10px; }
```

### 4. File Lock Errors
**Problem**: "The process cannot access the file because it is being used by another process"
**Solution**: Close any PDF viewers or output to a new filename.

### 5. CMYK Print Optimization
Add print media query with slightly desaturated colors:

```css
@media print {
    :root {
        --primary: #4a42d4;
        --magenta: #c840db;
        --cyan: #0885a8;
        --emerald: #058a5f;
        --amber: #c96d06;
        --violet: #7035d9;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
}
```

## 5-Page Structure (Consolidated)

### Page 1: Hero & Introduction

- Logo + badges (hOUR: "Rethink Ireland Awardee", "Registered Irish Charity" / Global: "Global Network", "Connecting Communities")
- Main headline with gradient text
- Tagline and description
- 4 stat cards
- "What is Timebanking?" section with intro grid
- "Our Story" timeline card
- **Five Core Principles** (5 cards with Lucide icons)

### Page 2: The Timebanking Way

- How Timebanking Works (4 numbered steps)
- What Can You Exchange? (12 service tags with icons)
- **Impact section** with SROI badge and 4 impact stat cards

### Page 3: Proven Impact & Social Features

- 2 testimonial cards
- Mission & Vision cards
- **Social Media Features** (6 cards: Community Feed, Likes, Comments, Share, @Mentions, Notifications)

### Page 4: Platform & Gamification

- Intelligent Feed Algorithm section (with 4 factor cards)
- Complete Platform Features (8 module cards in grid)
- **Gamification & Rewards** (3 cards: XP System, Achievement Badges, Leaderboards)
- Badge tier system (Legendary, Epic, Rare, Common)

### Page 5: Values & Call to Action

- 3 Core Values cards (Reciprocity & Equality, Inclusion & Connection, Empowerment)
- Large CTA section with gradient background
- 3 Contact cards (Website, Email, Location)
- Footer with charity/organization info

## Glass Morphism Effects

```css
.stat-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: 0 8px 32px rgba(79, 70, 229, 0.1),
                0 4px 12px rgba(0, 0, 0, 0.06),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
}
```

## Decorative Elements

```css
.decoration {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.4;
    z-index: 1;
}

.decoration-1 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--magenta) 100%);
    top: -100px;
    right: -100px;
}
```

## Branding Differences

### hOUR Timebank (Ireland)

- Logo: "h" icon (gradient indigo-magenta)
- Badges: "Rethink Ireland Awardee", "Registered Irish Charity"
- Stats: 250+ Members, 16:1 SROI, €500K+ Social Value, 92% Wellbeing
- Website: hour-timebank.ie
- Email: jasper@hour-timebank.ie
- Location: Ireland Nationwide
- Footer: "hOUR Timebank CLG (RBN Timebank Ireland) Registered Charity Number 20162023"
- Timeline: Irish history (2012-2030)
- Impact: Ireland-specific metrics

### Timebank Global (Worldwide)

- Logo: "G" icon (gradient indigo-magenta)
- Badges: "Global Network", "Connecting Communities"
- Stats: 50+ Countries, 10K+ Members, 1M+ Hours Exchanged, 100% Community Driven
- Website: timebank.global
- Email: jasper@hour-timebank.ie
- Location: Worldwide
- Footer: "Timebank Global - Connecting Communities Worldwide Through the Gift of Time"
- Timeline: Global history (1980s-Today) - Edgar Cahn origins
- Impact: Global metrics ($5M+ social value, 50+ countries)

## Best Practices

1. **Always test PDF output** - View the actual PDF, not just browser preview
2. **Use Chrome headless** - Most reliable PDF rendering
3. **Use Lucide SVG icons** - Consistent stroke-width: 2, outlined style, white stroke on gradient backgrounds
4. **Avoid emojis** - They render inconsistently across systems and in PDFs
5. **Test gradient text** - Can cause clipping/doubling issues
6. **Check page breaks** - Use `page-break-after: always` and `overflow: hidden`
7. **CMYK considerations** - Slightly desaturate colors for print
8. **Font loading** - Use Google Fonts with preconnect for reliability
9. **File locking** - Close PDF viewers before regenerating
10. **Compact layouts** - Reduce padding/gaps when content doesn't fit on page
11. **Flexbox centering** - Use `display: flex; justify-content: center;` for centered grids

## Lucide Icon Reference

Get SVG paths from: <https://lucide.dev/icons>

Common icons used in flyers:

- **Principles**: Diamond, Home, ArrowLeftRight, Globe, Scale
- **Services**: Leaf, Server, Car, BookOpen, Dog, Coffee, Wrench, FileEdit, UtensilsCrossed, MessageSquare, ShoppingCart, Music
- **Social**: Hash, Heart, MessageCircle, Share2, AtSign, Bell
- **Platform**: Clock, Store, Smartphone, Calendar, MessageSquare, Users, BarChart3, Lock
- **Gamification**: Sparkles, Trophy, Bot
- **Values**: Scale, HandHeart, Podcast
- **Contact**: Globe, Mail, MapPin
