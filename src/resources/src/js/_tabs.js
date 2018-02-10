if (typeof Craft.ImageResizer === typeof undefined) {
    Craft.ImageResizer = {};
}

(function($) {

    Craft.TabsManager = Garnish.Base.extend({

        init: function() {

            this.$selectedTab = null;

            // Are there any tabs that link to anchors?
            var $tabs = $('#settings-tabs').find('> ul > li > a');

            for (var i = 0; i < $tabs.length; i++) {
                var $tab = $($tabs[i]),
                    href = $tab.attr('href');

                if (href && href.charAt(0) === '#') {
                    this.addListener($tab, 'click', function(ev) {
                        ev.preventDefault();
                        this.selectTab(ev.currentTarget);
                    });
                }

                if (!this.$selectedTab && $tab.hasClass('sel')) {
                    this.$selectedTab = $tab;
                }
            }
        },

        selectTab: function(tab) {
            var $tab = $(tab);

            if (this.$selectedTab) {
                if (this.$selectedTab.get(0) === $tab.get(0)) {
                    return;
                }
                this.deselectTab();
            }

            $tab.addClass('sel');
            $($tab.attr('href')).removeClass('hidden');
            Garnish.$win.trigger('resize');
            // Fixes Redactor fixed toolbars on previously hidden panes
            Garnish.$doc.trigger('scroll');
            this.$selectedTab = $tab;
        },

        deselectTab: function() {
            if (!this.$selectedTab) {
                return;
            }

            this.$selectedTab.removeClass('sel');
            if (this.$selectedTab.attr('href').charAt(0) === '#') {
                $(this.$selectedTab.attr('href')).addClass('hidden');
            }
            this.$selectedTab = null;
        }
    });

})(jQuery);
