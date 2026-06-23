package com.craftcrawl.app;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        registerPlugin(CraftCrawlAppIconPlugin.class);
        registerPlugin(CraftCrawlGoogleAuthPlugin.class);
        super.onCreate(savedInstanceState);
        bridge.getWebView().setWebChromeClient(new CraftCrawlWebChromeClient(bridge));
    }
}
