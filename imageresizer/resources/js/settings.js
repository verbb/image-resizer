(function() {

    // ---------------------------------------------------------
    // Enable all asset source 'Resize on upload' light switches
    // ---------------------------------------------------------

    $(document).on('change', '#settings-allAssets', function() {
        var $lightSwitchFields = $('#settings-assetSources').find('.lightswitch');

        if ($(this).hasClass('on')) {
            $lightSwitchFields.addClass('on').attr('aria-checked', true);
            $lightSwitchFields.find('.lightswitch-container').animate({'margin-left': '0px'}, 100);
            $lightSwitchFields.find('input').val(1);
        }
    });


    // ---------------------------------------------------------------------
    // Disable 'Resize on upload' light switch for all asset source settings
    // ---------------------------------------------------------------------

    $(document).on('change', '#settings-assetSources .lightswitch', function() {
        var $allAssetsSourceLightSwitchField = $('#settings-allAssets.lightswitch');

        if (!$(this).hasClass('on')) {
            $allAssetsSourceLightSwitchField.removeClass('on').attr('aria-checked', false);
            $allAssetsSourceLightSwitchField.find('.lightswitch-container').animate({'margin-left': '-11px'}, 100);
            $allAssetsSourceLightSwitchField.find('input').val('');
        }
    });
})();