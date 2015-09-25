(function() {
Craft.CropElementAction = Garnish.Base.extend({
    
    init: function() {
        var cropTrigger = new Craft.ElementActionTrigger({
            handle: 'ImageResizer_CropImage',
            batch: false,
            validateSelection: function($selectedItems) {
                var documents = $selectedItems.find('.element.hasicon').length;
                return (documents > 0) ? false : true;
            },
            activate: function($selectedItems) {
                new Craft.CropImageModal($selectedItems.find('.element'), $selectedItems, {});
            }
        });
    },

});


Craft.Test = Garnish.Base.extend({
    
    init: function() {
        // Prevent firing multiple times
        if (typeof globalCropFunction == "undefined") {

            setTimeout(function() {

                
                //currentUploader.events.on('fileuploadadd', function() {
                //    console.log('elementindex')
                //});
/*
    $includeSubfoldersContainer: null,
    $includeSubfoldersCheckbox: null,
    showingIncludeSubfoldersCheckbox: false,

    $uploadButton: null,
    $uploadInput: null,
    $progressBar: null,
    $folders: null,

    uploader: null,
    promptHandler: null,
    progressBar: null,

    _uploadTotalFiles: 0,
    _uploadFileProgress: {},
    _uploadedFileIds: [],
    _currentUploaderSettings: {},

    _fileDrag: null,
    _folderDrag: null,
    _expandDropTargetFolderTimeout: null,
    _tempExpandedFolders: [],
*/

                

                Craft.elementIndex.$uploadButton.bind('fileuploaddone', function(e, data) {
                    console.log(e)
                    console.log(data)
                });



            }, 1);


/*

    $win: $(window),
    $doc: $(document),
    $bod: $(document.body)

*/



            
        }
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
        var $menu = $('<div class="menu">' +
            '<ul>' +
                '<li><a data-action="free">'+Craft.t('Free')+'</a></li>' +
                '<li><a data-action="square">'+Craft.t('Square')+'</a></li>' +
                '<li><a data-action="constrain">'+Craft.t('Constrain')+'</a></li>' +
                '<li><a data-action="4_3">'+Craft.t('4:3')+'</a></li>' +
            '</ul>' +
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

        var $imgAreaSelect = $img.imgAreaSelect({ instance: true });
        var x = 0;
        var y = 0;
        var width = $img.width();
        var height = $img.height();

        if ($option.data('action') == 'free') {
            $imgAreaSelect.setOptions({ aspectRatio: 'auto' });
        } else if ($option.data('action') == 'square') {

            $imgAreaSelect.setOptions({ aspectRatio: '1:1' });

            if (width > height) {
                width = height;
            } else {
                height = width;
            }

        } else if ($option.data('action') == 'constrain') {
            $imgAreaSelect.setOptions({ aspectRatio: width + ':' + height });
        } else if ($option.data('action') == '4_3') {

            $imgAreaSelect.setOptions({ aspectRatio: '4:3' });

            if (width > height) {
                width = Math.round((height / 3) * 4);
            } else {
                height = Math.round((width / 4) * 3);
            }
        }

        // Nice animation!
        $imgAreaSelect.animateSelection(x, y, width, height, 'slow');
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
                    });

                    this.areaSelect.showArea(this);

                    this.resize();
                }, this));
            }
            
        }, this));
    },

    resize: function () {
        var $img = this.$container.find('img'),
            leftDistance = parseInt(this.$container.css('left'), 10),
            topDistance = parseInt(this.$container.css('top'), 10);

        var quotient = this.originalWidth / this.originalHeight,
            leftAvailable = leftDistance - 10,
            topAvailable = topDistance - 10;

        if (leftAvailable / quotient > topAvailable) {
            newWidth = this.$container.width() + (topAvailable * quotient);
        } else {
            newWidth = this.$container.width() + leftAvailable;
        }

        // Set the size so that the image always fits into a constraint x constraint box
        newWidth = Math.min(newWidth, this.constraint, this.constraint * quotient, this.originalWidth);
        this.$container.width(newWidth);

        var factor = newWidth / this.originalWidth,
            newHeight = this.originalHeight * factor;

        $img.height(newHeight).width(newWidth);
        this.factor = factor;

        if (typeof $img.imgAreaSelect({instance: true}) != "undefined") {
            $img.imgAreaSelect({instance: true}).update();
        }
    },

    saveImage: function() {
        var selection = this.areaSelect.getSelection();

        var params = {
            x1: Math.round(selection.x1 / this.factor),
            x2: Math.round(selection.x2 / this.factor),
            y1: Math.round(selection.y1 / this.factor),
            y2: Math.round(selection.y2 / this.factor),
            source: this.source,
        };

        if ($(this.$selectedItems).data('id')) {
            params['assetId'] = $(this.$selectedItems).data('id');
        }

        Craft.postActionRequest('imageResizer/cropSaveAction', params, $.proxy(function(response, textStatus) {
            if (textStatus == 'success') {
                if (response.error) {
                    Craft.cp.displayError(response.error);
                } else {
                    Craft.cp.displayNotice(Craft.t('Image cropped successfully.'));
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
    $container: null,

    init: function($container, settings) {
        this.$container = $container;
        this.setSettings(settings);
    },

    showArea: function(referenceObject) {
        var $target = this.$container.find('img');

        var areaOptions = {
            aspectRatio: this.settings.aspectRatio,
            maxWidth: $target.width(),
            maxHeight: $target.height(),
            instance: true,
            resizable: true,
            show: true,
            persistent: true,
            handles: true,
            parent: $target.parent(),
            classPrefix: 'imgareaselect'
        };

        var areaSelect = $target.imgAreaSelect(areaOptions);

        var x1 = this.settings.initialRectangle.x1;
        var x2 = this.settings.initialRectangle.x2;
        var y1 = this.settings.initialRectangle.y1;
        var y2 = this.settings.initialRectangle.y2;

        if (this.settings.initialRectangle.mode == "auto") {
            var proportions = this.settings.aspectRatio.split(":");
            var rectangleWidth = 0;
            var rectangleHeight = 0;

            // [0] - width proportion, [1] - height proportion
            if (proportions[0] > proportions[1]) {
                rectangleWidth = $target.width();
                rectangleHeight = rectangleWidth * proportions[1] / proportions[0];
            } else if (proportions[0] > proportions[1]) {
                rectangleHeight = $target.height();
                rectangleWidth = rectangleHeight * proportions[0] / proportions[1];
            } else {
                rectangleHeight = rectangleWidth = Math.min($target.width(), $target.height());
            }

            x1 = 0;
            y1 = 0;
            x2 = $target.width();
            y2 = $target.height();
        }

        areaSelect.setSelection(x1, y1, x2, y2);
        areaSelect.update();

        // Make sure we never go below 400px wide
        referenceObject.desiredWidth = ($target.attr('width') <= 400) ? 400 : false;
        referenceObject.desiredHeight = false;

        referenceObject.areaSelect = areaSelect;
        referenceObject.factor = $target.data('factor');
        referenceObject.originalHeight = $target.attr('height') / referenceObject.factor;
        referenceObject.originalWidth = $target.attr('width') / referenceObject.factor;
        referenceObject.constraint = $target.data('constraint');
        referenceObject.source = $target.attr('src').split('/').pop();
        referenceObject.updateSizeAndPosition();
    }
});

$.extend($.imgAreaSelect.prototype, {
    animateSelection: function (x1, y1, x2, y2, duration) {
        var fx = $.extend($('<div/>')[0], {
            ias: this,
            start: this.getSelection(),
            end: { x1: x1, y1: y1, x2: x2, y2: y2 }
        });

        $(fx).animate({
            cur: 1
        },
        {
            duration: duration,
            step: function (now, fx) {
                var start = fx.elem.start, end = fx.elem.end,
                    curX1 = Math.round(start.x1 + (end.x1 - start.x1) * now),
                    curY1 = Math.round(start.y1 + (end.y1 - start.y1) * now),
                    curX2 = Math.round(start.x2 + (end.x2 - start.x2) * now),
                    curY2 = Math.round(start.y2 + (end.y2 - start.y2) * now);
                fx.elem.ias.setSelection(curX1, curY1, curX2, curY2);
                fx.elem.ias.update();
            }
        });
    }
});

})();