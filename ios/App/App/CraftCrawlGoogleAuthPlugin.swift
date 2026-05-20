import Capacitor
import Foundation
import GoogleSignIn
import UIKit

@objc(CraftCrawlGoogleAuthPlugin)
public class CraftCrawlGoogleAuthPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "CraftCrawlGoogleAuthPlugin"
    public let jsName = "CraftCrawlGoogleAuth"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "signIn", returnType: CAPPluginReturnPromise)
    ]

    @objc public func signIn(_ call: CAPPluginCall) {
        guard let clientId = call.getString("clientId"), !clientId.isEmpty else {
            call.reject("Missing Google iOS client ID.")
            return
        }

        let serverClientId = call.getString("serverClientId")
        GIDSignIn.sharedInstance.configuration = GIDConfiguration(
            clientID: clientId,
            serverClientID: serverClientId?.isEmpty == false ? serverClientId : nil
        )

        DispatchQueue.main.async {
            guard let presentingViewController = self.bridge?.viewController else {
                call.reject("Could not find a view controller for Google sign-in.")
                return
            }

            GIDSignIn.sharedInstance.signIn(withPresenting: presentingViewController) { result, error in
                if let error = error {
                    call.reject(error.localizedDescription)
                    return
                }

                guard let user = result?.user else {
                    call.reject("Google sign-in did not return a user.")
                    return
                }

                guard let idToken = user.idToken?.tokenString else {
                    call.reject("Google sign-in did not return an ID token.")
                    return
                }

                call.resolve([
                    "idToken": idToken,
                    "email": user.profile?.email ?? "",
                    "firstName": user.profile?.givenName ?? "",
                    "lastName": user.profile?.familyName ?? ""
                ])
            }
        }
    }
}
