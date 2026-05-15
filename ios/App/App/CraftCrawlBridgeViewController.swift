import UIKit
import WebKit
import Capacitor

@objc(CraftCrawlBridgeViewController)
class CraftCrawlBridgeViewController: CAPBridgeViewController {
    private let craftCrawlBackground = UIColor(red: 0.96, green: 0.95, blue: 0.91, alpha: 1.0)

    override func webView(with frame: CGRect, configuration: WKWebViewConfiguration) -> WKWebView {
        let webView = super.webView(with: frame, configuration: configuration)
        webView.isOpaque = false
        webView.backgroundColor = craftCrawlBackground
        if #available(iOS 15.0, *) {
            webView.underPageBackgroundColor = craftCrawlBackground
        }
        webView.scrollView.backgroundColor = craftCrawlBackground
        return webView
    }

    override func viewDidLoad() {
        view.backgroundColor = craftCrawlBackground
        super.viewDidLoad()
    }

    override func capacitorDidLoad() {
        super.capacitorDidLoad()
        bridge?.registerPluginInstance(CraftCrawlAppIconPlugin())
        bridge?.registerPluginInstance(CraftCrawlPageTransitionPlugin())
    }
}
