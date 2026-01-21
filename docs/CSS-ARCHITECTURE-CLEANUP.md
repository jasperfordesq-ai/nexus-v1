# CSS Architecture Cleanup - Design Token Migration

**Date**: 2026-01-21
**Goal**: Migrate all hardcoded CSS values to design tokens for consistency and maintainability

---

## Executive Summary

**Total Violations Found**: 16,436 across 162 CSS files

| Category | Count | Impact |
|----------|-------|--------|
| Spacing (padding/margin/gap) | 6,931 | High |
| Border Radius | 3,413 | High |
| Typography (font-size) | 6,073 | High |
| Transitions | 19 | Low |

**Estimated Total Effort**: 77-97 hours
**Quick Win (Top 5 files)**: 15-20 hours, eliminates 18.5% of violations

---

## Top 20 Priority Files

| Rank | File | Total Violations | Traffic Priority |
|------|------|------------------|------------------|
| 1 | federation.css | 1,153 | HIGH |
| 2 | modern-bundle-compiled.css | 527 | CRITICAL |
| 3 | scattered-singles.css | 572 | MEDIUM |
| 4 | volunteering.css | 496 | HIGH |
| 5 | groups.css | 301 | CRITICAL |
| 6 | civicone-bundle-compiled.css | 304 | CRITICAL |
| 7 | groups-show.css | 259 | HIGH |
| 8 | nexus-home.css | 308 | CRITICAL |
| 9 | organizations.css | 281 | MEDIUM |
| 10 | achievements.css | 352 | MEDIUM |
| 11 | civicone-dashboard.css | 219 | CRITICAL |
| 12 | matches.css | 206 | MEDIUM |
| 13 | polls.css | 217 | MEDIUM |
| 14 | static-pages.css | 300 | LOW |
| 15 | messages-index.css | 221 | HIGH |
| 16 | civicone-federation.css | 181 | HIGH |
| 17 | listings-index.css | 164 | MEDIUM |
| 18 | compose-multidraw.css | 177 | HIGH |
| 19 | goals.css | 149 | MEDIUM |
| 20 | help.css | 142 | LOW |

---

## Migration Strategy

### Phase 1: Critical User-Facing Files (Week 1)

**Focus**: Home, Feed, Groups, Dashboard (highest traffic)

1. **nexus-home.css** (308 violations) - Homepage
2. **groups.css** (301 violations) - Groups listing
3. **groups-show.css** (259 violations) - Group detail pages
4. **civicone-dashboard.css** (219 violations) - Dashboard
5. **feed-item.css** (22 violations) - Feed items

**Estimated Effort**: 12-15 hours
**Impact**: Eliminates 1,109 violations in highest-traffic pages

---

### Phase 2: Bundle Files (Week 2)

**Focus**: Compiled bundles affecting multiple pages

1. **modern-bundle-compiled.css** (527 violations)
2. **civicone-bundle-compiled.css** (304 violations)

**Estimated Effort**: 10-12 hours
**Impact**: Eliminates 831 violations affecting entire theme

---

### Phase 3: Module Files (Week 3-4)

**Focus**: Major feature modules

1. **federation.css** (1,153 violations)
2. **volunteering.css** (496 violations)
3. **civicone-federation.css** (181 violations)
4. **messages-index.css** (221 violations)
5. **compose-multidraw.css** (177 violations)

**Estimated Effort**: 20-25 hours
**Impact**: Eliminates 2,228 violations in major features

---

### Phase 4: Secondary Modules (Week 5-6)

**Focus**: Additional feature modules

1. **scattered-singles.css** (572 violations)
2. **achievements.css** (352 violations)
3. **organizations.css** (281 violations)
4. **polls.css** (217 violations)
5. **matches.css** (206 violations)
6. **listings-index.css** (164 violations)
7. **goals.css** (149 violations)

**Estimated Effort**: 18-22 hours
**Impact**: Eliminates 1,941 violations

---

### Phase 5: Long Tail (Week 7+)

**Focus**: Remaining 142 files with lower violation counts

**Estimated Effort**: 40-50 hours
**Impact**: Eliminates final 9,745 violations

---

## Token Mapping Reference

### Spacing Tokens

```css
/* From design-tokens.css */
--space-0: 0;
--space-0.5: 0.125rem;  /* 2px */
--space-1: 0.25rem;     /* 4px */
--space-1.5: 0.375rem;  /* 6px */
--space-2: 0.5rem;      /* 8px */
--space-2.5: 0.625rem;  /* 10px */
--space-3: 0.75rem;     /* 12px */
--space-3.5: 0.875rem;  /* 14px */
--space-4: 1rem;        /* 16px */
--space-5: 1.25rem;     /* 20px */
--space-6: 1.5rem;      /* 24px */
--space-7: 1.75rem;     /* 28px */
--space-8: 2rem;        /* 32px */
--space-9: 2.25rem;     /* 36px */
--space-10: 2.5rem;     /* 40px */
--space-11: 2.75rem;    /* 44px */
--space-12: 3rem;       /* 48px */
--space-14: 3.5rem;     /* 56px */
--space-16: 4rem;       /* 64px */
--space-20: 5rem;       /* 80px */
```

