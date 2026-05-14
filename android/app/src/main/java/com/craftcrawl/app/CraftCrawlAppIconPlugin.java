package com.craftcrawl.app;

import android.content.ComponentName;
import android.content.Context;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.util.LinkedHashMap;
import java.util.Map;

@CapacitorPlugin(name = "CraftCrawlAppIcon")
public class CraftCrawlAppIconPlugin extends Plugin {
    private static final String PREFS_NAME = "craftcrawl_app_icon";
    private static final String PREF_ICON = "icon";
    private final Map<String, String> aliases = new LinkedHashMap<>();

    @Override
    public void load() {
        aliases.put("trail", ".MainActivityTrail");
        aliases.put("trail-dark", ".MainActivityTrailDark");
        aliases.put("ember", ".MainActivityEmber");
        aliases.put("ember-dark", ".MainActivityEmberDark");
    }

    @PluginMethod
    public void getCurrentIcon(PluginCall call) {
        JSObject result = new JSObject();
        result.put("name", getStoredIconName());
        call.resolve(result);
    }

    @PluginMethod
    public void setIcon(PluginCall call) {
        String iconName = call.getString("name", "trail");
        if (!aliases.containsKey(iconName)) {
            call.reject("Unsupported app icon: " + iconName);
            return;
        }

        PackageManager packageManager = getContext().getPackageManager();
        String packageName = getContext().getPackageName();

        String selectedAlias = aliases.get(iconName);
        setAliasState(packageManager, packageName, selectedAlias, true);

        for (String alias : aliases.values()) {
            if (!alias.equals(selectedAlias)) {
                setAliasState(packageManager, packageName, alias, false);
            }
        }

        getPreferences().edit().putString(PREF_ICON, iconName).apply();

        JSObject result = new JSObject();
        result.put("name", iconName);
        call.resolve(result);
    }

    private void setAliasState(PackageManager packageManager, String packageName, String alias, boolean enabled) {
        ComponentName component = new ComponentName(packageName, packageName + alias);
        packageManager.setComponentEnabledSetting(
            component,
            enabled ? PackageManager.COMPONENT_ENABLED_STATE_ENABLED : PackageManager.COMPONENT_ENABLED_STATE_DISABLED,
            PackageManager.DONT_KILL_APP
        );
    }

    private SharedPreferences getPreferences() {
        return getContext().getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
    }

    private String getStoredIconName() {
        String storedIcon = getPreferences().getString(PREF_ICON, null);
        return aliases.containsKey(storedIcon) ? storedIcon : "trail";
    }
}
