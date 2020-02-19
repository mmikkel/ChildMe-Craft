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

        getSiteHandle: function () {
            var siteId = Craft.getLocalStorage('BaseElementIndex.siteId');
            return this.data['sites']['site:' + siteId] || null;
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
                if (!sectionHandle) {
                    return false;
                }

                var entryTypes = _self.data['entryTypes'][sectionHandle] || {};
                var entryTypeIds = Object.keys(entryTypes);
                if (entryTypeIds.length <= 1) {
                    return false;
                }

                var menuHtml = '<div class="menu" data-align="center"><ul>';
                var menuOptions = [];
                var typeId;
                var href;

                for (var j = 0; j < entryTypeIds.length; ++j) {
                    typeId = parseInt(entryTypeIds[j].split(':').pop(), 10);
                    if (!typeId || isNaN(typeId)) {
                        continue;
                    }
                    menuOptions.push('<li><a data-type="' + typeId + '" data-parent="' + $button.data('id') + '" data-section="' + sectionHandle + '">' + entryTypes[entryTypeIds[j]] + '</a></li>');
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
            var siteHandle = this.getSiteHandle();
            var segments = ['entries', $option.data('section'), 'new'];
            var variables = {
                typeId: $option.data('type'),
                parentId: $option.data('parent'),
            };
            if (siteHandle) {
                variables.site = siteHandle;
            }
            var url = Craft.getCpUrl(segments.join('/'), variables);
            window.location.href = url;
            this.closeActiveEntryTypeMenu();
        },

        closeActiveEntryTypeMenu: function () {
            if (this.openEntryTypeMenu) {
                this.openEntryTypeMenu.hide();
                this.openEntryTypeMenu = null;
            }
        },

        onChildMeButtonFocus: function (e) {
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

        onChildMeButtonClick: function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $target = $(e.currentTarget);
            var menu = $target.data('_childmemenu');
            if (menu) {
                return false;
            }
            var url = $target.attr('href');
            var siteId = Craft.getLocalStorage('BaseElementIndex.siteId');
            var siteHandle = this.data['sites']['site:' + siteId] || null;
            if (siteHandle) {
                url = url.split('?');
                url = url[0] + '/' + siteHandle + '?' + url[1];
            }
            window.location.href = url;
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
                .on('focus', '[data-childmeadd]', this.onChildMeButtonFocus.bind(this))
                .on('click', '[data-childmeadd]', this.onChildMeButtonClick.bind(this))
                .on('blur', '[data-childmeadd]', this.closeActiveEntryTypeMenu.bind(this))
                .on('click', '[data-childmeadd] a', this.onEntryTypeOptionSelect.bind(this))
                .on('click', this.onDocClick.bind(this));
        },

        removeEventListeners: function () {
            Garnish.$doc
                .off('focus blur click', '[data-childmeadd]')
                .off('click', '[data-childmeadd] a')
                .off('click', this.onDocClick.bind(this))
        }

    }

}(window));
