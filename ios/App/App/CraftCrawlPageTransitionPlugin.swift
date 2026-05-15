import UIKit
import Capacitor

@objc(CraftCrawlPageTransitionPlugin)
public class CraftCrawlPageTransitionPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "CraftCrawlPageTransitionPlugin"
    public let jsName = "CraftCrawlPageTransition"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "show", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "hide", returnType: CAPPluginReturnPromise)
    ]

    private var overlayView: UIView?

    @objc public func show(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            guard let hostView = self.bridge?.viewController?.view else {
                call.reject("Unable to access the host view.")
                return
            }

            let backgroundColor = UIColor(hex: call.getString("color")) ?? UIColor(red: 0.96, green: 0.95, blue: 0.91, alpha: 1.0)

            if let overlay = self.overlayView, overlay.superview != nil {
                overlay.backgroundColor = backgroundColor
                hostView.bringSubviewToFront(overlay)
                call.resolve()
                return
            }

            let overlay = self.makeColorOverlay(in: hostView, backgroundColor: backgroundColor)
            if overlay.superview == nil {
                hostView.addSubview(overlay)
            }

            hostView.bringSubviewToFront(overlay)
            self.overlayView = overlay
            call.resolve()
        }
    }

    @objc public func hide(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            guard let overlay = self.overlayView else {
                call.resolve()
                return
            }

            UIView.animate(withDuration: 0.12, animations: {
                overlay.alpha = 0
            }, completion: { _ in
                overlay.removeFromSuperview()
                overlay.alpha = 1
                call.resolve()
            })
        }
    }

    private func makeColorOverlay(in hostView: UIView, backgroundColor: UIColor) -> UIView {
        let overlay = UIView(frame: hostView.bounds)
        overlay.autoresizingMask = [.flexibleWidth, .flexibleHeight]
        overlay.backgroundColor = backgroundColor
        overlay.isUserInteractionEnabled = true
        overlay.alpha = 1
        return overlay
    }
}

private extension UIColor {
    convenience init?(hex: String?) {
        guard var value = hex?.trimmingCharacters(in: .whitespacesAndNewlines), !value.isEmpty else {
            return nil
        }

        if value.hasPrefix("#") {
            value.removeFirst()
        }

        guard value.count == 6, let rgb = Int(value, radix: 16) else {
            return nil
        }

        self.init(
            red: CGFloat((rgb >> 16) & 0xFF) / 255,
            green: CGFloat((rgb >> 8) & 0xFF) / 255,
            blue: CGFloat(rgb & 0xFF) / 255,
            alpha: 1
        )
    }
}
