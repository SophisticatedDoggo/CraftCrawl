package com.craftcrawl.app;

import android.Manifest;
import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.provider.MediaStore;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient.FileChooserParams;
import android.webkit.WebView;
import android.widget.Toast;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.core.content.ContextCompat;
import androidx.core.content.FileProvider;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeWebChromeClient;
import java.io.File;
import java.io.IOException;
import java.util.Map;

/**
 * Keeps HTML capture inputs camera-only on Android. Capacitor's default client
 * falls back to a file picker when camera capture cannot be launched, which can
 * let check-in photos come from the gallery.
 */
public class CraftCrawlWebChromeClient extends BridgeWebChromeClient {
    private final Bridge bridge;
    private final ActivityResultLauncher<String[]> permissionLauncher;
    private final ActivityResultLauncher<Intent> cameraLauncher;
    private ValueCallback<Uri[]> pendingCallback;
    private Uri pendingPhotoUri;
    private File pendingPhotoFile;

    public CraftCrawlWebChromeClient(Bridge bridge) {
        super(bridge);
        this.bridge = bridge;

        permissionLauncher = bridge.registerForActivityResult(
            new ActivityResultContracts.RequestMultiplePermissions(),
            this::handlePermissionResult
        );
        cameraLauncher = bridge.registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(),
            result -> finishCapture(result.getResultCode() == Activity.RESULT_OK)
        );
    }

    @Override
    public boolean onShowFileChooser(
        WebView webView,
        ValueCallback<Uri[]> filePathCallback,
        FileChooserParams fileChooserParams
    ) {
        if (!isImageCaptureRequest(fileChooserParams)) {
            return super.onShowFileChooser(webView, filePathCallback, fileChooserParams);
        }

        cancelPendingCapture();
        pendingCallback = filePathCallback;

        if (
            ContextCompat.checkSelfPermission(bridge.getContext(), Manifest.permission.CAMERA) ==
            PackageManager.PERMISSION_GRANTED
        ) {
            launchCamera();
        } else {
            permissionLauncher.launch(new String[] { Manifest.permission.CAMERA });
        }

        return true;
    }

    private boolean isImageCaptureRequest(FileChooserParams params) {
        if (!params.isCaptureEnabled()) {
            return false;
        }

        for (String acceptType : params.getAcceptTypes()) {
            if (acceptType != null && acceptType.trim().toLowerCase().startsWith("image/")) {
                return true;
            }
        }

        return false;
    }

    private void handlePermissionResult(Map<String, Boolean> result) {
        if (Boolean.TRUE.equals(result.get(Manifest.permission.CAMERA))) {
            launchCamera();
            return;
        }

        showCameraUnavailable("Camera permission is required to take a check-in photo.");
        finishCapture(false);
    }

    private void launchCamera() {
        if (pendingCallback == null) {
            return;
        }

        try {
            pendingPhotoFile = File.createTempFile("checkin-photo-", ".jpg", bridge.getContext().getCacheDir());
            pendingPhotoUri = FileProvider.getUriForFile(
                bridge.getContext(),
                bridge.getContext().getPackageName() + ".fileprovider",
                pendingPhotoFile
            );

            Intent intent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
            intent.putExtra(MediaStore.EXTRA_OUTPUT, pendingPhotoUri);
            intent.setClipData(ClipData.newRawUri("Check-in photo", pendingPhotoUri));
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_GRANT_WRITE_URI_PERMISSION);
            cameraLauncher.launch(intent);
        } catch (ActivityNotFoundException ex) {
            showCameraUnavailable("No camera app is available on this device.");
            finishCapture(false);
        } catch (IOException | IllegalArgumentException ex) {
            showCameraUnavailable("The camera could not be opened.");
            finishCapture(false);
        }
    }

    private void finishCapture(boolean succeeded) {
        ValueCallback<Uri[]> callback = pendingCallback;
        Uri photoUri = pendingPhotoUri;
        File photoFile = pendingPhotoFile;

        pendingCallback = null;
        pendingPhotoUri = null;
        pendingPhotoFile = null;

        if (!succeeded && photoFile != null) {
            //noinspection ResultOfMethodCallIgnored
            photoFile.delete();
        }

        if (callback != null) {
            callback.onReceiveValue(succeeded && photoUri != null ? new Uri[] { photoUri } : null);
        }
    }

    private void cancelPendingCapture() {
        if (pendingCallback != null) {
            finishCapture(false);
        }
    }

    private void showCameraUnavailable(String message) {
        Toast.makeText(bridge.getContext(), message, Toast.LENGTH_LONG).show();
    }
}
