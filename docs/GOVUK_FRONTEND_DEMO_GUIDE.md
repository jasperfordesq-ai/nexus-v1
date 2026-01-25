# GOV.UK Frontend Demo Website Guide

**Status:** ✅ Running
**URL:** http://localhost:3000
**Location:** `C:\xampp\htdocs\govuk-frontend-official`

---

## Quick Start

### Start the Demo Website
```bash
cd /c/xampp/htdocs/govuk-frontend-official
npm start
```

Then open: **http://localhost:3000**

### Stop the Demo Website
Press `Ctrl+C` in the terminal, or:
```bash
# Find and kill the process
taskkill /F /IM node.exe
```

---

## What's Available

The demo website shows **all 40+ official GOV.UK components** with:
- ✅ Live interactive examples
- ✅ Full styling and JavaScript
- ✅ Mobile/tablet responsive views
- ✅ Accessibility features (WCAG 2.2 AA)
- ✅ Auto-refresh on code changes (BrowserSync)

### Key Components You Can See

| Component | What It Shows |
|-----------|---------------|
| **Service Navigation** | The exact component we implemented (92/100 match) |
| **Header** | Official GOV.UK header with navigation |
| **Footer** | Standard footer with links and metadata |
| **Button** | All button variants (primary, secondary, warning, disabled) |
| **Form Inputs** | Text inputs, textareas, selects, date inputs |
| **Checkboxes & Radios** | Form controls with proper ARIA |
| **Error Messages** | Validation errors and summaries |
| **Notification Banner** | Success/warning/error banners |
| **Accordion** | Expandable content sections |
| **Breadcrumbs** | Navigation breadcrumb trail |
| **Tabs** | Tabbed content interface |
| **Tables** | Data tables with sorting |
| **Pagination** | Page navigation controls |
| **Tag** | Status tags (e.g., "Completed", "In progress") |
| **Warning Text** | Important warning callouts |
| **Phase Banner** | "Alpha" / "Beta" service indicators |
| **Cookie Banner** | Cookie consent interface |
| **Character Count** | Text input with character limits |
| **Task List** | Step-by-step task completion |
| **Summary List** | Key-value data display |
| **Panel** | Confirmation panels |
| **Inset Text** | Highlighted text blocks |
| **Details** | Expandable disclosure widget |

---

## How to Use It

### 1. Browse Components
- Navigate through the sidebar menu
- Click on any component to see live examples
- Each page shows multiple variants and states

### 2. Test Interactions
- Click buttons to see focus states
- Type in form fields to see validation
- Resize browser to test responsive behavior
- Use keyboard (Tab, Enter, Space) to test accessibility

### 3. View Source Code
- Right-click → "View Page Source" to see HTML
- Open DevTools (F12) → Elements tab to inspect
- Check `packages/govuk-frontend-review/src/views/` for page templates
- Check `packages/govuk-frontend/src/govuk/components/` for component code

### 4. Compare to Our Implementation
Navigate to Service Navigation example and compare with our CivicOne implementation:

**GOV.UK Demo:**
- http://localhost:3000/components/service-navigation

**Our Implementation:**
- https://project-nexus.ie (CivicOne theme)
- Check: Navigation bar, dropdown, styling

---

## Useful URLs

| Page | URL |
|------|-----|
| **Homepage** | http://localhost:3000 |
| **All Components** | http://localhost:3000/components |
| **Service Navigation** | http://localhost:3000/components/service-navigation |
| **Button** | http://localhost:3000/components/button |
| **Header** | http://localhost:3000/components/header |
| **Footer** | http://localhost:3000/components/footer |
| **Form Elements** | http://localhost:3000/components/text-input |
| **Patterns** | http://localhost:3000/patterns |

---

## File Structure

```
govuk-frontend-official/
├── packages/
│   ├── govuk-frontend/              # Main package
│   │   ├── src/govuk/
│   │   │   ├── components/          # All components (SCSS, JS, templates)
│   │   │   │   ├── service-navigation/
│   │   │   │   ├── button/
│   │   │   │   ├── header/
│   │   │   │   └── ... (40+ more)
│   │   │   ├── settings/            # Design tokens
│   │   │   ├── tools/               # Sass mixins/functions
│   │   │   └── utilities/           # Utility classes
│   │   └── dist/                    # Compiled CSS/JS
│   │
│   └── govuk-frontend-review/       # Demo website
│       ├── src/
│       │   ├── views/               # Page templates (Nunjucks)
│       │   ├── stylesheets/         # Demo-specific styles
│       │   └── javascripts/         # Demo-specific JS
│       └── public/                  # Served at http://localhost:3000
```

---

## What to Look For

### When Implementing New Components

1. **Navigate to component page** in demo
   - Example: http://localhost:3000/components/button

2. **Test all variants**
   - Default state
   - Hover state
   - Focus state (use Tab key)
   - Disabled state
   - Different sizes/colors

3. **Check SCSS source**
   ```bash
   cat /c/xampp/htdocs/govuk-frontend-official/packages/govuk-frontend/src/govuk/components/button/_index.scss
   ```

