import UIKit
import Capacitor

@objc(CraftCrawlAppIconPlugin)
public class CraftCrawlAppIconPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "CraftCrawlAppIconPlugin"
    public let jsName = "CraftCrawlAppIcon"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "getCurrentIcon", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "setIcon", returnType: CAPPluginReturnPromise)
    ]

    private let iconNames: [String: String?] = [
        "trail": "AppIcon-Trail",
        "trail-dark": "AppIcon-TrailDark",
        "ember": "AppIcon-Ember",
        "ember-dark": "AppIcon-EmberDark"
    ]

    @objc public func getCurrentIcon(_ call: CAPPluginCall) {
        let currentIcon = UIApplication.shared.alternateIconName
        let name = iconNames.first { $0.value == currentIcon }?.key ?? "trail"
        call.resolve(["name": name])
    }

    @objc public func setIcon(_ call: CAPPluginCall) {
        let name = call.getString("name") ?? "trail"

        guard UIApplication.shared.supportsAlternateIcons else {
            call.reject("Alternate app icons are not supported on this device.")
            return
        }

        guard iconNames.keys.contains(name) else {
            call.reject("Unsupported app icon: \(name)")
            return
        }

        DispatchQueue.main.async {
            let nextIcon = self.iconNames[name] ?? nil
            if UIApplication.shared.alternateIconName == nextIcon {
                call.resolve(["name": name])
                return
            }

            UIApplication.shared.setAlternateIconName(nextIcon) { error in
                if let error = error {
                    call.reject(error.localizedDescription)
                    return
                }

                call.resolve(["name": name])
            }
        }
    }
}
