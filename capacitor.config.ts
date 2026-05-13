import type { CapacitorConfig } from '@capacitor/cli';

const appEnv = process.env.CRAFTCRAWL_APP_ENV || 'prod';
const isStaging = appEnv === 'staging';
const liveUrl = process.env.CRAFTCRAWL_MOBILE_URL || 'https://example.com';

const config: CapacitorConfig = {
  appId: isStaging ? 'com.craftcrawl.app.staging' : 'com.craftcrawl.app',
  appName: isStaging ? 'CraftCrawl Staging' : 'CraftCrawl',
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
      overlaysWebView: true,
      style: 'DEFAULT',
      backgroundColor: '#f6f1e8'
    },
    Geolocation: {
      permissions: ['location']
    }
  }
};

export default config;
