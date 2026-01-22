#!/usr/bin/env node

/**
 * JavaScript Minification Script
 *
 * Minifies all JS files using Terser for maximum compression.
 *
 * Usage: node scripts/minify-js.js
 */

const fs = require('fs');
const path = require('path');
const { minify } = require('terser');

// JS files to minify (largest files first based on analysis)
// Updated 2026-01-19: Added groups-edit-overlay.js
const jsFiles = [
    'social-interactions.js',
    'nexus-mobile.js',
    'nexus-pwa.js',
    'nexus-auth-handler.js',
    'nexus-biometric.js',
    'nexus-native.js',
    'civicone-native.js',
    'civicone-webauthn.js',
    'nexus-turbo.js',
    'nexus-shared-transitions.js',
    'nexus-offline-queue.js',
    'nexus-ui.js',
    'civicone-pwa.js',
    'nexus-native-push.js',
    'nexus-capacitor-bridge.js',
    'gravity-wrapper.js',
    'nexus-mapbox.js',
    'nexus-native-features.js',
    'notifications.js',
    'civicone-mobile.js',
    'nexus-transitions.js',
    'nexus-loading-fix.js',
    'nexus-resize-handler.js',
    'modern-header-behavior.js',
    'layout-switch-helper.js',
    'nexus-instant-load.js',
    'pusher-client.js',
    'jGravity.js',
    'groups-edit-overlay.js',
    'federation-review-form.js',
    'civicone-members-directory.js',
    'toast-notifications.js',
    'page-transitions.js',
    'pull-to-refresh.js',
    'button-ripple.js',
    'form-validation.js',
    'avatar-placeholders.js',
    'scroll-progress.js',
    'fab-polish.js',
    'badge-animations.js',
    'error-states.js',
    'civicone-error-summary.js',
    'mobile-nav-v2.js',
];

const jsDir = path.join(__dirname, '../httpdocs/assets/js');

async function minifyAll() {
    console.log('🚀 Minifying JavaScript files with Terser...\n');

    let totalOriginal = 0;
    let totalMinified = 0;
    let successCount = 0;
    let errorCount = 0;

    for (const file of jsFiles) {
        const inputPath = path.join(jsDir, file);
        const outputPath = path.join(jsDir, file.replace('.js', '.min.js'));

        if (!fs.existsSync(inputPath)) {
            console.log(`⚠️  Skipping ${file} (not found)`);
            continue;
        }

        try {
            const original = fs.readFileSync(inputPath, 'utf8');

            const result = await minify(original, {
                compress: {
                    drop_console: false, // Keep console for debugging
                    drop_debugger: true,
                    dead_code: true,
                    unused: true,
                },
                mangle: {
                    toplevel: false, // Don't mangle top-level names
                },
                format: {
                    comments: false,
                },
            });

            if (result.code) {
                fs.writeFileSync(outputPath, result.code);

                const originalSize = Buffer.byteLength(original, 'utf8');
                const minifiedSize = Buffer.byteLength(result.code, 'utf8');
                const savings = ((originalSize - minifiedSize) / originalSize * 100).toFixed(1);

                totalOriginal += originalSize;
                totalMinified += minifiedSize;
                successCount++;

                console.log(`✅ ${file}: ${(originalSize/1024).toFixed(1)}KB → ${(minifiedSize/1024).toFixed(1)}KB (${savings}% smaller)`);
            }
        } catch (err) {
            console.log(`❌ ${file}: Error - ${err.message}`);
            errorCount++;
        }
    }

    console.log('\n' + '='.repeat(60));
    console.log(`📊 Total: ${(totalOriginal/1024).toFixed(1)}KB → ${(totalMinified/1024).toFixed(1)}KB`);
    console.log(`💾 Savings: ${((totalOriginal - totalMinified)/1024).toFixed(1)}KB (${((totalOriginal - totalMinified)/totalOriginal * 100).toFixed(1)}%)`);
    console.log(`✅ Success: ${successCount} files | ❌ Errors: ${errorCount} files`);
    console.log('='.repeat(60));
}

minifyAll().catch(console.error);
