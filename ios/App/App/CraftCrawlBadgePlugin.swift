import UIKit
import Capacitor

@objc(CraftCrawlBadgePlugin)
public class CraftCrawlBadgePlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "CraftCrawlBadgePlugin"
    public let jsName = "CraftCrawlBadge"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "setBadgeCount", returnType: CAPPluginReturnPromise)
    ]

    @objc public func setBadgeCount(_ call: CAPPluginCall) {
        let count = max(0, call.getInt("count") ?? 0)

        DispatchQueue.main.async {
            UIApplication.shared.applicationIconBadgeNumber = count
            call.resolve(["count": count])
        }
    }
}
