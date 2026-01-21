#!/usr/bin/env node
/**
 * CSS Consolidation Script
 * Reduces 289 CSS files → ~60 logical bundles
 *
 * Strategy:
 * 1. Core framework (design tokens, layout, grid)
 * 2. Components by type (buttons, forms, cards, modals)
 * 3. Pages by module (blog, groups, events, admin)
 * 4. Theme-specific (modern, civicone)
 * 5. Utilities (animations, interactions, polish)
 */

const fs = require('fs');
const path = require('path');

const CSS_DIR = 'httpdocs/assets/css';
const OUTPUT_DIR = 'httpdocs/assets/css/bundles';

// Bundle configuration
const bundles = {
  // === CORE FRAMEWORK ===
  'core-framework': [
    'design-tokens.css',
    'breakpoints.css',
    'nexus-phoenix.css',
    'nexus-shared-transitions.css',
    'theme-transitions.css'
  ],

  // === COMPONENTS ===
  'components-buttons': [
    'buttons.css',
    'button-ripple.css',
    'fab-polish.css'
  ],

  'components-forms': [
    'forms.css',
    'form-validation.css',
    'responsive-forms.css',
    'premium-search.css'
  ],

  'components-cards': [
    'cards.css',
    'card-hover-states.css',
    'glassmorphism.css'
  ],

  'components-modals': [
    'modals.css',
    'mobile-sheets.css',
    'biometric-modal.css'
  ],

  'components-navigation': [
    'nexus-modern-header.css',
    'nexus-native-nav-v2.css',
    'mobile-nav-v2.css',
    'nexus-header-extracted.css',
    'modern-header-utilities.css',
    'premium-dropdowns.css',
    'nexus-premium-mega-menu.css'
  ],

  'components-notifications': [
    'toast-notifications.css',
    'notifications.css',
    'badge-animations.css'
  ],

  // === ADMIN ===
  'admin-core': [
    'admin-gold-standard.css',
    'admin-sidebar.css',
    'admin-federation.css'
  ],

  'admin-modules': [
    'admin-menu-builder.css',
    'admin-menu-index.css'
  ],

  // === CIVICONE THEME ===
  'civicone-core': [
    'civicone-base.css',
    'civicone-header.css',
    'civicone-mobile.css',
    'civicone-native.css'
  ],

  'civicone-modules': [
    'civicone-blog.css',
    'civicone-groups.css',
    'civicone-events.css',
    'civicone-profile.css',
    'civicone-volunteering.css',
    'civicone-mini-modules.css',
    'civicone-messages.css',
    'civicone-achievements.css'
  ],

  'civicone-utilities': [
    'civicone-utilities.css',
    'civicone-blog-utilities.css',
    'civicone-groups-utilities.css'
  ],

  // === MODERN THEME ===
  'modern-pages': [
    'nexus-home.css',
    'nexus-groups.css',
    'profile-holographic.css',
    'dashboard.css'
  ],

  // === FEATURES ===
  'features-social': [
    'post-box-home.css',
    'feed-filter.css',
    'feed-empty-state.css',
    'social-interactions.css',
    'compose-multidraw.css'
  ],

  'features-gamification': [
    'nexus-score.css',
    'achievements.css',
    'gamification.css'
  ],

  'features-federation': [
    'federation-realtime.css'
  ],

  'features-pwa': [
    'pwa-install-modal.css',
    'pull-to-refresh.css',
    'page-transitions.css'
  ],

  // === UTILITIES ===
  'utilities-polish': [
    'nexus-polish.css',
    'nexus-interactions.css',
    'hover-interactions.css',
    'scroll-progress.css',
    'error-states.css'
  ],

  'utilities-loading': [
    'nexus-loading-fix.css',
    'image-lazy-load.css',
    'avatar-placeholders.css',
    'empty-states.css'
  ],

  'utilities-accessibility': [
    'accessibility.css',
    'focus-rings.css'
  ]
};

console.log('📦 CSS Consolidation Script');
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

// Create output directory
if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  console.log(`✅ Created ${OUTPUT_DIR}\n`);
}

let totalBundles = 0;
let totalFiles = 0;
const errors = [];

// Generate each bundle
for (const [bundleName, files] of Object.entries(bundles)) {
  const outputFile = path.join(OUTPUT_DIR, `${bundleName}.css`);
  let bundleContent = `/**\n * ${bundleName} Bundle\n * Generated: ${new Date().toISOString()}\n * Files: ${files.length}\n */\n\n`;

  let filesIncluded = 0;

  for (const file of files) {
    const filePath = path.join(CSS_DIR, file);

    if (fs.existsSync(filePath)) {
      const content = fs.readFileSync(filePath, 'utf8');
      bundleContent += `/* ========================================\n * ${file}\n * ======================================== */\n\n`;
      bundleContent += content + '\n\n';
      filesIncluded++;
    } else {
      errors.push(`⚠️  File not found: ${file} (for bundle: ${bundleName})`);
    }
  }

  fs.writeFileSync(outputFile, bundleContent);
  console.log(`✅ ${bundleName}.css`);
  console.log(`   Files: ${filesIncluded}/${files.length}`);

  totalBundles++;
  totalFiles += filesIncluded;
}

console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log('📊 SUMMARY:');
console.log(`   Bundles created: ${totalBundles}`);
console.log(`   Files consolidated: ${totalFiles}`);
console.log(`   Target reduction: 289 → ${totalBundles} core bundles`);

if (errors.length > 0) {
  console.log('\n⚠️  WARNINGS:');
  errors.forEach(err => console.log(`   ${err}`));
}

console.log('\n✨ Next steps:');
console.log('   1. Review bundles in httpdocs/assets/css/bundles/');
console.log('   2. Update headers to load bundles instead of individual files');
console.log('   3. Run: npm run minify:css');
console.log('   4. Test all pages for missing styles\n');