4. **Extract exact values**
   - Font sizes
   - Padding/margins (use spacing scale)
   - Colors (use color palette)
   - Border widths
   - Line heights

5. **Test accessibility**
   - Keyboard navigation (Tab, Enter, Space, Escape)
   - Screen reader labels (ARIA attributes)
   - Focus indicators
   - Color contrast

---

## How We Used It for Service Navigation

### Our Process (What We Did)

1. **Visited demo:** http://localhost:3000/components/service-navigation
2. **Read SCSS:** `packages/govuk-frontend/src/govuk/components/service-navigation/_index.scss`
3. **Extracted values:**
   - Font: 19px → 1.1875rem
   - Line height: (29/19) → 1.526
   - Padding: govuk-spacing(3) → 15px
   - Gaps: govuk-spacing(6) → 30px
   - Border: govuk-spacing(1) → 5px
   - Background: #f0f4f5
   - Link color: #144e81
4. **Implemented in CSS:** `httpdocs/assets/css/civicone-service-navigation.css`
5. **Tested locally:** Compared side-by-side
6. **Achieved:** 92/100 compliance

---

## Common Tasks

### Update GOV.UK Frontend to Latest Version
```bash
cd /c/xampp/htdocs/govuk-frontend-official
git pull origin main
npm install
npm start
```

### Check Component HTML Structure
1. Open component page in demo
2. Right-click → Inspect Element (F12)
3. Look at HTML in DevTools
4. Compare with our PHP templates

### Find Exact Color Values
```bash
# View color palette
cat /c/xampp/htdocs/govuk-frontend-official/packages/govuk-frontend/src/govuk/settings/_colours-palette.scss
```

### Find Spacing Scale
```bash
# View spacing values
cat /c/xampp/htdocs/govuk-frontend-official/packages/govuk-frontend/src/govuk/settings/_spacing.scss
```

### Check Typography Scale
```bash
# View font sizes
cat /c/xampp/htdocs/govuk-frontend-official/packages/govuk-frontend/src/govuk/settings/_typography-responsive.scss
```

---

## BrowserSync Features

The demo uses BrowserSync which provides:
- **Auto-refresh:** Changes to SCSS/JS reload the page automatically
- **Multi-device sync:** Test on multiple devices simultaneously
- **Network access:** Access from other devices on your network
- **Scroll sync:** Scrolling syncs across all connected devices
- **Click mirroring:** Clicks sync across devices

### Access from Other Devices
If you want to test on your phone/tablet:
1. Check your computer's IP address
2. On the other device, visit: `http://YOUR_IP:3000`
3. Both will stay in sync!

---

## Troubleshooting

### Server Won't Start
```bash
# Kill any existing node processes
taskkill /F /IM node.exe

# Try starting again
cd /c/xampp/htdocs/govuk-frontend-official
npm start
```

### Port 3000 Already in Use
```bash
# Find what's using port 3000
netstat -ano | findstr :3000

# Kill that process (replace PID with actual process ID)
taskkill /F /PID <PID>
```

### Changes Not Showing
1. Hard refresh: `Ctrl+Shift+R`
2. Clear browser cache
3. Restart the server

### npm install Errors
```bash
# Clear cache and reinstall
cd /c/xampp/htdocs/govuk-frontend-official
rm -rf node_modules package-lock.json
npm install
```

---

## Key Differences: Demo vs Our Implementation

| Aspect | GOV.UK Demo | Our CivicOne |
|--------|-------------|--------------|
| **Templates** | Nunjucks (.njk) | PHP |
| **Styles** | SCSS (compiled) | Plain CSS |
| **Build** | Sass + PostCSS | PurgeCSS + minify |
| **Components** | Stock GOV.UK | GOV.UK + custom |
| **Dropdown** | Standard | Categorized (custom) |
| **Navigation** | Static links | NavigationConfig (dynamic) |

---

## What NOT to Do

❌ Don't try to copy the demo website into NEXUS
❌ Don't use Nunjucks templates in our PHP project
❌ Don't copy compiled CSS blindly
❌ Don't import SCSS files without compilation

## What TO Do

✅ Use as visual reference for components
✅ Extract exact values from SCSS files
✅ Test interactions and accessibility
✅ Compare our implementation side-by-side
✅ Learn patterns and best practices
✅ Validate our styling decisions

---

## Summary

**Purpose:** Live reference for all GOV.UK components
**Use For:** Visual comparison, value extraction, testing interactions
**Don't Use For:** Direct code copying or integration
**Best Practice:** Reference → Extract → Implement in our style

The demo website is your **visual source of truth** for verifying that our CivicOne implementation matches official GOV.UK patterns.

---

**Demo URL:** http://localhost:3000
**Start Command:** `cd /c/xampp/htdocs/govuk-frontend-official && npm start`
**Stop Command:** `Ctrl+C` or `taskkill /F /IM node.exe`
**Documentation:** https://design-system.service.gov.uk/
