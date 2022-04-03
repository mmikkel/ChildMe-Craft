(function (window) {

    if (!window.Craft) {
        return false;
    };

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
            }
        },

        createEntryTypeMenu: function ($button) {

            var sectionHandle = $button.data('section') || null;
            if (!sectionHandle) {
                return null;
            }

            var entryTypes = this.data['entryTypes'][sectionHandle] || {};
            var entryTypeIds = Object.keys(entryTypes);
            if (entryTypeIds.length <= 1) {
                return null;
            }

            var menuHtml = '<div class="menu" data-align="center" style="z-index:1;"><ul>';
            var menuOptions = [];
            var typeId;

            var siteHandle = $button.data('site') || null;

            for (var j = 0; j < entryTypeIds.length; ++j) {
                typeId = parseInt(entryTypeIds[j].split(':').pop(), 10);
                if (!typeId || isNaN(typeId)) {
                    continue;
                }
                menuOptions.push('<li><a data-type="' + typeId + '" data-parent="' + $button.data('id') + '" data-section="' + sectionHandle + (siteHandle ? '" data-site="' + siteHandle : '') + '" tabindex="0">' + entryTypes[entryTypeIds[j]] + '</a></li>');
            }

            menuHtml += menuOptions.join('') + '</ul></div>';

            $button
                .data('_childmemenu', $(menuHtml).appendTo($button))
                .removeAttr('title');

            var menu = Garnish.MenuBtn($button);

            return menu;
        },

        getEntryTypeMenu: function ($button) {
            var menu = $button.data('_childmemenu');
            if (menu === undefined) {
                this.createEntryTypeMenu($button);
                return $button.data('_childmemenu');
            }
            return menu;
        },

        onEntryTypeOptionSelect: function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $option = $(e.currentTarget);
            var segments = ['entries', $option.data('section'), 'new'];
            var variables = {
                typeId: $option.data('type'),
                parentId: $option.data('parent'),
            };
            if ($option.data('site')) {
                variables['site'] = $option.data('site');
            }
            var url = Craft.getCpUrl(segments.join('/'), variables);
            window.location.href = url;
            this.closeActiveEntryTypeMenu();
        },

        closeActiveEntryTypeMenu: function () {
            if (!this.openEntryTypeMenu) {
                return;
            }
            this.openEntryTypeMenu.hide();
            this.openEntryTypeMenu = null;
        },

        // Position a fixed menu relative to the trigger
        positionMenu: function (menu) {
            if (!menu) {
                return;
            }
            var $menu = $(menu);
            var $anchor = $menu.parent('[data-childmeadd]');
            if (!$anchor.length) {
                return;
            }
            var rect = $anchor.get(0).getBoundingClientRect();
            var top = rect.height + rect.top;
            var left = rect.left;
            $menu.css({
                top: top,
                left: left,
                position: 'fixed'
            });
        },

        onChildMeButtonFocus: function (e) {
            this.closeActiveEntryTypeMenu();
            var $button = $(e.currentTarget);
            var menu = this.getEntryTypeMenu($button);
            if (menu) {
                e.preventDefault();
                e.stopPropagation();
                this.openEntryTypeMenu = menu;
                this.positionMenu(menu);
                menu.show();
            }
        },

        onChildMeButtonClick: function (e) {
            this.closeActiveEntryTypeMenu();
            var $button = $(e.currentTarget);
            var menu = this.getEntryTypeMenu($button);
            if (!menu) {
                window.location.href = $button.data('href');
            }
            e.preventDefault();
            e.stopPropagation();
            this.openEntryTypeMenu = menu;
            this.positionMenu(menu);
            menu.show();
        },

        onDragStop: function (tableSorter) {

            var $items = tableSorter.$items;
            $items.find('[data-childmeadd]').show();

            var maxLevels = tableSorter.maxLevels;
            if (!maxLevels) {
                return false
            };

            $items.filter(function () {
                return $(this).data('level') >= maxLevels;
            }).find('[data-childmeadd]').hide();

        },

        onDocClick: function (e) {
            if (!$(e.target).closest('[data-childmeadd]').length) {
                this.closeActiveEntryTypeMenu();
            }
        },

        onDocScroll: function (e) {
            // If there's a menu open, anchor it to the... anchor.
            if (!this.openEntryTypeMenu) {
                return;
            }
            this.positionMenu(this.openEntryTypeMenu);
        },

        addEventListeners: function () {
            Garnish.$doc
                .on('focus', '[data-childmeadd]', this.onChildMeButtonFocus.bind(this))
                .on('click', '[data-childmeadd]', this.onChildMeButtonClick.bind(this))
                .on('blur', '[data-childmeadd]', this.closeActiveEntryTypeMenu.bind(this))
                .on('click', '[data-childmeadd] a', this.onEntryTypeOptionSelect.bind(this))
                .on('click', this.onDocClick.bind(this))
                .on('scroll', this.onDocScroll.bind(this));
        },

        removeEventListeners: function () {
            Garnish.$doc
                .off('focus blur click', '[data-childmeadd]')
                .off('click', '[data-childmeadd] a')
                .off('click', this.onDocClick.bind(this))
                .off('scroll', this.onDocScroll.bind(this));
        }

    }

}(window));
