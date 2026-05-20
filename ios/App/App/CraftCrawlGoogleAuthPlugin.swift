import AuthenticationServices
import Capacitor
import Foundation
import Security
import UIKit

@objc(CraftCrawlGoogleAuthPlugin)
public class CraftCrawlGoogleAuthPlugin: CAPPlugin, CAPBridgedPlugin, ASWebAuthenticationPresentationContextProviding {
    public let identifier = "CraftCrawlGoogleAuthPlugin"
    public let jsName = "CraftCrawlGoogleAuth"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "signIn", returnType: CAPPluginReturnPromise)
    ]

    private var authSession: ASWebAuthenticationSession?
    private var activeState: String?

    @objc public func signIn(_ call: CAPPluginCall) {
        guard let clientId = call.getString("clientId"), !clientId.isEmpty else {
            call.reject("Missing Google iOS client ID.")
            return
        }

        let callbackScheme = call.getString("callbackScheme") ?? reversedClientIdScheme(clientId)

        guard !callbackScheme.isEmpty else {
            call.reject("Missing Google callback URL scheme.")
            return
        }

        let state = randomURLSafeString(byteCount: 18)
        let nonce = randomURLSafeString(byteCount: 18)
        activeState = state

        var components = URLComponents(string: "https://accounts.google.com/o/oauth2/v2/auth")
        components?.queryItems = [
            URLQueryItem(name: "client_id", value: clientId),
            URLQueryItem(name: "redirect_uri", value: "\(callbackScheme):/oauth2redirect"),
            URLQueryItem(name: "response_type", value: "id_token"),
            URLQueryItem(name: "scope", value: "openid email profile"),
            URLQueryItem(name: "state", value: state),
            URLQueryItem(name: "nonce", value: nonce),
            URLQueryItem(name: "prompt", value: "select_account")
        ]

        guard let authURL = components?.url else {
            call.reject("Could not create Google sign-in URL.")
            return
        }

        DispatchQueue.main.async {
            let session = ASWebAuthenticationSession(url: authURL, callbackURLScheme: callbackScheme) { [weak self] callbackURL, error in
                self?.authSession = nil

                if let error = error {
                    call.reject(error.localizedDescription)
                    return
                }

                guard let callbackURL = callbackURL else {
                    call.reject("Google sign-in did not return a callback URL.")
                    return
                }

                let values = self?.callbackValues(from: callbackURL) ?? [:]

                guard values["state"] == self?.activeState else {
                    call.reject("Invalid Google sign-in state.")
                    return
                }

                guard let idToken = values["id_token"], !idToken.isEmpty else {
                    let errorDescription = values["error_description"] ?? values["error"] ?? "Google sign-in did not return an ID token."
                    call.reject(errorDescription)
                    return
                }

                call.resolve(["idToken": idToken])
            }

            session.presentationContextProvider = self
            session.prefersEphemeralWebBrowserSession = false
            self.authSession = session

            if !session.start() {
                self.authSession = nil
                call.reject("Could not start Google sign-in.")
            }
        }
    }

    public func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
        return bridge?.viewController?.view.window ?? UIApplication.shared.windows.first { $0.isKeyWindow } ?? ASPresentationAnchor()
    }

    private func reversedClientIdScheme(_ clientId: String) -> String {
        let suffix = ".apps.googleusercontent.com"

        guard clientId.hasSuffix(suffix) else {
            return ""
        }

        let prefix = String(clientId.dropLast(suffix.count))
        return "com.googleusercontent.apps.\(prefix)"
    }

    private func randomURLSafeString(byteCount: Int) -> String {
        var bytes = [UInt8](repeating: 0, count: byteCount)
        let status = SecRandomCopyBytes(kSecRandomDefault, bytes.count, &bytes)

        if status != errSecSuccess {
            return UUID().uuidString.replacingOccurrences(of: "-", with: "")
        }

        return Data(bytes)
            .base64EncodedString()
            .replacingOccurrences(of: "+", with: "-")
            .replacingOccurrences(of: "/", with: "_")
            .replacingOccurrences(of: "=", with: "")
    }

    private func callbackValues(from url: URL) -> [String: String] {
        var values: [String: String] = [:]

        if let queryItems = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems {
            queryItems.forEach { item in
                values[item.name] = item.value ?? ""
            }
        }

        (url.fragment ?? "").split(separator: "&").forEach { pair in
            let parts = pair.split(separator: "=", maxSplits: 1).map(String.init)
            guard let name = parts.first else {
                return
            }

            let value = parts.count > 1 ? parts[1] : ""
            values[name.removingPercentEncoding ?? name] = value.removingPercentEncoding ?? value
        }

        return values
    }
}
