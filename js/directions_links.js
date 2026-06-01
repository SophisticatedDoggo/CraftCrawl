(function () {
    function isMobileDevice() {
        const userAgent = navigator.userAgent || '';
        const touchMac = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;

        return /Android|iPhone|iPad|iPod/i.test(userAgent) || touchMac;
    }

    function isAppleMobileDevice() {
        const userAgent = navigator.userAgent || '';
        const touchMac = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;

        return /iPhone|iPad|iPod/i.test(userAgent) || touchMac;
    }

    function mobileDirectionsUrl(link) {
        const latitude = link.getAttribute('data-directions-latitude');
        const longitude = link.getAttribute('data-directions-longitude');
        const address = link.getAttribute('data-directions-address') || '';
        const hasCoordinates = latitude && longitude;
        const encodedAddress = encodeURIComponent(address);

        if (isAppleMobileDevice()) {
            if (hasCoordinates) {
                return `maps://?daddr=${encodeURIComponent(`${latitude},${longitude}`)}&dirflg=d`;
            }

            return `maps://?daddr=${encodedAddress}&dirflg=d`;
        }

        if (hasCoordinates) {
            return `geo:${encodeURIComponent(latitude)},${encodeURIComponent(longitude)}?q=${encodeURIComponent(`${latitude},${longitude}`)}`;
        }

        return `geo:0,0?q=${encodedAddress}`;
    }

    function navigateToDirections(link, url) {
        window.location.href = url;
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-directions-address]');

        if (!link) {
            return;
        }

        if (!link.getAttribute('data-directions-address') && (!link.getAttribute('data-directions-latitude') || !link.getAttribute('data-directions-longitude'))) {
            return;
        }

        const shouldShowSocialClubDisclaimer = link.getAttribute('data-social-club-directions') === 'true'
            && window.CRAFTCRAWL_SHOW_SOCIAL_CLUB_DISCLAIMER !== false
            && typeof window.CraftCrawlShowSocialClubDisclaimerIfNeeded === 'function';
        const directionsUrl = isMobileDevice() ? mobileDirectionsUrl(link) : link.href;

        if (shouldShowSocialClubDisclaimer) {
            event.preventDefault();
            window.CraftCrawlShowSocialClubDisclaimerIfNeeded(() => {
                navigateToDirections(link, directionsUrl);
            });
            return;
        }

        if (isMobileDevice()) {
            event.preventDefault();
            window.location.href = directionsUrl;
        }
    });
}());
