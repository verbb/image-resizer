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

Craft.BulkResizeAssetFolder = Garnish.Base.extend({
    
    init: function(imageWidth, imageHeight) {
        var settings = {
            width: imageWidth,
            height: imageHeight,
        };

        $('.bulk-resize-btn').on('click', function() {
            settings.assetFolderId = $(this).data('id');
            settings.assetFolderName = $(this).data('name');
            settings.bulkResize = true;

            new Craft.ResizeModal('', '', settings);
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
        this.settings = settings;

        this.desiredWidth = '450';
        this.desiredHeight = '340';

        var plural = ($selectedItems.length == 1) ? '' : 's';

        // Build the modal
        var $container = $('<div class="modal fitted image-resizer-modal"></div>').appendTo(Garnish.$bod),
            $footer = $('<div class="footer"/>').appendTo($container);

        // Handle case for bulk-resize
        if (settings.bulkResize) {
            var actionDescription = '<strong>all images in ' + settings.assetFolderName + '</strong>';
        } else {
            var actionDescription = '<strong>' + $selectedItems.length + '</strong> image' + plural;
        }

        $body = $('<div class="body">' +
            '<div class="content">' +
                '<div class="main">' +
                    '<div class="elements">' +
                        '<h1>Resize Images</h1>' +
                        '<p>You are about to resize ' + actionDescription + ' to be a maximum of ' + settings.width + 'px wide and ' + settings.height + 'px high. Alternatively, set the width and height limits below for on-demand resizing.</p>' +

                        '<input class="text" type="text" id="settings-imageWidth" size="10" name="settings[imageWidth]" value="' + settings.width + '" autocomplete="off"> width &nbsp;&nbsp;' +
                        '<input class="text" type="text" id="settings-imageHeight" size="10" name="settings[imageHeight]" value="' + settings.height + '" autocomplete="off"> height' +

                        '<p><strong>Caution:</strong> This operation permanently alters your images.</p>' +
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
    },

    saveSettings: function() {
        var dataIds = [];
        var modal = this;

        if (this.$selectedItems.length) {
            this.$selectedItems.each(function(index, element) {
                dataIds[index] = $(element).data('id');
            });
        }

        this.$footerSpinner.removeClass('hidden');

        // Allow override
        var imageWidth = this.$body.find('#settings-imageWidth').val();
        var imageHeight = this.$body.find('#settings-imageHeight').val();

        if (this.settings.bulkResize) {
            var data = { 
                bulkResize: this.settings.bulkResize, 
                assetFolderId: this.settings.assetFolderId, 
                imageWidth: imageWidth, 
                imageHeight: imageHeight, 
            }
        } else {
            var data = { 
                assetIds: dataIds, 
                imageWidth: imageWidth, 
                imageHeight: imageHeight, 
            }
        }

        Craft.postActionRequest('imageResizer/resizeElementAction', data, $.proxy(function(response, textStatus) {}, this));
            
        new Craft.ResizeTaskProgress(this, function() {
            modal.$footerSpinner.addClass('hidden');

            // In this case, no images were resized at all!
            if (modal.$body.find('.task').length == 0) {
                var $container = $('<div class="task"/>').appendTo(modal.$body.find('.main'));
                var $statusContainer = $('<div class="task-status"/>').appendTo($container);
                $('<div><div data-icon="check"> ' + Craft.t('No images to resize!') + '</div>').appendTo($statusContainer);
            }

            setTimeout($.proxy(function() {
                modal.onFadeOut();

                if (Craft.elementIndex) {
                    Craft.elementIndex.updateElements();
                }
            }), 1000);
               
        });
    }
});

Craft.ResizeTaskProgress = Garnish.Base.extend({
    modal: null,

    tasksById: null,
    completedTasks: null,
    updateTasksTimeout: null,

    completed: false,

    callback: null,

    init: function(modal, callback) {
        this.modal = modal;
        this.callback = callback;
        this.tasksById = {};
        this.completedTasks = [];

        // Force the tasks icon to run
        setTimeout($.proxy(function() {
            this.updateTasks();
        }, this), 1000);

        Craft.cp.stopTrackingTaskProgress();
    },

    updateTasks: function() {
        this.completed = false;

        Craft.postActionRequest('tasks/getTaskInfo', $.proxy(function(taskInfo, textStatus) {
            if (textStatus == 'success') {
                this.showTaskInfo(taskInfo[0]);
            }
        }, this))
    },

    showTaskInfo: function(taskInfo) {
        // First remove any tasks that have completed
        var newTaskIds = [];

        if (taskInfo) {
            newTaskIds.push(taskInfo.id);
        }

        for (var id in this.tasksById) {
            if (!Craft.inArray(id, newTaskIds)) {
                this.tasksById[id].complete();
                this.completedTasks.push(this.tasksById[id]);
                delete this.tasksById[id];
            }
        }

        // Now display the tasks that are still around
        if (taskInfo) {
            var anyTasksRunning = false,
                anyTasksFailed = false;

            if (!anyTasksRunning && taskInfo.status == 'running') {
                anyTasksRunning = true;
            } else if (!anyTasksFailed && taskInfo.status == 'error') {
                anyTasksFailed = true;
            }

            if (this.tasksById[taskInfo.id]) {
                this.tasksById[taskInfo.id].updateStatus(taskInfo);
            } else {
                this.tasksById[taskInfo.id] = new Craft.ResizeTaskProgress.Task(this.modal, taskInfo);
            }

            if (anyTasksRunning) {
                this.updateTasksTimeout = setTimeout($.proxy(this, 'updateTasks'), 500);
            } else {
                this.completed = true;

                if (anyTasksFailed) {
                    Craft.cp.setRunningTaskInfo({ status: 'error' });
                }

                this.callback();
            }
        } else {
            this.completed = true;
            Craft.cp.setRunningTaskInfo(null);

            this.callback();
        }
    }
});

Craft.ResizeTaskProgress.Task = Garnish.Base.extend({
    modal: null,
    id: null,
    level: null,
    description: null,

    status: null,
    progress: null,

    $container: null,
    $statusContainer: null,
    $descriptionContainer: null,

    _progressBar: null,

    init: function(modal, info) {
        this.modal = modal;

        this.id = info.id;
        this.level = info.level;
        this.description = info.description;

        this.$container = $('<div class="task"/>').appendTo(this.modal.$body.find('.main'));
        this.$statusContainer = $('<div class="task-status"/>').appendTo(this.$container);

        this.$container.data('task', this);

        this.updateStatus(info);
    },

    updateStatus: function(info) {
        if (this.status != info.status) {
            this.$statusContainer.empty();
            this.status = info.status;

            this._progressBar = new Craft.ProgressBar(this.$statusContainer);
            this._progressBar.showProgressBar();
        }

        if (this.status == 'running') {
            this._progressBar.setProgressPercentage(info.progress*100);

            if (this.level == 0) {
                // Update the task icon
                Craft.cp.setRunningTaskInfo(info, true);
            }
        }
    },

    complete: function()
    {
        this.$statusContainer.empty();
        $('<div><div data-icon="check"> ' + Craft.t('Resizing complete!') + '</div>').appendTo(this.$statusContainer);
    },

    destroy: function() {
        if (this.modal.tasksById[this.id]) {
            delete this.modal.tasksById[this.id];
        }

        this.$container.remove();
        this.base();
    }
});

})();
