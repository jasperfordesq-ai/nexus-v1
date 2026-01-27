#!/usr/bin/env node

/**
 * Modern CSS Color Linter
 *
 * Phase 4 Guardrail: Prevents reintroduction of hardcoded colors in Modern theme CSS.
 * Phase 4.1 Update: Added summary tables, baseline comparison, and better CLI options.
 *
 * Modes:
 * - STRICT: Phase 2 tokenized files - must have 0 violations (errors)
 * - WARN: Other Modern CSS files - reports but doesn't fail (warnings)
 *
 * Rules:
 * 1. No hex colors (#xxx, #xxxxxx) except in token files
 * 2. No rgba/rgb/hsl/hsla literals except:
 *    - Token files (design-tokens.css, modern-theme-tokens.css)
 *    - Dynamic patterns: rgba(var(--settings-*), alpha)
 *
 * Usage:
 *   node scripts/lint-modern-colors.js              # Normal mode (strict + warn)
 *   node scripts/lint-modern-colors.js --strict     # Only check strict files
 *   node scripts/lint-modern-colors.js --all        # Check all files as errors
 *   node scripts/lint-modern-colors.js --top 10     # Show top 10 files (default 20)
 *   node scripts/lint-modern-colors.js --filter polls  # Filter to files matching "polls"
 *   node scripts/lint-modern-colors.js --json       # Output JSON to stdout
 *   node scripts/lint-modern-colors.js --baseline   # Compare against baseline, fail if increased
 *   node scripts/lint-modern-colors.js --update-baseline  # Update baseline file with current counts
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob');

// Parse CLI args
const args = process.argv.slice(2);
const strictOnly = args.includes('--strict');
const checkAll = args.includes('--all');
const jsonOutput = args.includes('--json');
const useBaseline = args.includes('--baseline');
const updateBaseline = args.includes('--update-baseline');

// Parse --top N
let topN = 20;
const topIndex = args.indexOf('--top');
if (topIndex !== -1 && args[topIndex + 1]) {
    topN = parseInt(args[topIndex + 1], 10) || 20;
}

// Parse --filter <pattern>
let filterPattern = null;
const filterIndex = args.indexOf('--filter');
if (filterIndex !== -1 && args[filterIndex + 1]) {
    filterPattern = args[filterIndex + 1].toLowerCase();
}

// Max findings to display (prevents overwhelming output)
const MAX_FINDINGS_DISPLAY = 200;

// Baseline file path
const BASELINE_PATH = path.join(__dirname, 'lint-modern-colors.baseline.json');

// Configuration
const CONFIG = {
    // Files to check (Modern theme only)
    include: [
        'httpdocs/assets/css/**/*.css'
    ],
    // Files to exclude entirely
    exclude: [
        '**/*.min.css',
        '**/bundles/**',
        '**/_archived/**',
        '**/vendor/**',
        '**/civicone/**',
        '**/civicone-*.css',
        // Token files are allowed to have hardcoded colors
        '**/design-tokens.css',
        '**/desktop-design-tokens.css',
        '**/mobile-design-tokens.css',
        '**/modern-theme-tokens.css',
        // Backup files
        '**/*.backup'
    ],
    // Files tokenized in Phase 2 - STRICT mode (errors, must pass)
    strictFiles: [
        'federation.css',
        'volunteering.css',
        'scattered-singles.css',
        'nexus-home.css',
        'nexus-groups.css',
        'profile-holographic.css',
        'dashboard.css'
    ],
    // Allowed dynamic rgba patterns (regex)
    allowedDynamicPatterns: [
        /rgba\s*\(\s*var\s*\(\s*--settings-/i,
        /rgba\s*\(\s*var\s*\(\s*--htb-primary-rgb/i,
        /rgba\s*\(\s*var\s*\(\s*--color-primary-rgb/i,
        /rgba\s*\(\s*var\s*\(\s*--holo-primary-rgb/i,
        /rgba\s*\(\s*var\s*\(\s*--privacy-theme-rgb/i
    ]
};

// Patterns to detect
const PATTERNS = {
    hex: {
        regex: /#(?:[0-9a-fA-F]{3}){1,2}(?![0-9a-fA-F])/g,
        message: 'Hardcoded hex color. Use var(--color-*) from design-tokens.css',
        strictOnly: false  // Hex NOT enforced in strict files (Phase 2 only did rgba)
    },
    rgba: {
        regex: /rgba\s*\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[\d.]+\s*\)/gi,
        message: 'Hardcoded rgba(). Use var(--effect-*) from modern-theme-tokens.css',
        strictOnly: true   // rgba IS enforced in strict files (Phase 2 tokenized)
    },
    rgb: {
        regex: /rgb\s*\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)/gi,
        message: 'Hardcoded rgb(). Use var(--color-*) from design-tokens.css',
        strictOnly: true   // rgb IS enforced in strict files (Phase 2 tokenized)
    },
    hsl: {
        regex: /hsla?\s*\(\s*\d+\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(?:,\s*[\d.]+)?\s*\)/gi,
        message: 'Hardcoded hsl/hsla(). Use a CSS variable instead',
        strictOnly: true   // hsl IS enforced in strict files (Phase 2 tokenized)
    }
};

// Colors to ignore (common safe values)
const IGNORED_HEX = [
    '#000', '#000000',  // Pure black
    '#fff', '#ffffff',  // Pure white
    '#transparent'      // Not actually hex
];

// RGB values to ignore (known exceptions from Phase 2)
const IGNORED_RGB = [
    'rgb(40, 15, 5)',   // Whiskey gradient dark (scattered-singles.css)
    'rgb(20, 10, 5)'    // Whiskey gradient darker (scattered-singles.css)
];

class ModernColorLinter {
    constructor() {
        this.errors = [];      // Strict file violations (fail build)
        this.warnings = [];    // Non-strict file violations (informational)
        this.filesChecked = 0;
        this.errorCount = 0;
        this.warningCount = 0;
        // Type-specific counts
        this.typeCounts = {
            hex: 0,
            rgba: 0,
            rgb: 0,
            hsl: 0
        };
    }

    getFiles() {
        const files = [];
        const baseDir = path.join(__dirname, '..');

        for (const pattern of CONFIG.include) {
            const matches = glob.sync(pattern, {
                cwd: baseDir,
                ignore: CONFIG.exclude,
                nodir: true
            });
            files.push(...matches.map(f => path.join(baseDir, f)));
        }

        return [...new Set(files)];
    }

    isStrictFile(filePath) {
        const basename = path.basename(filePath);
        return CONFIG.strictFiles.includes(basename);
    }

    isAllowedDynamicPattern(match) {
        for (const pattern of CONFIG.allowedDynamicPatterns) {
            if (pattern.test(match)) {
                return true;
            }
        }
        return false;
    }

    lintFile(filePath) {
        const content = fs.readFileSync(filePath, 'utf8');
        const lines = content.split('\n');
        const relativePath = path.relative(path.join(__dirname, '..'), filePath).replace(/\\/g, '/');
        const isStrict = this.isStrictFile(filePath) || checkAll;
        const fileIssues = [];

        // Skip non-strict files in --strict mode
        if (strictOnly && !this.isStrictFile(filePath)) {
            return;
        }

        // Apply filter if specified
        if (filterPattern && !relativePath.toLowerCase().includes(filterPattern)) {
            return;
        }

        lines.forEach((line, index) => {
            const lineNum = index + 1;

            // Skip comments
            if (line.trim().startsWith('/*') || line.trim().startsWith('*') || line.trim().startsWith('//')) {
                return;
            }

            // Skip lines with known comment markers
            if (line.includes('DUPLICATE REMOVED') || line.includes('defined earlier')) {
                return;
            }

            // Check for hex colors (warning only for strict files, since Phase 2 didn't tokenize hex)
            const hexMatches = line.matchAll(PATTERNS.hex.regex);
            for (const hexMatch of hexMatches) {
                const hex = hexMatch[0].toLowerCase();
                if (!IGNORED_HEX.includes(hex)) {
                    fileIssues.push({
                        line: lineNum,
                        column: hexMatch.index + 1,
                        rule: 'no-hardcoded-hex',
                        type: 'hex',
                        message: PATTERNS.hex.message,
                        value: hexMatch[0],
                        isStrictRule: false  // Hex not strictly enforced (future phase)
                    });
                    this.typeCounts.hex++;
                }
            }

            // Check for rgba (strictly enforced - Phase 2 tokenized)
            const rgbaMatches = line.matchAll(PATTERNS.rgba.regex);
            for (const rgbaMatch of rgbaMatches) {
                if (!this.isAllowedDynamicPattern(rgbaMatch[0])) {
                    fileIssues.push({
                        line: lineNum,
                        column: rgbaMatch.index + 1,
                        rule: 'no-hardcoded-rgba',
                        type: 'rgba',
                        message: PATTERNS.rgba.message,
                        value: rgbaMatch[0],
                        isStrictRule: true
                    });
                    this.typeCounts.rgba++;
                }
            }

            // Check for rgb (strictly enforced - Phase 2 tokenized)
            const rgbMatches = line.matchAll(PATTERNS.rgb.regex);
            for (const rgbMatch of rgbMatches) {
                // Normalize for comparison (remove extra spaces)
                const normalized = rgbMatch[0].replace(/\s+/g, ' ').toLowerCase();
                const isIgnored = IGNORED_RGB.some(ignored =>
                    ignored.replace(/\s+/g, ' ').toLowerCase() === normalized
                );
                if (!isIgnored) {
                    fileIssues.push({
                        line: lineNum,
                        column: rgbMatch.index + 1,
                        rule: 'no-hardcoded-rgb',
                        type: 'rgb',
                        message: PATTERNS.rgb.message,
                        value: rgbMatch[0],
                        isStrictRule: true
                    });
                    this.typeCounts.rgb++;
                }
            }

            // Check for hsl/hsla (strictly enforced - Phase 2 tokenized)
            const hslMatches = line.matchAll(PATTERNS.hsl.regex);
            for (const hslMatch of hslMatches) {
                fileIssues.push({
                    line: lineNum,
                    column: hslMatch.index + 1,
                    rule: 'no-hardcoded-hsl',
                    type: 'hsl',
                    message: PATTERNS.hsl.message,
                    value: hslMatch[0],
                    isStrictRule: true
                });
                this.typeCounts.hsl++;
            }
        });

        if (fileIssues.length > 0) {
            // Separate strict-rule issues from non-strict-rule issues
            const strictIssues = fileIssues.filter(i => i.isStrictRule);
            const nonStrictIssues = fileIssues.filter(i => !i.isStrictRule);

            // For strict files: strict-rule violations are errors, non-strict are warnings
            // For non-strict files: all violations are warnings
            if (isStrict && strictIssues.length > 0) {
                this.errors.push({
                    file: relativePath,
                    issues: strictIssues,
                    isStrict: true
                });
                this.errorCount += strictIssues.length;
            }

            // Non-strict rule issues (hex) or issues in non-strict files are warnings
            const warningIssues = isStrict ? nonStrictIssues : fileIssues;
            if (warningIssues.length > 0) {
                this.warnings.push({
                    file: relativePath,
                    issues: warningIssues,
                    isStrict: false
                });
                this.warningCount += warningIssues.length;
            }
        }

        this.filesChecked++;
    }

    loadBaseline() {
        if (fs.existsSync(BASELINE_PATH)) {
            try {
                return JSON.parse(fs.readFileSync(BASELINE_PATH, 'utf8'));
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    saveBaseline(data) {
        fs.writeFileSync(BASELINE_PATH, JSON.stringify(data, null, 2));
    }

    generateBaselineData() {
        const perFile = {};
        for (const warning of this.warnings) {
            if (warning.issues.length >= 20) {
                perFile[warning.file] = warning.issues.length;
            }
        }
        return {
            timestamp: new Date().toISOString(),
            totalWarnings: this.warningCount,
            totalErrors: this.errorCount,
            typeCounts: { ...this.typeCounts },
            filesWithWarnings: this.warnings.length,
            perFileWarnings: perFile
        };
    }

    outputJSON() {
        const result = {
            summary: {
                filesChecked: this.filesChecked,
                totalErrors: this.errorCount,
                totalWarnings: this.warningCount,
                typeCounts: this.typeCounts,
                strictFilesPassing: this.errorCount === 0
            },
            errors: this.errors,
            warnings: this.warnings.map(w => ({
                file: w.file,
                count: w.issues.length,
                // Only include first 10 issues per file in JSON
                issues: w.issues.slice(0, 10)
            }))
        };
        console.log(JSON.stringify(result, null, 2));
    }

    run() {
        // JSON mode: suppress all non-JSON output
        const log = jsonOutput ? () => {} : console.log.bind(console);

        log('🎨 Modern CSS Color Linter (Phase 4.1)');
        log('=' .repeat(50));
        log('');

        const files = this.getFiles();
        const mode = strictOnly ? 'strict-only' : checkAll ? 'all-as-errors' : 'normal';
        log(`Mode: ${mode}`);
        if (filterPattern) {
            log(`Filter: "${filterPattern}"`);
        }
        log(`📁 Checking ${files.length} CSS files...\n`);

        for (const file of files) {
            this.lintFile(file);
        }

        // JSON output mode
        if (jsonOutput) {
            this.outputJSON();
            return this.errorCount > 0 ? 1 : 0;
        }

        // Update baseline mode
        if (updateBaseline) {
            const baselineData = this.generateBaselineData();
            this.saveBaseline(baselineData);
            log(`\n📊 Baseline updated: ${BASELINE_PATH}`);
            log(`   Total warnings: ${this.warningCount}`);
            log(`   Files tracked: ${Object.keys(baselineData.perFileWarnings).length}`);
            return this.errorCount > 0 ? 1 : 0;
        }

        // ========== SUMMARY TABLE ==========
        log('\n📊 SUMMARY BY TYPE');
        log('┌─────────────┬───────────┐');
        log('│ Type        │ Count     │');
        log('├─────────────┼───────────┤');
        log(`│ hex         │ ${String(this.typeCounts.hex).padStart(9)} │`);
        log(`│ rgba        │ ${String(this.typeCounts.rgba).padStart(9)} │`);
        log(`│ rgb         │ ${String(this.typeCounts.rgb).padStart(9)} │`);
        log(`│ hsl/hsla    │ ${String(this.typeCounts.hsl).padStart(9)} │`);
        log('├─────────────┼───────────┤');
        log(`│ TOTAL       │ ${String(this.errorCount + this.warningCount).padStart(9)} │`);
        log('└─────────────┴───────────┘');

        // ========== ERRORS (Strict files) ==========
        if (this.errors.length > 0) {
            log(`\n❌ ERRORS (${this.errorCount}) - Phase 2 tokenized files must have 0 violations:\n`);
            let displayedFindings = 0;
            for (const fileError of this.errors) {
                log(`  📄 ${fileError.file}`);
                const maxPerFile = Math.min(5, MAX_FINDINGS_DISPLAY - displayedFindings);
                for (const issue of fileError.issues.slice(0, maxPerFile)) {
                    log(`     ${issue.line}:${issue.column} [${issue.rule}] ${issue.value}`);
                    displayedFindings++;
                }
                if (fileError.issues.length > maxPerFile) {
                    log(`     ... and ${fileError.issues.length - maxPerFile} more`);
                }
                if (displayedFindings >= MAX_FINDINGS_DISPLAY) {
                    log(`\n   (Output capped at ${MAX_FINDINGS_DISPLAY} findings)`);
                    break;
                }
            }
        }

        // ========== WARNINGS (Legacy files) ==========
        if (this.warnings.length > 0 && !strictOnly) {
            log(`\n⚠️  WARNINGS (${this.warningCount}) - Legacy files (informational):`);
            log(`   ${this.warnings.length} file(s) have hardcoded colors`);

            // Top N files by issue count
            const sorted = [...this.warnings].sort((a, b) => b.issues.length - a.issues.length);
            log(`\n   Top ${Math.min(topN, sorted.length)} files needing tokenization:`);
            log('   ┌─────────────────────────────────────────────────────┬───────┐');
            log('   │ File                                                │ Count │');
            log('   ├─────────────────────────────────────────────────────┼───────┤');
            for (const file of sorted.slice(0, topN)) {
                const fileName = file.file.length > 51 ? '...' + file.file.slice(-48) : file.file;
                log(`   │ ${fileName.padEnd(51)} │ ${String(file.issues.length).padStart(5)} │`);
            }
            log('   └─────────────────────────────────────────────────────┴───────┘');

            if (sorted.length > topN) {
                log(`   ... and ${sorted.length - topN} more files (use --top N to show more)`);
            }
        }

        // ========== BASELINE COMPARISON ==========
        let baselineExceeded = false;
        if (useBaseline) {
            const baseline = this.loadBaseline();
            if (baseline) {
                log('\n📈 BASELINE COMPARISON');
                log('┌──────────────────────┬───────────┬───────────┬──────────┐');
                log('│ Metric               │ Baseline  │ Current   │ Delta    │');
                log('├──────────────────────┼───────────┼───────────┼──────────┤');

                const warnDelta = this.warningCount - baseline.totalWarnings;
                const warnDeltaStr = warnDelta > 0 ? `+${warnDelta}` : String(warnDelta);
                log(`│ Total Warnings       │ ${String(baseline.totalWarnings).padStart(9)} │ ${String(this.warningCount).padStart(9)} │ ${warnDeltaStr.padStart(8)} │`);

                const filesDelta = this.warnings.length - baseline.filesWithWarnings;
                const filesDeltaStr = filesDelta > 0 ? `+${filesDelta}` : String(filesDelta);
                log(`│ Files with Warnings  │ ${String(baseline.filesWithWarnings).padStart(9)} │ ${String(this.warnings.length).padStart(9)} │ ${filesDeltaStr.padStart(8)} │`);

                log('└──────────────────────┴───────────┴───────────┴──────────┘');

                if (this.warningCount > baseline.totalWarnings) {
                    baselineExceeded = true;
                    log(`\n❌ BASELINE EXCEEDED: Warnings increased by ${warnDelta}`);
                    log('   Run cleanup or update baseline with --update-baseline');
                } else if (this.warningCount < baseline.totalWarnings) {
                    log(`\n✨ IMPROVEMENT: Warnings decreased by ${Math.abs(warnDelta)}!`);
                    log('   Consider updating baseline with --update-baseline');
                } else {
                    log('\n✅ Baseline maintained');
                }
            } else {
                log('\n⚠️  No baseline file found. Create one with --update-baseline');
            }
        }

        // ========== FINAL SUMMARY ==========
        log('\n' + '=' .repeat(50));

        if (this.errors.length === 0 && this.warnings.length === 0) {
            log('✅ No hardcoded colors found!');
            log(`   Checked ${this.filesChecked} files.`);
            return 0;
        }

        if (this.errors.length === 0 && !baselineExceeded) {
            log('✅ Phase 2 tokenized files are clean!');
            log(`   Strict files: 0 errors`);
            log(`   Legacy files: ${this.warningCount} warnings (informational)`);
            return 0;  // Warnings don't fail the build
        }

        if (this.errors.length > 0) {
            log(`❌ FAILED: ${this.errorCount} error(s) in ${this.errors.length} strict file(s)`);
            log('\n💡 To fix:');
            log('   1. For hex: Use var(--color-*) from design-tokens.css');
            log('   2. For rgba: Use var(--effect-*) from modern-theme-tokens.css');
            log('   3. See docs/modern-css-guardrails.md for guidance');
            return 1;  // Errors fail the build
        }

        if (baselineExceeded) {
            log(`❌ FAILED: Baseline exceeded (${this.warningCount} > ${this.loadBaseline().totalWarnings})`);
            return 1;
        }

        return 0;
    }
}

// Run
const linter = new ModernColorLinter();
const exitCode = linter.run();
process.exit(exitCode);
