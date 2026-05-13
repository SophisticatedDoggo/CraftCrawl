(function () {
    function getNativeGeolocation() {
        const capacitor = window.Capacitor;
        const plugins = capacitor && capacitor.Plugins;
        const isNative = capacitor
            && typeof capacitor.isNativePlatform === 'function'
            && capacitor.isNativePlatform();
        let geolocation = plugins && plugins.Geolocation;

        if (!geolocation
            && isNative
            && typeof capacitor.registerPlugin === 'function'
            && (!capacitor.isPluginAvailable || capacitor.isPluginAvailable('Geolocation'))) {
            geolocation = capacitor.registerPlugin('Geolocation');
        }

        return isNative && geolocation && typeof geolocation.getCurrentPosition === 'function'
            ? geolocation
            : null;
    }

    function normalizeError(error) {
        if (!error || typeof error !== 'object') {
            return error;
        }

        const codeMap = {
            'OS-PLUG-GLOC-0002': 2,
            'OS-PLUG-GLOC-0003': 1,
            'OS-PLUG-GLOC-0007': 2,
            'OS-PLUG-GLOC-0008': 1,
            'OS-PLUG-GLOC-0009': 1,
            'OS-PLUG-GLOC-0010': 3,
            'OS-PLUG-GLOC-0017': 2
        };

        if (typeof error.code === 'string' && Object.prototype.hasOwnProperty.call(codeMap, error.code)) {
            return {
                code: codeMap[error.code],
                message: error.message || ''
            };
        }

        return error;
    }

    function getCurrentPosition(success, failure, options) {
        const nativeGeolocation = getNativeGeolocation();

        if (nativeGeolocation) {
            nativeGeolocation.getCurrentPosition(options || {})
                .then(success)
                .catch((error) => {
                    if (failure) {
                        failure(normalizeError(error));
                    }
                });
            return true;
        }

        if (!navigator.geolocation) {
            return false;
        }

        navigator.geolocation.getCurrentPosition(success, failure, options);
        return true;
    }

    window.CraftCrawlLocation = {
        getCurrentPosition
    };
}());
