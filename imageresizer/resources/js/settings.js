(function() {

    // When toggling an asset source's enabled, we want to turn off the 'all asset source' option
    $('#settings-resize .lightswitch[id^="settings-assetSourceSettings"]').on('change', function(e) {
        e.stopPropagation();

        var $allSwitch = $('#settings-allAssets');
        var $assetSwitches = $('.lightswitch[id^="settings-assetSourceSettings"]')
        var $assetSwitchesOn = $('.lightswitch.on[id^="settings-assetSourceSettings"]')

        if ($assetSwitches.length == $assetSwitchesOn.length) {
            $allSwitch.data('lightswitch').turnOn();
        } else {
            $allSwitch.data('lightswitch').turnOff();
        }
    });

})();