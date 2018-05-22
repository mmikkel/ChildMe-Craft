(function (window) {

    if (!window.Craft) return false;

    var elementIndex = Craft.BaseElementIndex.prototype;
    var init = elementIndex.init;
    elementIndex.init = function () {
        init.apply(this, arguments);
        Craft.ChildMePlugin.initElementIndex(this);
    };

    var _updateView = elementIndex._updateView;
    elementIndex._updateView = function () {
        _updateView.apply(this, arguments);
        Craft.ChildMePlugin.updateElementIndex(this);
    };

    // Hi-jack the `onDragStop` method in Craft.StructureTableSorter
    var structureTableSorter = Craft.StructureTableSorter.prototype;
    var onDragStop = structureTableSorter.onDragStop;
    structureTableSorter.onDragStop = function () {
        onDragStop.apply(this, arguments);
        var tableSorter = this
        Garnish.requestAnimationFrame(function () {
            Craft.ChildMePlugin.onDragStop(tableSorter);
        })
    };

    Craft.ChildMePlugin = {

        initialized: false,

        init: function (data) {
            if (this.initialized) {
                return;
            }
            this.initialized = true;
            this.data = data || {};
            this.addEventListeners();
        },

        initElementIndex: function (elementIndex) {
            this.elementIndex = elementIndex;
            if (elementIndex.settings.context === 'modal') {
                this.hideButtons();
            } else {
                this.createEntryTypeButtons();
            }
        },

        hideButtons: function () {
            this.elementIndex.$container.find('[data-childmeadd]').parent().hide();
        },

        showButtons: function () {
            this.elementIndex.$container.find('[data-childmeadd]').parent().show();
        },

        updateElementIndex: function (elementIndex) {
            this.elementIndex = elementIndex;
            if (this.elementIndex.settings.context === 'modal') {
                this.hideButtons();
            } else {
                this.showButtons();
                this.createEntryTypeButtons();
            }
        },

        createEntryTypeButtons: function () {

            if (this.entryTypeButtons) {
                for (var i = 0; i < this.entryTypeButtons.length; ++i) {
                    this.entryTypeButtons[i].destroy();
                }
            }

            var _self = this;
            var entryTypeButtons = [];
            var $button;

            $('[data-childmeadd]').each(function () {

                $button = $(this);

                var sectionHandle = $button.data('section') || null;
                if (!sectionHandle) return false;

                var entryTypes = _self.data[sectionHandle] || {};
                var entryTypeIds = Object.keys(entryTypes);
                if (entryTypeIds.length <= 1) return false;

                var menuHtml = '<div class="menu" data-align="center"><ul>';
                var menuOptions = [];

                for (var j = 0; j < entryTypeIds.length; ++j) {
                    menuOptions.push('<li><a data-type="' + entryTypeIds[j] + '" data-parent="' + $button.data('id') + '" data-section="' + sectionHandle + '">' + entryTypes[entryTypeIds[j]] + '</a></li>');
                }

                menuHtml += menuOptions.join('') + '</ul></div>';

                $button
                    .data('_childmemenu', $(menuHtml).appendTo($button))
                    .removeAttr('title')
                    .removeAttr('href');

                entryTypeButtons.push(new Garnish.MenuBtn($button));

            });

            this.entryTypeButtons = entryTypeButtons;

        },

        onEntryTypeOptionSelect: function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $option = $(e.currentTarget);
            var url = Craft.getCpUrl(['entries', $option.data('section'), 'new'].join('/'), {
                typeId: $option.data('type'),
                parentId: $option.data('parent')
            });
            window.location.href = url;
            this.closeActiveEntryTypeMenu();
        },

        closeActiveEntryTypeMenu: function () {
            if (this.openEntryTypeMenu) {
                this.openEntryTypeMenu.hide();
                this.openEntryTypeMenu = null;
            }
        },

        onChildMeButtonClick: function (e) {
            this.closeActiveEntryTypeMenu();
            var $target = $(e.currentTarget);
            var menu = $target.data('_childmemenu');
            if (menu) {
                e.preventDefault();
                e.stopPropagation();
                this.openEntryTypeMenu = menu;
                menu.show();
            }
        },

        onDragStop: function (tableSorter) {

            var $items = tableSorter.$items;
            $items.find('[data-childmeadd]').show();

            var maxLevels = tableSorter.maxLevels;
            if (!maxLevels) return false;

            var $hiddenItems = $items.filter(function () {
                return $(this).data('level') >= maxLevels;
            }).find('[data-childmeadd]').hide();

        },

        onDocClick: function (e) {
            if (!$(e.target).closest('[data-childmeadd]').length) {
                this.closeActiveEntryTypeMenu();
            }
        },

        addEventListeners: function () {
            Garnish.$doc
                .on('focus', '[data-childmeadd]', this.onChildMeButtonClick.bind(this))
                .on('blur', '[data-childmeadd]', this.closeActiveEntryTypeMenu.bind(this))
                .on('click', '[data-childmeadd] a', this.onEntryTypeOptionSelect.bind(this))
                .on('click', this.onDocClick.bind(this));
        },

        removeEventListeners: function () {
            Garnish.$doc
                .off('focus blur', '[data-childmeadd]')
                .off('click', '[data-childmeadd] a')
                .off('click', this.onDocClick.bind(this))
        }

    }

}(window));
