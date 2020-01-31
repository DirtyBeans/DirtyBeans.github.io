// Decimation's fork to remove emojis

const VERSION_CHECK_SUPPORTED = "Your iOS version is supported!";
const VERSION_CHECK_NEEDS_UPGRADE = "This package requires an upgrade to at least iOS %s.";
const VERSION_CHECK_UNCONFIRMED = "This package is not yet confirmed to work on iOS %s.";

(function (document) {
    "use strict";

    function iOSversion() {
        if (/iP(hone|od|ad)/.test(navigator.platform)) {
            var v = (navigator.appVersion).match(/OS (\d+)_(\d+)_?(\d+)?/);
            return [parseInt(v[1], 10), parseInt(v[2], 10), parseInt(v[3] || 0, 10)];
        } else {
            return 0;
        }
    }

    var prerequisite = document.querySelector(".prerequisite");
    var version = iOSversion();

    if (!prerequisite || !version) {
        prerequisite.querySelector("p").textContent = "Failed to check iOS version";
        prerequisite.querySelector("ul").style.backgroundColor = "#FF0000";
        return;
    }

    var versionNumber = parseFloat(version[0] + "." + version[1]);
    var minVersionNumber = parseFloat(prerequisite.dataset.minIos);
    var maxVersionNumber = parseFloat(prerequisite.dataset.maxIos);

    var message = VERSION_CHECK_SUPPORTED;
    var isBad = false;

    if (versionNumber < minVersionNumber) {
        message = VERSION_CHECK_NEEDS_UPGRADE.replace("%s", minVersionNumber);
        prerequisite.querySelector("ul").style.backgroundColor = "#FFA500";
        isBad = true;
    } else if (maxVersionNumber < versionNumber) {
        message = VERSION_CHECK_UNCONFIRMED.replace("%s", versionNumber);
        prerequisite.querySelector("ul").style.backgroundColor = "#FFA500";
        isBad = true;
    }

    prerequisite.querySelector("p").textContent = message;

    if (isBad) {
        prerequisite.classList.add("info");
    } else {
        prerequisite.querySelector("ul").style.backgroundColor = "#E8FFEE";
    }

})(document);