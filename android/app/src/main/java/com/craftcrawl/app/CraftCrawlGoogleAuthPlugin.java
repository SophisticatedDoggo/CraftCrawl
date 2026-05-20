package com.craftcrawl.app;

import android.content.Intent;

import com.getcapacitor.ActivityCallback;
import com.getcapacitor.ActivityResult;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.google.android.gms.auth.api.signin.GoogleSignIn;
import com.google.android.gms.auth.api.signin.GoogleSignInAccount;
import com.google.android.gms.auth.api.signin.GoogleSignInClient;
import com.google.android.gms.auth.api.signin.GoogleSignInOptions;
import com.google.android.gms.common.api.ApiException;
import com.google.android.gms.tasks.Task;

@CapacitorPlugin(name = "CraftCrawlGoogleAuth")
public class CraftCrawlGoogleAuthPlugin extends Plugin {
    @PluginMethod
    public void signIn(PluginCall call) {
        String serverClientId = call.getString("serverClientId", "");

        if (serverClientId == null || serverClientId.trim().isEmpty()) {
            call.reject("Missing Google web client ID.");
            return;
        }

        GoogleSignInOptions options = new GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
            .requestIdToken(serverClientId)
            .requestEmail()
            .requestProfile()
            .build();

        GoogleSignInClient client = GoogleSignIn.getClient(getActivity(), options);
        client.signOut().addOnCompleteListener(task -> startActivityForResult(call, client.getSignInIntent(), "handleGoogleSignInResult"));
    }

    @ActivityCallback
    private void handleGoogleSignInResult(PluginCall call, ActivityResult result) {
        if (call == null) {
            return;
        }

        Intent data = result.getData();
        Task<GoogleSignInAccount> task = GoogleSignIn.getSignedInAccountFromIntent(data);

        try {
            GoogleSignInAccount account = task.getResult(ApiException.class);
            String idToken = account.getIdToken();

            if (idToken == null || idToken.isEmpty()) {
                call.reject("Google sign-in did not return an ID token.");
                return;
            }

            JSObject response = new JSObject();
            response.put("idToken", idToken);
            response.put("email", account.getEmail() != null ? account.getEmail() : "");
            response.put("firstName", account.getGivenName() != null ? account.getGivenName() : "");
            response.put("lastName", account.getFamilyName() != null ? account.getFamilyName() : "");
            call.resolve(response);
        } catch (ApiException error) {
            call.reject("Google sign-in failed: " + error.getStatusCode());
        }
    }
}
