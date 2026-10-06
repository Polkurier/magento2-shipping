/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The first column of a grid whose rows are put in order by dragging them by a handle, as
 * Magento's dynamic rows are: the row is lifted and follows the pointer, a bar across the table
 * marks the gap it will land in, and it lands there when let go. The page scrolls when
 * the pointer is held near the top or bottom of the window, and Escape puts the row back. The column has no caption
 * and doesn't sort; the grid is always in the order dragging sets. A drop shows the new order at
 * once and posts the ids of the rows shown, in order, to sortUrl, where they're fitted in among
 * the rows not shown.
 *
 * From the keyboard, Alt+Up and Alt+Down on a focused handle move its row one place, and the
 * handle keeps the focus.
 *
 * With groupBy set, the grid shows each value of that field as a block of rows, and a row can be
 * moved only within its own block.
 */
define([
    'jquery',
    'underscore',
    'mage/translate',
    'uiRegistry',
    'Magento_Ui/js/grid/columns/column',
    'Magento_Ui/js/modal/alert',
    'Polkurier_Shipping/js/request'
], function ($, _, $t, registry, Column, alert, request) {
    'use strict';

    const EVENTS = '.polkurierArrange',
        DRAGGED = '_dragged';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Polkurier_Shipping/grid/cells/drag-handle',
            headerTmpl: 'Polkurier_Shipping/grid/columns/drag-handle',
            label: '',
            sortable: false,
            // The grid's own column dragging would move it off the first place.
            draggable: false,
            fieldClass: {
                'data-grid-draggable-row-cell': true
            },
            sortUrl: '',
            groupBy: '',
            modules: {
                // "source" is the column's own; this is the grid's data provider.
                rows: '${ $.provider }'
            }
        },

        initialize: function () {
            this._super();
            this.requests = request();
            registry.get(this.parentName, this.keepFirst.bind(this));

            return this;
        },

        /**
         * Keeps the column first, whatever a saved view or the columns' own dragging puts there.
         *
         * @param {Object} columns the grid's columns
         */
        keepFirst: function (columns) {
            const pin = function () {
                if (columns.elems().indexOf(this) > 0) {
                    columns.insertChild(this, 0);
                }
            }.bind(this);

            columns.on('positions', function () {
                _.defer(pin);
            });
            _.defer(pin);
        },

        /**
         * Rows are dragged by their handle, never opened for editing by it.
         *
         * @returns {Boolean}
         */
        hasFieldAction: function () {
            return false;
        },

        /**
         * @returns {String} the field that identifies a row
         */
        indexField: function () {
            return this.rows().get('storageConfig.indexField') || 'config_id';
        },

        /**
         * @param {Object|undefined} record the row's entry; none while a row the grid no longer
         *                                  shows is taken away
         * @returns {String}
         */
        getId: function (record) {
            return record ? String(record[this.indexField()]) : '';
        },

        /**
         * @param {HTMLElement} handle
         */
        initHandle: function (handle) {
            $(handle).off(EVENTS)
                .on('mousedown' + EVENTS, this.lift.bind(this))
                .on('keydown' + EVENTS, this.step.bind(this));
        },

        /**
         * Alt+Up or Alt+Down moves the row one place, within its block.
         *
         * @param {KeyboardEvent} event
         */
        step: function (event) {
            const by = {ArrowUp: -1, ArrowDown: 1}[event.key],
                row = $(event.currentTarget).closest('tr'),
                rows = row.parent().children('tr.data-row'),
                from = rows.index(row),
                to = from + (by || 0),
                id = this.rowId.bind(this),
                ids = rows.map(function () {
                    return id(this);
                }).get();

            if (!event.altKey || !by || this.drag || to < 0 || to >= rows.length
                || this.group(rows.eq(to)) !== this.group(row)) {
                return;
            }
            event.preventDefault();
            ids.splice(to, 0, ids.splice(from, 1)[0]);
            this.arrange(ids);
            // The grid shows the new order in the rows it has, so the handle to keep the focus on
            // is the one now in the row's new place.
            _.defer(function () {
                rows.parent().children('tr.data-row').eq(to).find('.draggable-handle').trigger('focus');
            });
        },

        /**
         * Lifts the row: a copy of it, in a table of its own, follows the pointer.
         *
         * Where every other row sits is measured once, now: nothing in the table moves until the
         * row lands, so the spot under the pointer is worked out from those positions on every
         * move and again where the button is let go, however fast the pointer went.
         *
         * @param {MouseEvent} event
         */
        lift: function (event) {
            const row = $(event.currentTarget).closest('tr'),
                table = row.closest('table'),
                copy = $(table[0].cloneNode(false)).append($('<tbody/>').append(row.clone())),
                others = row.parent().children('tr.data-row').not(row),
                group = this.group.bind(this),
                own = group(row),
                // The other rows of its block, whose gaps are the only ones it can land in.
                mates = others.map(function (index) {
                    return group(this) === own ? index : null;
                }).get();

            if (event.which !== 1 || this.drag) {
                return;
            }
            event.preventDefault();
            copy.find('tr').removeClass('data-row');
            copy.find('td').each(function (index) {
                $(this).css('width', row.children().eq(index).outerWidth());
            });
            copy.addClass('polkurier-dragged-row ' + DRAGGED).css({
                left: table.offset().left,
                top: row.offset().top,
                width: table.outerWidth()
            }).appendTo(document.body);

            this.drag = {
                row: row.addClass(DRAGGED),
                copy: copy,
                others: others,
                // In page coordinates, top to bottom: the middle of each other row, and the gaps
                // the row can land in, above each of them and below the last.
                middles: others.map(function () {
                    return $(this).offset().top + $(this).outerHeight() / 2;
                }).get(),
                gaps: others.map(function () {
                    return $(this).offset().top;
                }).get().concat(others.length ? [others.last().offset().top + others.last().outerHeight()] : []),
                // Its own place: landing there changes nothing.
                from: row.parent().children('tr.data-row').index(row),
                // The first and last gaps of its block; alone in it, only its own.
                first: mates.length ? _.first(mates) : row.parent().children('tr.data-row').index(row),
                last: mates.length ? _.last(mates) + 1 : row.parent().children('tr.data-row').index(row),
                line: $('<div class="polkurier-drop-line"/>').css({
                    left: table.offset().left,
                    width: table.outerWidth()
                }).appendTo(document.body),
                grabY: event.pageY - row.offset().top,
                clientY: event.clientY,
                scroll: null
            };
            $(document)
                .on('mousemove' + EVENTS, this.move.bind(this))
                .on('mouseup' + EVENTS, this.land.bind(this))
                .on('keydown' + EVENTS, this.escape.bind(this));
            this.autoScroll();
        },

        /**
         * @param {MouseEvent} event
         */
        move: function (event) {
            this.drag.clientY = event.clientY;
            this.follow(event.pageY);
        },

        /**
         * Moves the copy with the pointer and puts the bar in the gap the row would land in; no
         * bar where it would land where it already is.
         *
         * @param {Number} pageY
         */
        follow: function (pageY) {
            const drag = this.drag,
                slot = this.slot(pageY);

            drag.copy.css('top', pageY - drag.grabY);
            drag.line.css('top', drag.gaps[slot]).toggle(slot !== drag.from && drag.gaps.length > 0);
        },

        /**
         * @param {Number} pageY
         * @returns {Number} how many of the other rows end up above the dragged one
         */
        slot: function (pageY) {
            const drag = this.drag;

            return Math.min(Math.max(_.sortedIndex(drag.middles, pageY), drag.first), drag.last);
        },

        /**
         * Read from the attribute each time: the grid redraws a new order by giving its rows
         * other entries, and jQuery's data() would keep answering with the row's first one.
         *
         * @param {HTMLElement|jQuery} row
         * @returns {String} the id of the entry the row shows
         */
        rowId: function (row) {
            return String($(row).find('[data-id]').attr('data-id'));
        },

        /**
         * @param {HTMLElement|jQuery} row
         * @returns {String} the value of groupBy the row has, or '' without groupBy
         */
        group: function (row) {
            const field = this.indexField(),
                id = this.rowId(row),
                item = this.groupBy ? _.find(this.rows().get('data.items') || [], function (entry) {
                    return String(entry[field]) === id;
                }) : null;

            return item ? String(item[this.groupBy]) : '';
        },

        /**
         * While the pointer is held near the top or bottom of the window, scrolls the page, so that
         * a row can be taken past what's in view.
         */
        autoScroll: function () {
            const drag = this.drag,
                edge = 60,
                step = function () {
                    let by = 0;

                    if (!this.drag) {
                        return;
                    }
                    if (drag.clientY < edge) {
                        by = -Math.ceil((edge - drag.clientY) / 4);
                    } else if (drag.clientY > window.innerHeight - edge) {
                        by = Math.ceil((drag.clientY - window.innerHeight + edge) / 4);
                    }
                    if (by) {
                        window.scrollBy(0, by);
                        this.follow(drag.clientY + window.pageYOffset);
                    }
                    drag.scroll = window.requestAnimationFrame(step);
                }.bind(this);

            drag.scroll = window.requestAnimationFrame(step);
        },

        /**
         * Escape puts the row back where it was.
         *
         * @param {KeyboardEvent} event
         */
        escape: function (event) {
            if (event.key === 'Escape') {
                this.drop();
            }
        },

        /**
         * Ends the drag, leaving the table as it was.
         *
         * @returns {Object} the drag that ended
         */
        drop: function () {
            const drag = this.drag;

            $(document).off(EVENTS);
            window.cancelAnimationFrame(drag.scroll);
            drag.copy.remove();
            drag.line.remove();
            drag.row.removeClass(DRAGGED);
            this.drag = null;

            return drag;
        },

        /**
         * Puts the row where the pointer let go of it, and saves the order.
         *
         * @param {MouseEvent} event
         */
        land: function (event) {
            const slot = this.slot(event.pageY),
                drag = this.drop(),
                id = this.rowId.bind(this),
                before = drag.row.parent().children('tr.data-row').map(function () {
                    return id(this);
                }).get(),
                ids = drag.others.map(function () {
                    return id(this);
                }).get();

            ids.splice(slot, 0, id(drag.row));
            if (!_.isEqual(ids, before)) {
                this.arrange(ids);
            }
        },

        /**
         * Shows the rows in the order given, from the grid's data, and saves it.
         *
         * @param {Array<String>} ids
         */
        arrange: function (ids) {
            const field = this.indexField(),
                byId = _.indexBy(this.rows().get('data.items') || [], function (item) {
                    return String(item[field]);
                });

            this.rows().set('data.items', _.compact(ids.map(function (id) {
                return byId[id];
            })));
            // The grid keeps the lists it was sent, in their old order, and would show one again
            // when a filter or page it was sent for comes back.
            this.rows().storage().clearRequests();
            this.requests.post(this.sortUrl, {ids: ids}, {
                done: function (response) {
                    if (response.error) {
                        this.fail(response.message);
                    }
                }.bind(this),
                fail: function () {
                    this.fail($t('The new order could not be saved.'));
                }.bind(this)
            });
        },

        /**
         * @param {String} message
         */
        fail: function (message) {
            alert({title: $t('Order of the carriers'), content: _.escape(message)});
            this.rows().reload({refresh: true});
        }
    });
});
