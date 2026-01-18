/**
 * Copy APK to downloads folder
 * Copies the built APK to the website's downloads folder for direct distribution
 */

const fs = require('fs');
const path = require('path');

// Paths
const APK_SOURCE = path.join(__dirname, '..', 'android', 'app', 'build', 'outputs', 'apk', 'debug', 'app-debug.apk');
const APK_RELEASE_SOURCE = path.join(__dirname, '..', 'android', 'app', 'build', 'outputs', 'apk', 'release', 'app-release-unsigned.apk');
const DOWNLOADS_DEST = path.join(__dirname, '..', '..', 'httpdocs', 'downloads');
const APK_DEST_NAME = 'nexus-latest.apk';

console.log('=================================');
console.log('  NEXUS APK Copy Script');
console.log('=================================\n');

// Ensure downloads directory exists
if (!fs.existsSync(DOWNLOADS_DEST)) {
    console.log('Creating downloads directory...');
    fs.mkdirSync(DOWNLOADS_DEST, { recursive: true });
}

// Try release APK first, fall back to debug
let sourceApk = APK_RELEASE_SOURCE;
let buildType = 'release';

if (!fs.existsSync(sourceApk)) {
    sourceApk = APK_SOURCE;
    buildType = 'debug';
}

if (!fs.existsSync(sourceApk)) {
    console.error('ERROR: No APK found!');
    console.error('Expected locations:');
    console.error('  - ' + APK_RELEASE_SOURCE);
    console.error('  - ' + APK_SOURCE);
    console.error('\nRun the build first:');
    console.error('  npm run build:android');
    process.exit(1);
}

// Copy the APK
const destPath = path.join(DOWNLOADS_DEST, APK_DEST_NAME);
console.log(`Copying ${buildType} APK...`);
console.log(`  From: ${sourceApk}`);
console.log(`  To:   ${destPath}`);

try {
    fs.copyFileSync(sourceApk, destPath);

    const stats = fs.statSync(destPath);
    const sizeMB = (stats.size / (1024 * 1024)).toFixed(2);

    console.log('\n  SUCCESS!\n');
    console.log(`  File size: ${sizeMB} MB`);
    console.log(`  Build type: ${buildType}`);
    console.log(`\n  Download URL: /downloads/${APK_DEST_NAME}`);
    console.log('=================================\n');
} catch (error) {
    console.error('ERROR copying APK:', error.message);
    process.exit(1);
}
