/**
 * fix-context-mocks.mjs
 *
 * Adds missing @/contexts hook stubs to test files that mock @/contexts
 * but don't include all the hooks that components commonly use.
 *
 * Run: node scripts/fix-context-mocks.mjs
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SRC_DIR = path.resolve(__dirname, '../src');

// Default stubs for each hook — only added if the test file doesn't already include it
const STUBS = {
  useTheme: `  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),`,
  useNotifications: `  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),`,
  usePusher: `  usePusher: () => ({ channel: null, isConnected: false }),`,
  usePusherOptional: `  usePusherOptional: () => null,`,
  useCookieConsent: `  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),`,
  readStoredConsent: `  readStoredConsent: () => null,`,
  useMenuContext: `  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),`,
  useFeature: `  useFeature: vi.fn(() => true),`,
  useModule: `  useModule: vi.fn(() => true),`,
  useAuth: `  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),`,
  useToast: `  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),`,
  useTenant: `  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),`,
};

// Find all test files
function findTestFiles(dir) {
  const results = [];
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      results.push(...findTestFiles(fullPath));
    } else if (/\.(test|spec)\.(tsx?|jsx?)$/.test(entry.name)) {
      results.push(fullPath);
    }
  }
  return results;
}

let fixed = 0;
let skipped = 0;

const testFiles = findTestFiles(SRC_DIR);

for (const filePath of testFiles) {
  const content = fs.readFileSync(filePath, 'utf-8');

  // Only process files that mock @/contexts with a factory
  if (!content.includes("vi.mock('@/contexts'") && !content.includes('vi.mock("@/contexts"')) {
    skipped++;
    continue;
  }

  // Find what's already in the file
  const missingStubs = [];
  for (const [hookName, stub] of Object.entries(STUBS)) {
    // Check if the hook is already defined in the mock (or anywhere in the file)
    if (!content.includes(hookName + ':') && !content.includes(hookName + ' :')) {
      missingStubs.push(stub);
    }
  }

  if (missingStubs.length === 0) {
    skipped++;
    continue;
  }

  // Find the vi.mock('@/contexts', ...) block and add missing stubs before the closing }));
  // Pattern: find the mock block end — })); after vi.mock('@/contexts'
  const mockPattern = /vi\.mock\(['"]@\/contexts['"]\s*,\s*\(\s*\)\s*=>\s*\(\s*\{([\s\S]*?)\}\s*\)\s*\)\s*;/;
  const match = content.match(mockPattern);

  if (!match) {
    // Try alternative pattern without outer parens
    const alt = /vi\.mock\(['"]@\/contexts['"]\s*,\s*\(\s*\)\s*=>\s*\{([\s\S]*?)\}\s*\)\s*;/;
    const altMatch = content.match(alt);
    if (!altMatch) {
      console.log(`SKIP (no pattern match): ${path.relative(SRC_DIR, filePath)}`);
      skipped++;
      continue;
    }
  }

  // Insert missing stubs before the closing }));
  // We need to find the exact closing of the mock block
  // Look for the pattern })); or }); that closes the mock
  const newStubsStr = '\n' + missingStubs.join('\n');

  // Replace the mock block: find })); that closes vi.mock('@/contexts', ...)
  // This regex finds the specific closing sequence after @/contexts mock
  let newContent = content;

  // Strategy: find the mock call and its approximate end
  const contextMockIdx = content.indexOf("vi.mock('@/contexts'");
  if (contextMockIdx === -1) {
    const altIdx = content.indexOf('vi.mock("@/contexts"');
    if (altIdx === -1) {
      skipped++;
      continue;
    }
  }

  const startIdx = content.indexOf("vi.mock('@/contexts'") !== -1
    ? content.indexOf("vi.mock('@/contexts'")
    : content.indexOf('vi.mock("@/contexts"');

  // Find the matching closing }));  — scan from start, tracking brace depth
  let depth = 0;
  let inMock = false;
  let mockBodyStart = -1;
  let mockBodyEnd = -1;

  for (let i = startIdx; i < content.length; i++) {
    const ch = content[i];
    if (ch === '{') {
      depth++;
      if (depth === 1 && !inMock) {
        inMock = true;
        mockBodyStart = i;
      }
    } else if (ch === '}') {
      depth--;
      if (depth === 0 && inMock) {
        mockBodyEnd = i;
        break;
      }
    }
  }

  if (mockBodyEnd === -1) {
    console.log(`SKIP (no mock body end): ${path.relative(SRC_DIR, filePath)}`);
    skipped++;
    continue;
  }

  // Insert missing stubs before the closing }
  const before = content.slice(0, mockBodyEnd);
  const after = content.slice(mockBodyEnd);

  // Add a trailing comma to the last entry if needed
  const beforeTrimmed = before.trimEnd();
  const needsComma = !beforeTrimmed.endsWith(',') && !beforeTrimmed.endsWith('{');
  newContent = (needsComma ? before.trimEnd() + ',\n' : before) + newStubsStr + '\n' + after;

  fs.writeFileSync(filePath, newContent);
  console.log(`FIXED (${missingStubs.length} stubs): ${path.relative(SRC_DIR, filePath)}`);
  fixed++;
}

console.log(`\nDone: ${fixed} files fixed, ${skipped} skipped.`);

// Also fix @/components/ui mocks
const UI_STUBS = {
  GlassCard: `  GlassCard: ({ children, className }: Record<string, unknown>) => children as never,`,
  GlassButton: `  GlassButton: ({ children }: Record<string, unknown>) => children as never,`,
  GlassInput: `  GlassInput: () => null,`,
  BackToTop: `  BackToTop: () => null,`,
  AlgorithmLabel: `  AlgorithmLabel: () => null,`,
  ImagePlaceholder: `  ImagePlaceholder: () => null,`,
  DynamicIcon: `  DynamicIcon: () => null,`,
  ICON_MAP: `  ICON_MAP: {},`,
  ICON_NAMES: `  ICON_NAMES: [],`,
  ListingSkeleton: `  ListingSkeleton: () => null,`,
  MemberCardSkeleton: `  MemberCardSkeleton: () => null,`,
  StatCardSkeleton: `  StatCardSkeleton: () => null,`,
  EventCardSkeleton: `  EventCardSkeleton: () => null,`,
  GroupCardSkeleton: `  GroupCardSkeleton: () => null,`,
  ConversationSkeleton: `  ConversationSkeleton: () => null,`,
  ExchangeCardSkeleton: `  ExchangeCardSkeleton: () => null,`,
  NotificationSkeleton: `  NotificationSkeleton: () => null,`,
  ProfileHeaderSkeleton: `  ProfileHeaderSkeleton: () => null,`,
  SkeletonList: `  SkeletonList: () => null,`,
};

console.log('\n=== Fixing @/components/ui mocks ===');
let uiFixed = 0;

for (const filePath of testFiles) {
  const content = fs.readFileSync(filePath, 'utf-8');
  
  if (!content.includes("vi.mock('@/components/ui'") && !content.includes('vi.mock("@/components/ui"')) {
    continue;
  }
  
  const missingUiStubs = [];
  for (const [name, stub] of Object.entries(UI_STUBS)) {
    if (!content.includes(name + ':') && !content.includes(name + ' :') && !content.includes(`'${name}'`) && !content.includes(`"${name}"`)) {
      missingUiStubs.push(stub);
    }
  }
  
  if (missingUiStubs.length === 0) continue;
  
  // Find the ui mock block and insert missing stubs
  const uiMockIdx = content.indexOf("vi.mock('@/components/ui'") !== -1
    ? content.indexOf("vi.mock('@/components/ui'")
    : content.indexOf('vi.mock("@/components/ui"');
  
  if (uiMockIdx === -1) continue;
  
  // Find matching closing brace
  let depth2 = 0;
  let inMock2 = false;
  let mockBodyEnd2 = -1;
  
  for (let i = uiMockIdx; i < content.length; i++) {
    const ch = content[i];
    if (ch === '{') {
      depth2++;
      if (depth2 === 1 && !inMock2) {
        inMock2 = true;
      }
    } else if (ch === '}') {
      depth2--;
      if (depth2 === 0 && inMock2) {
        mockBodyEnd2 = i;
        break;
      }
    }
  }
  
  if (mockBodyEnd2 === -1) continue;
  
  const newStubsStr2 = '\n' + missingUiStubs.join('\n');
  const before2 = content.slice(0, mockBodyEnd2);
  const after2 = content.slice(mockBodyEnd2);
  const beforeTrimmed2 = before2.trimEnd();
  const needsComma2 = !beforeTrimmed2.endsWith(',') && !beforeTrimmed2.endsWith('{');
  const newContent2 = (needsComma2 ? before2.trimEnd() + ',\n' : before2) + newStubsStr2 + '\n' + after2;
  
  fs.writeFileSync(filePath, newContent2);
  console.log(`FIXED UI (${missingUiStubs.length} stubs): ${path.relative(SRC_DIR, filePath)}`);
  uiFixed++;
}

console.log(`\nUI fixes: ${uiFixed} files fixed.`);
