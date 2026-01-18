#!/usr/bin/env node

/**
 * Image Optimization Script
 *
 * Converts PNG/JPG images to WebP format for significant size savings.
 * Creates WebP versions alongside originals (doesn't delete originals).
 *
 * Usage: node scripts/optimize-images.js
 */

const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

// Directories to process
const directories = [
    'httpdocs/assets/img',
    'httpdocs/assets/images',
    'httpdocs/uploads/avatars',
    'httpdocs/uploads/resources',
    'httpdocs/uploads/groups',
    'httpdocs/uploads/posts',
];

// Track stats
let totalOriginal = 0;
let totalConverted = 0;
let fileCount = 0;
let errorCount = 0;

async function processImage(filePath) {
    const ext = path.extname(filePath).toLowerCase();
    if (!['.png', '.jpg', '.jpeg'].includes(ext)) return;

    const webpPath = filePath.replace(/\.(png|jpg|jpeg)$/i, '.webp');

    // Skip if WebP already exists and is newer
    if (fs.existsSync(webpPath)) {
        const origStat = fs.statSync(filePath);
        const webpStat = fs.statSync(webpPath);
        if (webpStat.mtime >= origStat.mtime) {
            return; // Already converted
        }
    }

    try {
        const originalSize = fs.statSync(filePath).size;

        await sharp(filePath)
            .webp({ quality: 85 })
            .toFile(webpPath);

        const newSize = fs.statSync(webpPath).size;
        const savings = ((originalSize - newSize) / originalSize * 100).toFixed(1);

        totalOriginal += originalSize;
        totalConverted += newSize;
        fileCount++;

        console.log(`✅ ${path.basename(filePath)}: ${(originalSize/1024).toFixed(0)}KB → ${(newSize/1024).toFixed(0)}KB (${savings}% smaller)`);
    } catch (err) {
        errorCount++;
        console.error(`❌ ${path.basename(filePath)}: ${err.message}`);
    }
}

async function processDirectory(dir) {
    const fullPath = path.join(__dirname, '..', dir);

    if (!fs.existsSync(fullPath)) {
        console.log(`⚠️  Directory not found: ${dir}`);
        return;
    }

    console.log(`\n📁 Processing: ${dir}`);
    console.log('-'.repeat(50));

    const entries = fs.readdirSync(fullPath, { withFileTypes: true });

    for (const entry of entries) {
        const entryPath = path.join(fullPath, entry.name);

        if (entry.isDirectory()) {
            // Recursively process subdirectories
            await processDirectoryRecursive(entryPath);
        } else if (entry.isFile()) {
            await processImage(entryPath);
        }
    }
}

async function processDirectoryRecursive(dirPath) {
    if (!fs.existsSync(dirPath)) return;

    const entries = fs.readdirSync(dirPath, { withFileTypes: true });

    for (const entry of entries) {
        const entryPath = path.join(dirPath, entry.name);

        if (entry.isDirectory()) {
            await processDirectoryRecursive(entryPath);
        } else if (entry.isFile()) {
            await processImage(entryPath);
        }
    }
}

async function main() {
    console.log('🖼️  Image Optimization Script');
    console.log('='.repeat(50));
    console.log('Converting PNG/JPG to WebP format...\n');

    for (const dir of directories) {
        await processDirectory(dir);
    }

    console.log('\n' + '='.repeat(50));
    console.log('📊 Summary:');
    console.log(`   Files converted: ${fileCount}`);
    console.log(`   Errors: ${errorCount}`);
    console.log(`   Original size: ${(totalOriginal / 1024 / 1024).toFixed(2)} MB`);
    console.log(`   WebP size: ${(totalConverted / 1024 / 1024).toFixed(2)} MB`);
    console.log(`   Savings: ${((totalOriginal - totalConverted) / 1024 / 1024).toFixed(2)} MB (${((totalOriginal - totalConverted) / totalOriginal * 100).toFixed(1)}%)`);
    console.log('='.repeat(50));

    console.log('\n⚠️  Note: Original files are kept. Update your code to serve WebP with fallback.');
    console.log('   Example: <picture><source srcset="image.webp" type="image/webp"><img src="image.jpg"></picture>');
}

main().catch(err => {
    console.error('Script failed:', err);
    process.exit(1);
});
