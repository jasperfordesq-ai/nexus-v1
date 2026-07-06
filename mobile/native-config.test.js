// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const fs = require('fs');
const path = require('path');

const root = __dirname;

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function readJson(relativePath) {
  return JSON.parse(read(relativePath));
}

function readPngSize(relativePath) {
  const buffer = fs.readFileSync(path.join(root, relativePath));
  return {
    width: buffer.readUInt32BE(16),
    height: buffer.readUInt32BE(20),
  };
}

function listSourceFiles(relativeDir) {
  const dir = path.join(root, relativeDir);
  if (!fs.existsSync(dir)) return [];

  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const fullPath = path.join(dir, entry.name);
    const relativePath = path.relative(root, fullPath);
    if (entry.isDirectory()) {
      return listSourceFiles(relativePath);
    }
    return /\.(jsx?|tsx?)$/.test(entry.name) ? [relativePath] : [];
  });
}

describe('native app configuration', () => {
  it('uses HeroUI Native and native gesture/animation packages, not web HeroUI', () => {
    const pkg = readJson('package.json');
    const deps = { ...(pkg.dependencies ?? {}), ...(pkg.devDependencies ?? {}) };

    expect(deps['heroui-native']).toBeTruthy();
    expect(deps['uniwind']).toBeTruthy();
    expect(deps['react-native-reanimated']).toBeTruthy();
    expect(deps['react-native-gesture-handler']).toBeTruthy();
    expect(deps['react-native-worklets']).toBeTruthy();
    expect(deps['expo-font']).toBeTruthy();
    expect(deps['expo-system-ui']).toBeTruthy();
    expect(deps['@heroui/react']).toBeUndefined();
    expect(deps['@nextui-org/react']).toBeUndefined();
  });

  it('has no web HeroUI imports in mobile source files', () => {
    const sourceFiles = [
      ...listSourceFiles('app'),
      ...listSourceFiles('components'),
      ...listSourceFiles('lib'),
    ];
    const offenders = sourceFiles.filter((relativePath) => {
      const source = read(relativePath);
      return /@heroui\/(?!native)|@nextui|@nextui-org\/react|@heroui\/react/.test(source);
    });

    expect(offenders).toEqual([]);
  });

  it('keeps react-native-reanimated as the last Babel plugin', () => {
    const factory = require('./babel.config.js');
    const config = factory({ cache: jest.fn() });

    expect(config.presets).toContain('babel-preset-expo');
    expect(config.plugins.at(-1)).toBe('react-native-reanimated/plugin');
  });

  it('wraps Metro with Uniwind and transforms native ESM packages', () => {
    const metroConfig = read('metro.config.js');

    expect(metroConfig).toContain('withUniwindConfig(config');
    expect(metroConfig).toContain("cssEntryFile: './global.css'");
    expect(metroConfig).toContain("dtsFile: './uniwind-types.d.ts'");
    expect(metroConfig).toContain('heroui-native');
    expect(metroConfig).toContain('react-native-reanimated');
    expect(metroConfig).toContain('react-native-gesture-handler');
    expect(metroConfig).toContain('react-native-worklets');
    expect(metroConfig).toContain('uniwind');
    expect(metroConfig).toContain('inlineRequires: true');
  });

  it('loads Tailwind v4, Uniwind, and HeroUI Native styles from global CSS', () => {
    const globalCss = read('global.css');

    expect(globalCss).toContain("@import 'tailwindcss'");
    expect(globalCss).toContain("@import 'uniwind'");
    expect(globalCss).toContain("@import 'heroui-native/styles'");
    expect(globalCss).toContain("@source './node_modules/heroui-native/lib'");
  });

  it('keeps common native navigation labels translated', () => {
    const common = readJson('locales/en/common.json');

    expect(common.back).toBe('Back');
    expect(common.buttons.back).toBe('Back');
  });

  it('places native providers and system UI at the root', () => {
    const layout = read('app/_layout.tsx');
    const gestureIndex = layout.indexOf('<GestureHandlerRootView');
    const heroIndex = layout.indexOf('<HeroUINativeProvider>');
    const safeAreaIndex = layout.indexOf('<SafeAreaProvider>');
    // StatusBar now lives in <ThemedShell/> (mounted inside the provider stack)
    // and its style follows the active light/dark scheme.
    const themedShellIndex = layout.indexOf('<ThemedShell />');
    const statusIndex = layout.indexOf('<StatusBar style={');

    expect(gestureIndex).toBeGreaterThan(-1);
    expect(heroIndex).toBeGreaterThan(gestureIndex);
    expect(safeAreaIndex).toBeGreaterThan(heroIndex);
    // ThemedShell (which renders StatusBar) is mounted after SafeAreaProvider.
    expect(themedShellIndex).toBeGreaterThan(safeAreaIndex);
    expect(statusIndex).toBeGreaterThan(-1);
  });

  it('bundles native fonts and assets into Android release builds', () => {
    const app = readJson('app.json').expo;

    expect(app.plugins).toEqual(expect.arrayContaining(['expo-font']));
    expect(app.assetBundlePatterns).toEqual(expect.arrayContaining(['assets/**/*']));
    expect(app.icon).toBe('./assets/icon.png');
    expect(app.splash.image).toBe('./assets/splash.png');
    expect(app.android.adaptiveIcon.foregroundImage).toBe('./assets/adaptive-icon.png');
  });

  it('does not block cold starts on launch-time remote update checks', () => {
    const app = readJson('app.json').expo;
    const manifest = read('android/app/src/main/AndroidManifest.xml');

    expect(app.updates.enabled).toBe(true);
    expect(app.updates.checkAutomatically).toBe('ON_ERROR_RECOVERY');
    expect(app.updates.fallbackToCacheTimeout).toBe(0);
    expect(manifest).toContain('expo.modules.updates.EXPO_UPDATES_CHECK_ON_LAUNCH" android:value="ERROR_RECOVERY_ONLY"');
    expect(manifest).toContain('expo.modules.updates.EXPO_UPDATES_LAUNCH_WAIT_MS" android:value="0"');
  });

  it('paints a branded Android window while React is booting', () => {
    const styles = read('android/app/src/main/res/values/styles.xml');
    const launchBackground = read('android/app/src/main/res/drawable/ic_launcher_background.xml');

    expect(styles).toContain('<style name="AppTheme" parent="Theme.AppCompat.DayNight.NoActionBar">');
    expect(styles).toContain('<item name="android:windowBackground">@color/splashscreen_background</item>');
    expect(launchBackground).toContain('@color/splashscreen_background');
    expect(launchBackground).toContain('@drawable/splashscreen_logo');
  });

  it('keeps the Android notification icon in Expo-compatible dimensions', () => {
    const appConfig = require('./app.config.js')({ config: readJson('app.json').expo });
    const notificationsPlugin = appConfig.plugins.find((plugin) => {
      return Array.isArray(plugin) && plugin[0] === 'expo-notifications';
    });
    const notificationIcon = Array.isArray(notificationsPlugin)
      ? notificationsPlugin[1]?.icon
      : null;

    expect(notificationIcon).toBe('./assets/notification-icon.png');
    expect(readPngSize('assets/notification-icon.png')).toEqual({
      width: 96,
      height: 96,
    });
  });

  it('keeps Maestro flows aligned with the production native application id', () => {
    const app = readJson('app.json').expo;
    const maestroDir = path.join(root, '.maestro');
    const flows = fs.readdirSync(maestroDir).filter((file) => file.endsWith('.yaml') && file !== 'config.yaml');
    const mismatched = flows.filter((file) => {
      const source = read(path.join('.maestro', file));
      return !source.includes(`appId: ${app.android.package}`);
    });

    expect(app.android.package).toBe(app.ios.bundleIdentifier);
    expect(mismatched).toEqual([]);
  });

  it('documents the native local API port consistently', () => {
    const envExample = read('.env.example');

    expect(envExample).toContain('http://10.0.2.2:8090');
    expect(envExample).toContain('http://localhost:8090');
    expect(envExample).not.toContain(':8090');
  });

  it('allows Android release APKs to reach only approved cleartext development hosts', () => {
    const manifest = read('android/app/src/main/AndroidManifest.xml');
    const networkConfig = read('android/app/src/main/res/xml/network_security_config.xml');

    expect(manifest).toContain('android:networkSecurityConfig="@xml/network_security_config"');
    expect(networkConfig).toContain('<domain-config cleartextTrafficPermitted="true">');
    expect(networkConfig).toContain('<domain includeSubdomains="false">10.0.2.2</domain>');
    expect(networkConfig).toContain('<domain includeSubdomains="false">localhost</domain>');
    expect(networkConfig).toContain('<base-config cleartextTrafficPermitted="false">');
  });
});
