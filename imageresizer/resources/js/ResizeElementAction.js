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

    $container: null,
    $footer: null,
    $body: null,
    $buttons: null,
    $closeBtn: null,
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
        this.$container = $('<div class="modal fitted image-resizer-modal"></div>').appendTo(Garnish.$bod);
        this.$footer = $('<div class="footer"/>').appendTo(this.$container);

        // Handle case for bulk-resize
        if (settings.bulkResize) {
            var actionDescription = '<strong>all images in ' + settings.assetFolderName + '</strong>';
        } else {
            var actionDescription = '<strong>' + $selectedItems.length + '</strong> image' + plural;
        }

        var bodyHtml = '<div class="body">' +
            '<div class="content">' +
                '<div class="main">' +
                    '<div class="elements">' +
                        '<h1>' + Craft.t('Resize Images') + '</h1>' +
                        '<p>' + Craft.t('You are about to resize {desc} to be a maximum of {width}px wide and {height}px high. Alternatively, set the width and height limits below for on-demand resizing.', { desc: actionDescription, width: settings.width, height: settings.height }) + '</p>' +

                        '<input class="text" type="text" id="settings-imageWidth" size="10" name="settings[imageWidth]" value="' + settings.width + '" autocomplete="off"> ' + Craft.t('width') + ' &nbsp;&nbsp;' +
                        '<input class="text" type="text" id="settings-imageHeight" size="10" name="settings[imageHeight]" value="' + settings.height + '" autocomplete="off"> ' + Craft.t('height') + 

                        '<p><strong>' + Craft.t('Caution') + ':</strong> ' + Craft.t('This operation permanently alters your images.') + '</p>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';

        $body = $(bodyHtml).appendTo(this.$container);

        this.base(this.$container, this.settings);

        this.$footerSpinner = $('<div class="spinner hidden"/>').appendTo(this.$footer);
        this.$buttons = $('<div class="buttons rightalign first"/>').appendTo(this.$footer);
        this.$closeBtn = $('<div class="btn close hidden">'+Craft.t('Close')+'</div>').appendTo(this.$buttons);
        this.$cancelBtn = $('<div class="btn cancel">'+Craft.t('Cancel')+'</div>').appendTo(this.$buttons);
        this.$saveBtn = $('<div class="btn submit">'+Craft.t('Resize')+'</div>').appendTo(this.$buttons);

        this.$body = $body;

        this.addListener(this.$closeBtn, 'activate', 'onFadeOut');
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

        // Generate a random ID to keep track of this task run
        var taskId = this.generateId();

        if (this.settings.bulkResize) {
            var data = {
                taskId: taskId,
                bulkResize: this.settings.bulkResize,
                assetFolderId: this.settings.assetFolderId,
                imageWidth: imageWidth,
                imageHeight: imageHeight,
            }
        } else {
            var data = {
                taskId: taskId,
                assetIds: dataIds,
                imageWidth: imageWidth,
                imageHeight: imageHeight,
            }
        }

        // Trigger the task creation
        Craft.postActionRequest('imageResizer/resizeElementAction', data, $.proxy(function(response, textStatus) {}, this));

        new Craft.ResizeTaskProgress(this, taskId, function() {
            modal.$footerSpinner.addClass('hidden');

            modal.$closeBtn.removeClass('hidden');
            modal.$cancelBtn.addClass('hidden');
            modal.$saveBtn.addClass('hidden');

            setTimeout($.proxy(function() {
                if (Craft.elementIndex) {
                    Craft.elementIndex.updateElements();
                }
            }), 1000);

        });
    },

    generateId: function() {
        var text = "";
        var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

        for (var i = 0; i < 5; i++) {
            text += possible.charAt(Math.floor(Math.random() * possible.length));
        }

        return text;
    },

});

Craft.ResizeTaskProgress = Garnish.Base.extend({
    modal: null,
    taskId: null,

    tasksById: null,
    completedTasks: null,
    updateTasksTimeout: null,

    completed: false,

    callback: null,

    init: function(modal, taskId, callback) {
        this.modal = modal;
        this.taskId = taskId;
        this.callback = callback;
        this.tasksById = {};
        this.completedTasks = [];

        // Force the tasks icon to run
        setTimeout($.proxy(function() {
            // Trigger running the task - from JS so as not to lock the browser session
            Craft.postActionRequest('tasks/runPendingTasks', $.proxy(function(taskInfo, textStatus) {}, this));    
        }, this), 500);

        // Force the tasks icon to run
        setTimeout($.proxy(function() {
            this.updateTasks();
        }, this), 1500);
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
        } else {
            // Likely too fast for Craft to register this was even a task!
            var progressTask = new Craft.ResizeTaskProgress.Task(this.modal, this.taskId, taskInfo);
            progressTask.complete();

            this.completed = true;
            this.callback();
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
                this.tasksById[taskInfo.id] = new Craft.ResizeTaskProgress.Task(this.modal, this.taskId, taskInfo);
            }

            if (anyTasksRunning) {
                this.updateTasksTimeout = setTimeout($.proxy(this, 'updateTasks'), 500);
            } else {
                this.completed = true;

                this.callback();
            }
        } else {
            this.completed = true;

            this.callback();
        }
    }
});

Craft.ResizeTaskProgress.Task = Garnish.Base.extend({
    taskId: null,
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

    init: function(modal, taskId, info) {
        this.taskId = taskId;
        this.modal = modal;

        if (info) {
            this.id = info.id;
            this.level = info.level;
            this.description = info.description;
        }

        this.$container = $('<div class="task"/>').appendTo(this.modal.$body.find('.main'));
        this.$statusContainer = $('<div class="task-status"/>').appendTo(this.$container);

        this.$container.data('task', this);

        if (info) {
            this.updateStatus(info);
        }
    },

    updateStatus: function(info) {
        if (this.status != info.status) {
            this.$statusContainer.empty();
            this.status = info.status;

            switch (this.status) {
                case 'running': {
                    this._progressBar = new Craft.ProgressBar(this.$statusContainer);
                    this._progressBar.showProgressBar();
                    break;
                }
                case 'error': {
                    $('<div class="error">' + Craft.t('Processing failed. <a class="go" href="' + Craft.getUrl('imageresizer/logs') + '">View logs</a>') + '</div>').appendTo(this.$statusContainer);
                    break;
                }
            }
        }

        if (this.status == 'running') {
            this._progressBar.setProgressPercentage(info.progress*100);
        }
    },

    complete: function()
    {
        // Get the summary of our processing
        Craft.postActionRequest('imageResizer/getTaskSummary', { taskId: this.taskId }, $.proxy(function(taskInfo, textStatus) {
            if (textStatus == 'success') {
                var html = '<span class="success">' + Craft.t('Success') + ': ' + taskInfo.summary.success + ', </span>' + 
                    '<span class="skipped">' + Craft.t('Skipped') + ': ' + taskInfo.summary.skipped + ', </span>' + 
                    '<span class="error">' + Craft.t('Error') + ': ' + taskInfo.summary.error + ' </span>' + 
                    '<a class="go" href="' + Craft.getUrl('imageresizer/logs') + '">' + Craft.t('View logs') + '</a>';

                this.$statusContainer.empty();
                $('<div>' + html + '</div>').appendTo(this.$statusContainer);
            }
        }, this));
    },

    destroy: function() {
        this.$container.remove();
        this.base();
    }
});

})();
