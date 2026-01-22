#!/usr/bin/env node
/**
 * Auto CSS Discovery
 * Automatically generates purgecss.config.js with all CSS files
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob');

const projectRoot = path.resolve(__dirname, '..');

console.log('Auto-discovering CSS files...');
console.log('');

// Find all CSS files
const cssFiles = glob.sync('httpdocs/assets/css/**/*.css', {
    cwd: projectRoot,
    ignore: [
        '**/purged/**',
        '**/*.min.css',
        '**/node_modules/**',
        '**/vendor/**',
        '**/_archive/**',
        '**/_archived/**'
    ]
}).sort();

console.log(`Found ${cssFiles.length} CSS files`);
console.log('');

// Generate config with automatic discovery
const configTemplate = `module.exports = {
    // Content to scan for used classes
    content: [
        'views/**/*.php',
        'httpdocs/**/*.php',
        'httpdocs/assets/js/**/*.js',
        'src/**/*.php',
    ],

    // CSS files to purge
    // Auto-discovered: ${new Date().toISOString().split('T')[0]}
    css: [
${cssFiles.map(file => `        '${file}',`).join('\n')}
    ],

    // Output directory for purged CSS
    output: 'httpdocs/assets/css/purged/',

    // Safelist - classes to never remove
    safelist: {
        // Exact class names to keep
        standard: [
            // Dynamic state classes
            'active', 'open', 'closed', 'visible', 'hidden', 'show', 'hide',
            'loading', 'loaded', 'error', 'success', 'warning', 'info',
            'disabled', 'enabled', 'selected', 'checked', 'focused',
            'expanded', 'collapsed', 'animating', 'animated',
            'scrolled', 'sticky', 'fixed', 'ready', 'valid', 'invalid',
            'dark', 'light', 'mobile', 'desktop', 'tablet',

            // App state classes
            'verified-offline', 'hydrated', 'content-loaded',
            'no-ptr', 'chat-page', 'messages-fullscreen',
            'logged-in', 'user-is-admin',
            'nexus-home-page', 'nexus-skin-modern',
            'feed-loaded', 'page-loaded', 'fonts-loaded',
            'is-pwa', 'is-pwa-installed', 'is-native', 'is-native-app',
            'is-offline', 'pwa-installed', 'push-enabled',

            // Navigation states
            'drawer-open', 'nav-hidden', 'navigating', 'navigating-back',
            'hide-on-scroll', 'hiding', 'keyboard-open',
            'search-expanded', 'layout-switching',

            // Header states
            'nexus-header-compact', 'nexus-header-hidden', 'nexus-header-visible',
            'nexus-header-is-compact', 'nexus-header-is-hidden',
            'nexus-collapsing-header', 'back-nav',
        ],

        // Patterns to keep (regex)
        deep: [
            // Keep all Font Awesome classes
            /^fa-/, /^fas$/, /^far$/, /^fab$/, /^fal$/, /^fad$/,

            // Keep all framework prefixed classes
            /^nexus-/, /^civic-/, /^civicone-/, /^fds-/,

            // Keep all module prefixed classes
            /^htb-/, /^fed-/, /^vol-/, /^group-/, /^goal-/,
            /^poll-/, /^resource-/, /^match-/, /^org-/, /^help-/,
            /^wallet-/, /^glass-/, /^admin-/,

            // Keep all feed/profile classes
            /^feed-/, /^composer-/, /^compose-/, /^sidebar-/,
            /^profile-/, /^badge/, /^avatar/,

            // Keep all CSS variables and pseudo-elements
            /^--/, /::before/, /::after/,

            // Keep state patterns
            /^is-/, /^has-/, /hover/, /focus/, /active/,
        ],
    },

    // Keep font-face declarations
    fontFace: true,

    // Keep keyframes
    keyframes: true,

    // Keep CSS variables
    variables: true,
};
`;

// Write new config
const backupPath = path.join(projectRoot, 'purgecss.config.js.backup');
const configPath = path.join(projectRoot, 'purgecss.config.js');

// Backup existing config
if (fs.existsSync(configPath)) {
    fs.copyFileSync(configPath, backupPath);
    console.log(`✓ Backed up existing config to: purgecss.config.js.backup`);
}

// Write new config
fs.writeFileSync(configPath, configTemplate);
console.log(`✓ Generated new config with ${cssFiles.length} CSS files`);
console.log('');
console.log('Config file: purgecss.config.js');
