window.CraftCrawlResizePhoto = (function () {
    var MAX_DIMENSION = 1600;
    var TARGET_BYTES = 1800000;
    var MIN_QUALITY = 0.62;

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var image = new Image();

            image.onload = function () {
                URL.revokeObjectURL(url);
                resolve(image);
            };
            image.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('Photo could not be read.'));
            };
            image.src = url;
        });
    }

    function canvasToBlob(canvas, quality) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob) {
                    resolve(blob);
                    return;
                }

                reject(new Error('Photo could not be compressed.'));
            }, 'image/jpeg', quality);
        });
    }

    async function resizePhoto(file) {
        if (!file.type.startsWith('image/')) {
            return file;
        }

        var image = await loadImage(file);
        var ratio = Math.min(1, MAX_DIMENSION / Math.max(image.naturalWidth || image.width, image.naturalHeight || image.height));

        if (ratio >= 1 && file.size <= TARGET_BYTES) {
            return file;
        }

        var width = Math.max(1, Math.round((image.naturalWidth || image.width) * ratio));
        var height = Math.max(1, Math.round((image.naturalHeight || image.height) * ratio));
        var canvas = document.createElement('canvas');
        var context = canvas.getContext('2d', { alpha: false });

        canvas.width = width;
        canvas.height = height;
        context.fillStyle = '#fff';
        context.fillRect(0, 0, width, height);
        context.drawImage(image, 0, 0, width, height);

        var quality = 0.82;
        var blob = await canvasToBlob(canvas, quality);

        while (blob.size > TARGET_BYTES && quality > MIN_QUALITY) {
            quality = Math.max(MIN_QUALITY, quality - 0.08);
            blob = await canvasToBlob(canvas, quality);
        }

        if (blob.size >= file.size) {
            return file;
        }

        var baseName = (file.name || 'photo').replace(/\.[^.]+$/, '');
        return new File([blob], baseName + '.jpg', {
            type: 'image/jpeg',
            lastModified: Date.now()
        });
    }

    return resizePhoto;
})();
