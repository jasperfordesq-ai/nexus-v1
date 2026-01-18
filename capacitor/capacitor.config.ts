import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.nexus.timebank',
  appName: 'Project NEXUS',

  // Point to your live website URL
  // Change this to your actual production URL when deploying
  server: {
    // For development/testing, use your local server:
    // url: 'http://192.168.1.100',  // Your local network IP

    // For production, use your live site:
    url: 'https://hour-timebank.ie',

    // Allow cleartext (HTTP) for local development only
    // Set to false for production
    cleartext: true,

    // Handle navigation within the app
    handleApplicationNotifications: true
  },

  // Android-specific configuration
  android: {
    // Allow mixed content for development
    allowMixedContent: true,

    // Splash screen background
    backgroundColor: '#0f172a',

    // Build options
    buildOptions: {
      keystorePath: undefined,
      keystorePassword: undefined,
      keystoreAlias: undefined,
      keystoreAliasPassword: undefined,
      releaseType: 'APK'
    },

    // Deep linking - handle URLs that point to your domain
    // This allows links like https://hour-timebank.ie/profile/123 to open in the app
    webContentsDebuggingEnabled: false
  },

  // iOS-specific configuration
  ios: {
    backgroundColor: '#0f172a',
    contentInset: 'automatic'
  },

  // Plugins configuration
  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      launchAutoHide: true,
      backgroundColor: '#0f172a',
      androidSplashResourceName: 'splash',
      androidScaleType: 'CENTER_CROP',
      showSpinner: false,
      splashFullScreen: true,
      splashImmersive: true
    },
    StatusBar: {
      style: 'DARK',
      backgroundColor: '#0f172a'
    },
    Keyboard: {
      resize: 'body',
      resizeOnFullScreen: true
    }
  }
};

export default config;
