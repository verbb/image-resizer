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
        this.desiredHeight = '280';

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

        this.$selectedItems.each(function(index, element) {
            dataIds[index] = $(element).data('id');
        });

        this.$footerSpinner.removeClass('hidden');

        Craft.postActionRequest('imageResizer/resizeElementAction', { assetIds: dataIds }, $.proxy(function(response, textStatus) {
            
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
                }), 1000);
                   
            });

        }, this));
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

        this.updateTasks();
    },

    updateTasks: function() {
        this.completed = false;

        Craft.postActionRequest('tasks/getTaskInfo', $.proxy(function(taskInfo, textStatus) {
            if (textStatus == 'success') {
                this.showTaskInfo(taskInfo);
            }
        }, this))
    },

    showTaskInfo: function(taskInfo) {
        // First remove any tasks that have completed
        var newTaskIds = [];

        if (taskInfo) {
            for (var i = 0; i < taskInfo.length; i++) {
                newTaskIds.push(taskInfo[i].id);
            }
        }

        for (var id in this.tasksById) {
            if (!Craft.inArray(id, newTaskIds)) {
                this.tasksById[id].complete();
                this.completedTasks.push(this.tasksById[id]);
                delete this.tasksById[id];
            }
        }

        // Now display the tasks that are still around
        if (taskInfo && taskInfo.length) {
            var anyTasksRunning = false,
                anyTasksFailed = false;

            for (var i = 0; i < taskInfo.length; i++) {
                var info = taskInfo[i];

                if (!anyTasksRunning && info.status == 'running') {
                    anyTasksRunning = true;
                } else if (!anyTasksFailed && info.status == 'error') {
                    anyTasksFailed = true;
                }

                if (this.tasksById[info.id]) {
                    this.tasksById[info.id].updateStatus(info);
                } else {
                    this.tasksById[info.id] = new Craft.ResizeTaskProgress.Task(this.modal, info);

                    // Place it before the next already known task
                    for (var j = i + 1; j < taskInfo.length; j++) {
                        if (this.tasksById[taskInfo[j].id]) {
                            this.tasksById[info.id].$container.insertBefore(this.tasksById[taskInfo[j].id].$container);
                            break;
                        }
                    }
                }
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