**Common Replacements:**
- `padding: 8px` → `padding: var(--space-2)`
- `margin: 12px` → `margin: var(--space-3)`
- `padding: 16px` → `padding: var(--space-4)`
- `gap: 20px` → `gap: var(--space-5)`
- `padding: 24px` → `padding: var(--space-6)`
- `margin: 32px` → `margin: var(--space-8)`

### Border Radius Tokens

```css
/* From design-tokens.css */
--radius-none: 0;
--radius-sm: 0.25rem;   /* 4px */
--radius-base: 0.5rem;  /* 8px */
--radius-md: 0.75rem;   /* 12px */
--radius-lg: 1rem;      /* 16px */
--radius-xl: 1.5rem;    /* 24px */
--radius-2xl: 2rem;     /* 32px */
--radius-full: 9999px;
```

**Common Replacements:**
- `border-radius: 4px` → `border-radius: var(--radius-sm)`
- `border-radius: 8px` → `border-radius: var(--radius-base)`
- `border-radius: 12px` → `border-radius: var(--radius-md)`
- `border-radius: 16px` → `border-radius: var(--radius-lg)`
- `border-radius: 24px` → `border-radius: var(--radius-xl)`
- `border-radius: 50%` → `border-radius: var(--radius-full)`

### Typography Tokens

```css
/* From design-tokens.css */
--font-size-xs: 0.75rem;    /* 12px */
--font-size-sm: 0.875rem;   /* 14px */
--font-size-base: 1rem;     /* 16px */
--font-size-lg: 1.125rem;   /* 18px */
--font-size-xl: 1.25rem;    /* 20px */
--font-size-2xl: 1.5rem;    /* 24px */
--font-size-3xl: 1.875rem;  /* 30px */
--font-size-4xl: 2.25rem;   /* 36px */
--font-size-5xl: 3rem;      /* 48px */
--font-size-6xl: 3.75rem;   /* 60px */
```

**Common Replacements:**
- `font-size: 12px` → `font-size: var(--font-size-xs)`
- `font-size: 14px` → `font-size: var(--font-size-sm)`
- `font-size: 16px` → `font-size: var(--font-size-base)`
- `font-size: 18px` → `font-size: var(--font-size-lg)`
- `font-size: 20px` → `font-size: var(--font-size-xl)`
- `font-size: 24px` → `font-size: var(--font-size-2xl)`

### Transition Tokens

```css
/* From design-tokens.css */
--transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
--transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
--transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
```

**Common Replacements:**
- `transition: 150ms` → `transition: var(--transition-fast)`
- `transition: 200ms` → `transition: var(--transition-base)`
- `transition: 300ms` → `transition: var(--transition-slow)`

---

## Automated Migration Scripts

### Find/Replace Regex Patterns

**Spacing:**
```regex
Find: padding:\s*16px
Replace: padding: var(--space-4)

Find: margin:\s*12px
Replace: margin: var(--space-3)

Find: gap:\s*20px
Replace: gap: var(--space-5)
```

**Border Radius:**
```regex
Find: border-radius:\s*8px
Replace: border-radius: var(--radius-base)

Find: border-radius:\s*12px
Replace: border-radius: var(--radius-md)
```

**Typography:**
```regex
Find: font-size:\s*14px
Replace: font-size: var(--font-size-sm)

Find: font-size:\s*16px
Replace: font-size: var(--font-size-base)
```

---

## Testing Strategy

### After Each File Migration:

1. **Visual Regression Test**
   - Compare screenshots before/after
   - Test on mobile (375px), tablet (768px), desktop (1440px)
   - Test dark mode

2. **Browser Testing**
   - Chrome, Firefox, Safari, Edge
   - iOS Safari, Android Chrome

3. **Lighthouse Audit**
   - Ensure CSS size reduction
   - Verify no layout shifts

4. **Manual QA**
   - Click through major user flows
   - Verify hover states
   - Check responsive breakpoints

---

## Expected Benefits

### Immediate Benefits:

- **Consistency**: All spacing/radius/typography follows design system
- **Maintainability**: Change one token, update everywhere
- **Dark Mode**: Tokens support theme switching automatically
- **Responsive**: Tokens can be breakpoint-specific

### Long-Term Benefits:

- **CSS Size Reduction**: Estimated 30-40% reduction through deduplication
- **Development Speed**: No more guessing spacing values
- **Brand Updates**: Change entire brand with token updates
- **A11y**: Tokens enforce accessible spacing/contrast

---

## Success Metrics

- [ ] 16,436 hardcoded values migrated to tokens
- [ ] 0 visual regressions introduced
- [ ] 30-40% CSS size reduction
- [ ] All high-traffic pages migrated (Phase 1-3)
- [ ] Design system documentation complete

---

## Progress Tracking

| Phase | Files | Violations | Status | Completion Date |
|-------|-------|------------|--------|-----------------|
| Phase 1 | 5 files | 1,109 | Not Started | - |
| Phase 2 | 2 files | 831 | Not Started | - |
| Phase 3 | 5 files | 2,228 | Not Started | - |
| Phase 4 | 7 files | 1,941 | Not Started | - |
| Phase 5 | 142 files | 9,745 | Not Started | - |

---

## Related Documentation

- [Design Tokens](./DESIGN-TOKENS.md)
- [Mobile Design Tokens](./MOBILE-DESIGN-TOKENS.md)
- [Desktop Design Tokens](./DESKTOP-DESIGN-TOKENS.md)
- [CSS Architecture Guide](./CSS-ARCHITECTURE.md)
