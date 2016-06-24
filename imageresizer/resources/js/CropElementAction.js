(function() {
Craft.CropElementAction = Garnish.Base.extend({
    
    init: function(croppingRatios) {
        var cropTrigger = new Craft.ElementActionTrigger({
            handle: 'ImageResizer_CropImage',
            batch: false,
            validateSelection: function($selectedItems) {
                var documents = $selectedItems.find('.element.hasicon').length;
                return (documents > 0) ? false : true;
            },
            activate: function($selectedItems) {
                new Craft.CropImageModal($selectedItems.find('.element'), $selectedItems, { croppingRatios: croppingRatios });
            }
        });
    },

});

Craft.CropImageModal = Garnish.Modal.extend(
{
    $element: null,
    $selectedItems: null,
    settings: null,

    $container: null,
    $body: null,
    $footerSpinner: null,
    $buttonsLeft: null,
    $buttonsRight: null,
    $cancelBtn: null,
    $saveBtn: null,
    $aspectRatioSelect: null,

    areaSelect: null,

    init: function($element, $selectedItems, settings) {
        this.$element = $element;
        this.$selectedItems = $selectedItems;
        this.settings = settings;

        this.desiredWidth = 400;
        this.desiredHeight = 280;

        // Build the modal
        var $container = $('<div class="modal fitted logo-modal last image-resizer-crop-modal"></div>').appendTo(Garnish.$bod),
            $footer = $('<div class="footer"/>').appendTo($container);

        $body = $('<div class="crop-image">' +
            '<div class="image-chooser">' +
                '<div class="centeralign">' +
                    '<div class="spinner loadingmore big"></div>' +
                '</div>' +
            '</div>' +
        '</div>').appendTo($container);

        this.base($container, this.settings);

        this.$footerSpinner = $('<div class="spinner hidden"/>').appendTo($footer);
        this.$buttonsLeft = $('<div class="buttons leftalign first"/>').appendTo($footer);
        this.$aspectRatioSelect = $('<div class="btn menubtn">'+Craft.t('Aspect Ratio')+': <span class="select-option">'+Craft.t('Free')+'</span></div>').appendTo(this.$buttonsLeft);

        this.$buttonsRight = $('<div class="buttons rightalign first"/>').appendTo($footer);
        this.$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo(this.$buttonsRight);
        this.$saveBtn = $('<div class="btn submit">'+Craft.t('Save')+'</div>').appendTo(this.$buttonsRight);

        this.setupAspectRatio();

        this.$body = $body;

        this.fetchImage();

        this.addListener(this.$cancelBtn, 'activate', 'onFadeOut');
        this.addListener(this.$saveBtn, 'activate', 'saveImage');
    },

    setupAspectRatio: function() {
        var menuOptions = '';
        $.each(this.settings.croppingRatios, function(index, item) {
            menuOptions += '<li><a data-action="' + index + '" data-width="' + item.width + '" data-height="' + item.height + '">' + Craft.t(item.name) + '</a></li>';
        });

        var $menu = $('<div class="menu">' +
            '<ul>' + menuOptions + '</ul>' +
        '</div>').insertAfter(this.$aspectRatioSelect);

        var MenuButton = new Garnish.MenuBtn(this.$aspectRatioSelect, {
            onOptionSelect: $.proxy(this, 'onSelectAspectRatio')
        });

        this.$aspectRatioSelect.data('menuButton', MenuButton);
    },

    onSelectAspectRatio: function(option) {
        var $option = $(option);
        var $img = this.$container.find('img');

        this.$aspectRatioSelect.find('.select-option').html($option.text());

        var x = 0;
        var y = 0;
        var width = $img.width();
        var height = $img.height();

        var action = $option.data('action');
        var widthConstraint = $option.data('width');
        var heightConstraint = $option.data('height');

        // Cater for special aspect ratio cases
        if (widthConstraint == 'none') { widthConstraint = null; }
        if (heightConstraint == 'none') { heightConstraint = null; }
        if (widthConstraint == 'relative') { widthConstraint = width; }
        if (heightConstraint == 'relative') { heightConstraint = height; }

        this.areaSelect.setOptions({ aspectRatio: widthConstraint / heightConstraint });
    },

    onFadeOut: function() {
        this.hide();
    },

    fetchImage: function() {
        var dataId = $(this.$selectedItems).data('id');
        
        Craft.postActionRequest('imageResizer/cropElementAction', { assetId: dataId }, $.proxy(function(response, textStatus) {
            this.$body.find('.spinner').addClass('hidden');

            if (textStatus == 'success') {
                var $imgContainer = $(response.html).appendTo(this.$container.find('.image-chooser'));

                // Setup cropping
                this.$container.find('img').load($.proxy(function() {
                    this.areaSelect = new Craft.CropImageAreaTool(this.$body, {
                        aspectRatio: "",
                        initialRectangle: {
                            mode: "auto"
                        }
                    }, this);

                    this.areaSelect.showArea();
                }, this));
            }
            
        }, this));
    },

    saveImage: function() {
        var selection = this.areaSelect.tellSelect();

        var params = {
            x1: Math.round(selection.x / this.factor),
            x2: Math.round(selection.x2 / this.factor),
            y1: Math.round(selection.y / this.factor),
            y2: Math.round(selection.y2 / this.factor),
            source: this.source,
        };

        if ($(this.$selectedItems).data('id')) {
            params['assetId'] = $(this.$selectedItems).data('id');
        }

        this.$body.find('.spinner').removeClass('hidden');

        Craft.postActionRequest('imageResizer/cropSaveAction', params, $.proxy(function(response, textStatus) {
            if (textStatus == 'success') {
                if (response.error) {
                    Craft.cp.displayError(response.error);
                } else {
                    Craft.cp.displayNotice(Craft.t('Image cropped successfully.'));

                    Craft.elementIndex.updateElements();
                }
            }

            this.onFadeOut();
            this.$container.empty();
        }, this));

        this.removeListener(this.$saveBtn, 'click');
        this.removeListener(this.$cancelBtn, 'click');

        this.$container.find('.crop-image').fadeTo(50, 0.5);
    }

});

Craft.CropImageAreaTool = Garnish.Base.extend({
    api: null,
    $container: null,
    containingModal: null,

    init: function($container, settings, containingModal) {
        this.$container = $container;
        this.setSettings(settings);
        this.containingModal = containingModal;
    },

    showArea: function(referenceObject) {
        var $target = this.$container.find('img');

        var cropperOptions = {
            aspectRatio: this.settings.aspectRatio,
            maxSize: [$target.width(), $target.height()],
            bgColor: 'black'
        };

        var initCropper = $.proxy(function(api) {
            this.api = api;

            var x1 = this.settings.initialRectangle.x1;
            var x2 = this.settings.initialRectangle.x2;
            var y1 = this.settings.initialRectangle.y1;
            var y2 = this.settings.initialRectangle.y2;

            if (this.settings.initialRectangle.mode == "auto") {
                var rectangleWidth = 0;
                var rectangleHeight = 0;

                if (this.settings.aspectRatio == "") {
                    rectangleWidth = $target.width();
                    rectangleHeight = $target.height();
                } else if (this.settings.aspectRatio > 1) {
                    rectangleWidth = $target.width();
                    rectangleHeight = rectangleWidth / this.settings.aspectRatio;
                } else if (this.settings.aspectRatio < 1) {
                    rectangleHeight = $target.height();
                    rectangleWidth = rectangleHeight * this.settings.aspectRatio;
                } else {
                    rectangleHeight = rectangleWidth = Math.min($target.width(), $target.height());
                }

                x1 = Math.round(($target.width() - rectangleWidth) / 2);
                y1 = Math.round(($target.height() - rectangleHeight) / 2);
                x2 = x1 + rectangleWidth;
                y2 = y1 + rectangleHeight;
            }

            this.api.setSelect([x1, y1, x2, y2]);

            // Make sure we never go below 400px wide
            this.containingModal.desiredWidth = ($target.attr('width') <= 400) ? 400 : false;
            this.containingModal.desiredHeight = false;

            this.containingModal.areaSelect = this.api;
            this.containingModal.factor = $target.data('factor');
            this.containingModal.originalHeight = $target.attr('height') / this.containingModal.factor;
            this.containingModal.originalWidth = $target.attr('width') / this.containingModal.factor;
            this.containingModal.constraint = $target.data('constraint');
            this.containingModal.source = $target.attr('src').split('/').pop();
            this.containingModal.updateSizeAndPosition();

        }, this);

        $target.Jcrop(cropperOptions, function() {
            initCropper(this);
        });
    }
});

})();