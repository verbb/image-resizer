(function() {
    Craft.ResizeElementAction = Garnish.Base.extend({
        
        init: function(imageWidth, imageHeight) {
            var settings = {
                width: imageWidth,
                height: imageHeight,
            };

            var resizeTrigger = new Craft.ElementActionTrigger({
                handle: 'ImageResizer_ResizeImage',
                batch: true,
                validateSelection: function($selectedItems) {
                    var documents = $selectedItems.find('.element.hasicon').length;
                    return (documents > 0) ? false : true;
                },
                activate: function($selectedItems) {
                    new Craft.ResizeModal($selectedItems.find('.element'), $selectedItems, settings);
                }
            });
        },

    });

    Craft.ResizeModal = Garnish.Modal.extend({
        $element: null,
        $selectedItems: null,
        settings: null,

        $body: null,
        $buttons: null,
        $cancelBtn: null,
        $saveBtn: null,
        $footerSpinner: null,

        init: function($element, $selectedItems, settings) {
            this.$element = $element;
            this.$selectedItems = $selectedItems;

            this.desiredWidth = '450';
            this.desiredHeight = '250';

            var plural = ($selectedItems.length == 1) ? '' : 's';

            // Build the modal
            var $container = $('<div class="modal fitted image-resizer-modal"></div>').appendTo(Garnish.$bod),
                $footer = $('<div class="footer"/>').appendTo($container);

            $body = $('<div class="body">' +
                '<div class="content">' +
                    '<div class="main">' +
                        '<div class="elements">' +
                            '<h1>Resize Images</h1>' +
                            '<p>You are about to resize <strong>' + $selectedItems.length + '</strong> image' + plural + ' to be a maximum of ' + settings.width + 'px wide and ' + settings.height + 'px high.</p>' +
                            '<p><strong>Caution:</strong> This operation permanently alters your images.</p>' +
                        '</div>' +
                        '<div class="centeralign">' +
                            '<div class="spinner loadingmore hidden"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>').appendTo($container);

            this.base($container, this.settings);

            this.$footerSpinner = $('<div class="spinner hidden"/>').appendTo($footer);
            this.$buttons = $('<div class="buttons rightalign first"/>').appendTo($footer);
            this.$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo(this.$buttons);
            this.$saveBtn = $('<div class="btn submit">'+Craft.t('Resize')+'</div>').appendTo(this.$buttons);

            this.$body = $body;

            this.addListener(this.$cancelBtn, 'activate', 'onFadeOut');
            this.addListener(this.$saveBtn, 'activate', 'saveSettings');
        },

        onFadeOut: function() {
            this.hide();
            this.destroy();
            this.$shade.remove();
            this.$container.remove();

            this.removeListener(this.$saveBtn, 'activate');
            this.removeListener(this.$cancelBtn, 'activate');
        },

        saveSettings: function() {
            var dataIds = [];

            this.$selectedItems.each(function(index, element) {
                dataIds.push($(element).data('id'));
            });

            this.$footerSpinner.removeClass('hidden');

            Craft.postActionRequest('imageResizer/resizeElementAction', { assetIds: dataIds }, $.proxy(function(response, textStatus) {
                this.$footerSpinner.addClass('hidden');

                if (response.error) {
                    $.each(response.error, function(index, value) {
                        Craft.cp.displayError(value);
                    });
                } else if (response.success) {

                    // Update the size column
                    this.$selectedItems.each(function(index, value) {
                        var size = response.success[$(value).data('id')];
                        console.log(size);

                        $(value).find('td[data-title="Size"]').html(size);
                    });

                    Craft.cp.displayNotice(Craft.t('Images resized successfully.'));
                    this.onFadeOut();
                } else {
                    Craft.cp.displayError(Craft.t('An error occured.'));
                }

            }, this));

            this.removeListener(this.$saveBtn, 'activate');
            this.removeListener(this.$cancelBtn, 'activate');
        }
    });
})();