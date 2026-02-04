# NEXUS React Frontend - Visual Shell Design Notes

This document explains the design decisions behind the NEXUS React frontend visual identity.

## Design Philosophy

The NEXUS frontend aims to feel **modern, premium, and distinctly branded** while maintaining usability and accessibility. When users land on the app, they should immediately feel: *"This is the new NEXUS."*

## Core Visual Elements

### 1. Holographic Gradient Background

The background uses a soft, iridescent gradient mesh that gives the app depth and visual interest without being distracting.

```css
/* Four overlapping radial gradients */
radial-gradient(ellipse at 0% 0%, var(--holo-1) 0%, transparent 50%),
radial-gradient(ellipse at 100% 0%, var(--holo-2) 0%, transparent 50%),
radial-gradient(ellipse at 100% 100%, var(--holo-3) 0%, transparent 50%),
radial-gradient(ellipse at 0% 100%, var(--holo-4) 0%, transparent 50%)
```

**Why holographic?**
- Creates visual depth without being overwhelming
- Subtly incorporates brand colors
- Differentiates NEXUS from generic SaaS apps
- Works well with glassmorphism surfaces

### 2. Glassmorphism Surfaces

All cards, headers, and interactive surfaces use glassmorphism (frosted glass effect):

| Surface | Blur | Opacity | Use Case |
|---------|------|---------|----------|
| `.glass` | 12px | 70% | Standard cards |
| `.glass-strong` | 16px | 85% | Headers, nav |
| `.glass-primary` | 12px | 8% tint | Accent cards |
| `.glass-elevated` | 16px | 85% + hover | Floating cards |
| `.glass-dark` | 16px | 90% dark | Footer |

**Why glassmorphism?**
- Creates layered depth
- Makes content feel "floating" above background
- Works naturally with holographic backdrop
- Modern aesthetic (iOS/Windows 11 inspired)

### 3. Tenant Branding System

Colors are applied via CSS custom properties that get overwritten by tenant bootstrap:

```css
:root {
  --tenant-primary: #6366f1;      /* Indigo default */
  --tenant-secondary: #8b5cf6;    /* Purple default */
  --tenant-accent: #06b6d4;       /* Cyan default */
}
```

**Applied to:**
- Active navigation states (pill highlight)
- Primary buttons (gradient from primary → secondary)
- Focus states
- Hero section gradient text
- Card accent elements

### 4. Depth & Shadow System

Consistent shadow scale for elevation hierarchy:

```css
--shadow-subtle: 0 2px 8px rgba(0, 0, 0, 0.04);
--shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.08);
--shadow-elevated: 0 8px 32px rgba(0, 0, 0, 0.12);
--shadow-float: 0 12px 48px rgba(0, 0, 0, 0.16);
```

### 5. Gradient Utilities

```css
.gradient-primary    /* primary → secondary (buttons) */
.gradient-accent     /* accent → primary (CTAs) */
.gradient-holo       /* Animated shimmer effect */
```

## Component Styling

### Header
- Glass-strong surface with border
- Brand text uses gradient (primary → secondary)
- Active nav items get glass-primary pill + dot indicator
- Sign In button uses gradient-primary

### Footer
- Dark glass (glass-dark)
- Social icons in circular glass containers with hover glow
- Links animate on hover (translate + color)
- Accent color on hover (tenant-accent)

### Mobile Nav
- Glass-strong bottom bar
- Active tab gets:
  - Gradient pill indicator above
  - Filled icon variant
  - Primary color tint background
  - Scale-up animation

### Cards (GlassCard)
- Four variants: default, elevated, primary, solid
- Elevated cards lift on hover (-2px + shadow increase)
- Consistent rounded-2xl corners
- Optional header/footer with divider borders

### Home Page
- Hero section with decorative gradient orbs (animated)
- Gradient text headline
- Quick action cards with icon gradients
- Feature chips use various Hero UI colors
- "Powered by NEXUS" badge with shimmer dot

## Animation System

```css
.animate-fade-in     /* 0.3s fade + slide up */
.animate-slide-up    /* 0.4s longer slide */
.animate-pulse-soft  /* Subtle breathing */
.delay-100/200/300   /* Staggered animations */
```

## Accessibility

- Focus states use tenant-primary color
- All interactive elements have visible focus rings
- Color contrast maintained through glass opacity
- Text remains readable on glass surfaces

## Files Changed

| File | Purpose |
|------|---------|
| `src/index.css` | Global styles, glass utilities, gradients |
| `src/components/layout/AppShell.tsx` | Holographic background container |
| `src/components/layout/Header.tsx` | Glass header with branded nav |
| `src/components/layout/Footer.tsx` | Dark glass footer |
| `src/components/layout/MobileNav.tsx` | Glass mobile nav with indicators |
| `src/components/ui/GlassCard.tsx` | Reusable glass card component |
| `src/components/ui/index.ts` | UI component exports |
| `src/components/index.ts` | Updated exports |
| `src/pages/HomePage.tsx` | Visual showcase landing page |

## Usage Examples

### Basic Glass Card
```tsx
<GlassCard variant="elevated" hoverable>
  <h2>Card Title</h2>
  <p>Card content</p>
</GlassCard>
```

### Gradient Button
```tsx
<Button className="gradient-primary text-white">
  Get Started
</Button>
```

### Applying Tenant Color
```tsx
<span className="text-[var(--tenant-primary)]">
  Branded text
</span>
```

## Browser Support

Glassmorphism requires:
- `backdrop-filter` (Safari 9+, Chrome 76+, Firefox 103+)
- CSS custom properties (all modern browsers)

Fallback: Solid semi-transparent backgrounds where blur not supported.

## Future Considerations

1. **Dark Mode**: Variables ready for `.dark` variant
2. **More Gradients**: Per-feature color gradients
3. **Micro-animations**: Skeleton loaders, transitions
4. **Theme Customization**: Per-tenant accent gradients
