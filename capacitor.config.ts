import type { CapacitorConfig } from '@capacitor/cli';

const liveUrl = process.env.CRAFTCRAWL_MOBILE_URL || 'https://example.com';

const config: CapacitorConfig = {
  appId: 'com.craftcrawl.app',
  appName: 'CraftCrawl',
  webDir: 'mobile',
  server: {
    url: liveUrl,
    cleartext: false
  },
  plugins: {
    SplashScreen: {
      launchAutoHide: true,
      backgroundColor: '#f6f1e8',
      androidSplashResourceName: 'splash',
      showSpinner: false
    },
    StatusBar: {
      style: 'LIGHT',
      backgroundColor: '#f6f1e8'
    },
    Geolocation: {
      permissions: ['location']
    }
  }
};

export default config;
