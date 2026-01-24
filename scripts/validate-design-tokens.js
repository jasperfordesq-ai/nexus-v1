#!/usr/bin/env node

/**
 * Design Tokens Validation Script
 *
 * This script validates that design-tokens.min.css is not corrupted.
 * Run this after CSS builds to catch issues immediately.
 *
 * Usage: node scripts/validate-design-tokens.js
 */

const fs = require('fs');
const path = require('path');

const designTokensPath = path.join(__dirname, '..', 'httpdocs', 'assets', 'css', 'design-tokens.min.css');

function validateDesignTokens() {
    console.log('🔍 Validating design tokens...\n');

    // Check if file exists
    if (!fs.existsSync(designTokensPath)) {
        console.error('❌ ERROR: design-tokens.min.css does not exist!');
        process.exit(1);
    }

    // Check file size
    const stats = fs.statSync(designTokensPath);
    const sizeKB = (stats.size / 1024).toFixed(1);

    if (stats.size < 10000) { // Less than 10KB is definitely wrong
        console.error(`❌ ERROR: design-tokens.min.css is corrupted!`);
        console.error(`   File size: ${sizeKB}KB (expected ~29KB)`);
        console.error(`   This means PurgeCSS removed all the CSS variables.`);
        console.error(`\n💡 Fix: Run 'npm run minify:css' to rebuild`);
        process.exit(1);
    }

    // Check content
    const content = fs.readFileSync(designTokensPath, 'utf8');

    // Look for key CSS variables
    const requiredVariables = [
        '--space-0',
        '--space-4',
        '--color-primary-500',
    ];

    const missingVars = [];
    for (const varName of requiredVariables) {
        if (!content.includes(varName)) {
            missingVars.push(varName);
        }
    }

    if (missingVars.length > 0) {
        console.error(`❌ ERROR: design-tokens.min.css is missing required variables!`);
        console.error(`   Missing: ${missingVars.join(', ')}`);
        console.error(`   File size: ${sizeKB}KB`);
        console.error(`\n💡 Fix: Run 'npm run minify:css' to rebuild`);
        process.exit(1);
    }

    // All good!
    console.log(`✅ design-tokens.min.css is valid`);
    console.log(`   File size: ${sizeKB}KB`);
    console.log(`   Contains all required CSS variables`);
    console.log('');
}

validateDesignTokens();
