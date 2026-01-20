/**
 * Service Navigation Diagnostic Script
 * Run this in browser console to diagnose why service navigation isn't visible
 *
 * Usage: Copy this entire script and paste into Chrome DevTools Console
 */

console.log('='.repeat(60));
console.log('SERVICE NAVIGATION DIAGNOSTIC');
console.log('='.repeat(60));
console.log('');

// Find the service navigation element
const serviceNav = document.querySelector('.civicone-service-navigation');
const serviceNavList = document.querySelector('.civicone-service-navigation__list');
const serviceNavItems = document.querySelectorAll('.civicone-service-navigation__item');
const header = document.querySelector('.civicone-header');

// Check if elements exist
console.log('1. ELEMENT EXISTENCE:');
console.log('---------------------');
console.log('✅ .civicone-header exists:', !!header);
console.log('✅ .civicone-service-navigation exists:', !!serviceNav);
console.log('✅ .civicone-service-navigation__list exists:', !!serviceNavList);
console.log('✅ .civicone-service-navigation__item count:', serviceNavItems.length);
console.log('');

// Check computed styles
if (serviceNav) {
    const navStyles = window.getComputedStyle(serviceNav);
    console.log('2. SERVICE NAV COMPUTED STYLES:');
    console.log('-------------------------------');
    console.log('   display:', navStyles.display);
    console.log('   visibility:', navStyles.visibility);
    console.log('   opacity:', navStyles.opacity);
    console.log('   height:', navStyles.height);
    console.log('   position:', navStyles.position);
    console.log('   z-index:', navStyles.zIndex);
    console.log('');
}

if (serviceNavList) {
    const listStyles = window.getComputedStyle(serviceNavList);
    console.log('3. SERVICE NAV LIST COMPUTED STYLES:');
    console.log('------------------------------------');
    console.log('   display:', listStyles.display);
    console.log('   visibility:', listStyles.visibility);
    console.log('   opacity:', listStyles.opacity);
    console.log('   height:', listStyles.height);
    console.log('');
}

// Check individual items
if (serviceNavItems.length > 0) {
    console.log('4. SERVICE NAV ITEMS:');
    console.log('---------------------');
    serviceNavItems.forEach((item, index) => {
        const itemStyles = window.getComputedStyle(item);
        const link = item.querySelector('.civicone-service-navigation__link');
        const linkText = link ? link.textContent.trim() : '(no link)';

        console.log(`   Item ${index + 1}: "${linkText}"`);
        console.log(`      display: ${itemStyles.display}`);
        console.log(`      visibility: ${itemStyles.visibility}`);
        console.log(`      opacity: ${itemStyles.opacity}`);
        console.log(`      color: ${itemStyles.color}`);
        console.log(`      background: ${itemStyles.backgroundColor}`);
        console.log(`      height: ${itemStyles.height}`);
        console.log('');
    });
} else {
    console.log('4. SERVICE NAV ITEMS:');
    console.log('---------------------');
    console.log('   ❌ NO ITEMS FOUND - List is empty!');
    console.log('');
}

// Check for overlapping elements
console.log('5. OVERLAPPING ELEMENTS:');
console.log('------------------------');
if (header) {
    const headerRect = header.getBoundingClientRect();
    console.log('   Header position:', headerRect);

    // Check what's at the header's position
    const centerX = headerRect.left + (headerRect.width / 2);
    const centerY = headerRect.top + (headerRect.height / 2);
    const elementAtCenter = document.elementFromPoint(centerX, centerY);

    console.log('   Element at header center:', elementAtCenter ? elementAtCenter.className : 'null');

    if (elementAtCenter && !elementAtCenter.closest('.civicone-header')) {
        console.log('   ⚠️  WARNING: Another element is covering the header!');
        console.log('      Covering element:', elementAtCenter);
    }
}
console.log('');

// Check data-layout attribute
console.log('6. DATA-LAYOUT ATTRIBUTE:');
console.log('-------------------------');
const htmlElement = document.documentElement;
const dataLayout = htmlElement.getAttribute('data-layout');
console.log('   data-layout:', dataLayout);
if (dataLayout !== 'civicone') {
    console.log('   ❌ ERROR: data-layout should be "civicone", not "' + dataLayout + '"');
} else {
    console.log('   ✅ Correct: data-layout="civicone"');
}
console.log('');

// Check CSS files loaded
console.log('7. CSS FILES LOADED:');
console.log('--------------------');
const cssFiles = Array.from(document.styleSheets)
    .filter(sheet => sheet.href)
    .filter(sheet => sheet.href.includes('civicone-header'))
    .map(sheet => sheet.href);

if (cssFiles.length > 0) {
    console.log('   ✅ civicone-header CSS loaded:', cssFiles.length);
    cssFiles.forEach(file => console.log('      -', file));
} else {
    console.log('   ❌ ERROR: No civicone-header CSS files found!');
}
console.log('');

// Summary and recommendations
console.log('='.repeat(60));
console.log('SUMMARY & RECOMMENDATIONS:');
console.log('='.repeat(60));

if (!serviceNav) {
    console.log('❌ CRITICAL: .civicone-service-navigation element not found');
    console.log('   → Check if site-header.php is being included');
} else if (!serviceNavList) {
    console.log('❌ CRITICAL: .civicone-service-navigation__list element not found');
    console.log('   → Check service-navigation.php partial');
} else if (serviceNavItems.length === 0) {
    console.log('❌ CRITICAL: Service navigation list is EMPTY (no items)');
    console.log('   → Check if $navItems array in service-navigation.php is populated');
    console.log('   → Check if foreach loop is executing');
} else {
    const listStyles = window.getComputedStyle(serviceNavList);
    if (listStyles.display === 'none') {
        console.log('❌ ISSUE: List has display: none');
        console.log('   → Check media query - might be in mobile mode');
        console.log('   → Current viewport width:', window.innerWidth);
        console.log('   → Needs min-width: 768px for desktop nav');
    } else if (listStyles.visibility === 'hidden') {
        console.log('❌ ISSUE: List has visibility: hidden');
        console.log('   → Check CSS for visibility rules');
    } else {
        console.log('✅ Elements exist and have correct styles');
        console.log('   → If still not visible, check:');
        console.log('      1. Color contrast (text color vs background)');
        console.log('      2. Overlapping elements (see section 5 above)');
        console.log('      3. Clear browser cache (Ctrl+F5)');
    }
}

console.log('');
console.log('='.repeat(60));
console.log('Copy this output and share with developer');
console.log('='.repeat(60));
