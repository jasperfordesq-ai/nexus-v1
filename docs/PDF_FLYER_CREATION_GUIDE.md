# PDF Flyer Creation Guide

Documentation from the hOUR Timebank and Timebank Global flyer creation session.

## Overview

This guide covers creating high-quality A4 PDF flyers using HTML/CSS rendered via Chrome headless mode. The flyers use the FDS Light Mode Design System with CSS-only icons (no emojis), CMYK print optimization, and premium visual effects.

## Files Created

### hOUR Timebank (Ireland)
- **HTML Source**: `scratchpad/hour_timebank_flyer.html`
- **PDF Output**: `hOUR_Timebank_Flyer_v3.pdf`
- **Generator Script**: `scratchpad/generate_hour_pdf_v2.js`

### Timebank Global (Worldwide)
- **HTML Source**: `scratchpad/timebank_global_flyer.html`
- **PDF Output**: `Timebank_Global_Flyer.pdf`
- **Generator Script**: `scratchpad/generate_global_pdf.js`

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
- **Headings**: Inter (Google Fonts)
- **Numbers/Accents**: Space Grotesk (Google Fonts)
- **Body**: Inter

### CSS-Only Icons
Instead of emojis (which render inconsistently in PDFs), use CSS icons:

```css
.css-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
    font-size: 14px;
    color: white;
    margin: 0 auto 10px;
}

/* Icon variations with gradient backgrounds */
.icon-diamond { background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%); }
.icon-home { background: linear-gradient(135deg, #f472b6 0%, #ec4899 100%); }
.icon-arrows { background: linear-gradient(135deg, #34d399 0%, #10b981 100%); }
```

Icon symbols used: Unicode characters like `◆`, `⌂`, `⇄`, `◉`, `⚖`, `★`, `♥`, `@`, etc.

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

## 6-Page Structure

### Page 1: Hero & Introduction
- Logo + badges (Rethink Ireland Awardee, Registered Irish Charity)
- Main headline with gradient text
- Tagline and description
- 4 stat cards (Members, SROI, Social Value, Wellbeing)
- "What is Timebanking?" section with 4 paragraphs
- "Our Irish/Global Story" timeline card

### Page 2: The Timebanking Way
- 5 Core Principles (cards with icons)
- How Timebanking Works (4 numbered steps)
- What Can You Exchange? (12 service tags with icons)

### Page 3: Proven Impact
- Impact section with 16:1 SROI badge
- 4 impact stat cards
- 2 testimonial cards
- Mission & Vision cards

### Page 4: Social Platform
- "A Complete Social Platform" hero section
- 6 Social Media Features (grid of cards)
- Intelligent Feed Algorithm section

### Page 5: Enterprise Platform
- Complete Platform Features (8 module cards)
- Gamification & Rewards (3 cards)
- Badge tier system (Legendary, Epic, Rare, Common)

### Page 6: Call to Action
- 3 Core Values cards
- Large CTA section with gradient background
- 3 Contact cards (Website, Email, Location)
- Footer with charity registration

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
- Logo: "h" icon
- Badges: "Rethink Ireland Awardee", "Registered Irish Charity"
- Website: hour-timebank.ie
- Email: jasper@hour-timebank.ie
- Location: Ireland Nationwide
- Footer: "hOUR Timebank CLG (RBN Timebank Ireland) Registered Charity Number 20162023"
- Timeline: Irish history (2012-2030)

### Timebank Global (Worldwide)
- Logo: "T" icon
- Badges: "Global Community", "Worldwide Network"
- Website: timebank.global
- Email: hello@timebank.global
- Location: Worldwide
- Footer: "Timebank Global - Connecting Communities Worldwide Through the Gift of Time"
- Timeline: Global history (1980s-Today)

## Best Practices

1. **Always test PDF output** - View the actual PDF, not just browser preview
2. **Use Chrome headless** - Most reliable PDF rendering
3. **Avoid emojis** - Use CSS icons with Unicode symbols instead
4. **Test gradient text** - Can cause clipping/doubling issues
5. **Check page breaks** - Use `page-break-after: always` and `overflow: hidden`
6. **CMYK considerations** - Slightly desaturate colors for print
7. **Font loading** - Use Google Fonts with preconnect for reliability
8. **File locking** - Close PDF viewers before regenerating
