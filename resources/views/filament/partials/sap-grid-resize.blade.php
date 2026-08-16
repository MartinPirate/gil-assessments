{{--
    Draggable column widths for the A/R Invoice line grid.

    The client lets you pull a column edge to widen it, which is the only way
    to read a value the column is too narrow for — "FG WHS" arriving as
    "FG W…" is the usual one. The table is already `table-layout: fixed`, so a
    width on the header cell governs the whole column.

    Widths are remembered per column in localStorage: having dragged a column
    wider, nobody expects it back at its old size after a save.

    Plain DOM rather than Alpine — the repeater re-renders on every Livewire
    round trip and this has to survive that, which an observer does and a
    component binding does not.
--}}
<script>
    (() => {
        const STORE = 'sap-grid-widths';
        const MIN = 48;

        const load = () => {
            try {
                return JSON.parse(localStorage.getItem(STORE)) || {};
            } catch {
                // A corrupt entry must not take the grid down with it.
                return {};
            }
        };

        const save = (widths) => {
            try {
                localStorage.setItem(STORE, JSON.stringify(widths));
            } catch {
                // Private browsing, quota, whatever — resizing still works for
                // this session, it just will not be remembered.
            }
        };

        const apply = (table) => {
            const widths = load();
            table.querySelectorAll('thead th').forEach((th, index) => {
                const width = widths[index];
                if (width) {
                    th.style.width = `${width}px`;
                }
            });
        };

        const startDrag = (event, th, table) => {
            event.preventDefault();
            event.stopPropagation();

            const index = [...th.parentNode.children].indexOf(th);
            const startX = event.pageX;
            const startWidth = th.getBoundingClientRect().width;

            document.body.classList.add('sap-col-resizing');

            const move = (moveEvent) => {
                const width = Math.max(MIN, startWidth + (moveEvent.pageX - startX));
                th.style.width = `${width}px`;
                paintBands(table);
            };

            const stop = () => {
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', stop);
                document.body.classList.remove('sap-col-resizing');

                const widths = load();
                widths[index] = Math.round(th.getBoundingClientRect().width);
                save(widths);
                paintBands(table);
            };

            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', stop);
        };

        /*
            Paint the derived columns down the full height of the grid.

            The client tints the columns it computes for you all the way to the
            bottom of the table, not just behind the rows that have data. The
            empty rows below are a background gradient rather than real rows —
            drawing dozens of empty repeater items to carry a colour would be
            expensive — so the tint is painted as vertical bands on the same
            container, positioned from the header cells.

            Recomputed whenever a column is dragged, because the bands have to
            follow the column they belong to.
        */
        /**
         * The nearest ancestor that actually scrolls horizontally, stopping at
         * the repeater container.
         */
        const scrollerFor = (table, container) => {
            let node = table.parentElement;

            while (node && node !== container.parentElement) {
                if (node.scrollWidth > node.clientWidth + 1) {
                    return node;
                }

                node = node.parentElement;
            }

            return container;
        };

        const paintBands = (table) => {
            const container = table.closest('.fi-fo-table-repeater');
            const firstRow = table.querySelector('tbody tr');

            if (!container || !firstRow) {
                return;
            }

            const headers = [...table.querySelectorAll('thead th')];
            const tableBox = table.getBoundingClientRect();
            const stops = [];

            [...firstRow.children].forEach((cell, index) => {
                if (!cell.querySelector('.sap-derived')) {
                    return;
                }

                const th = headers[index];

                if (!th) {
                    return;
                }

                // Offsets within the table, so they hold whatever the scroll
                // position happens to be when this runs.
                const box = th.getBoundingClientRect();
                stops.push([Math.round(box.left - tableBox.left), Math.round(box.right - tableBox.left)]);
            });

            if (stops.length === 0) {
                container.style.removeProperty('--sap-bands');

                return;
            }

            /*
                The gradient box has to be the width of the *table*, not the
                container. The container is narrower — the table scrolls inside
                it — so a gradient sized to 100% clipped every band past the
                container's right edge and put the closing stop in the wrong
                place, which is why the tint drifted off its columns.
            */
            const width = Math.round(table.scrollWidth || tableBox.width);
            const tint = 'var(--sap-readonly, #e8eef6)';
            const parts = [];
            let cursor = 0;

            stops.sort((a, b) => a[0] - b[0]).forEach(([from, to]) => {
                parts.push(`transparent ${cursor}px ${from}px`, `${tint} ${from}px ${to}px`);
                cursor = to;
            });

            parts.push(`transparent ${cursor}px ${width}px`);

            container.style.setProperty('--sap-bands', `linear-gradient(90deg, ${parts.join(', ')})`);
            container.style.setProperty('--sap-bands-w', `${width}px`);

            /*
                One measurement covers both the horizontal scroll and any
                padding on the container: where the table's left edge currently
                sits relative to the container's.
            */
            const track = () => {
                const delta = table.getBoundingClientRect().left - container.getBoundingClientRect().left;
                container.style.setProperty('--sap-bands-x', `${Math.round(delta)}px`);
            };

            const scroller = scrollerFor(table, container);

            if (scroller && !scroller.dataset.sapBandScroll) {
                scroller.dataset.sapBandScroll = '1';
                scroller.addEventListener('scroll', track, { passive: true });
            }

            track();
        };

        const decorate = (table) => {
            apply(table);
            paintBands(table);

            table.querySelectorAll('thead th').forEach((th) => {
                if (th.dataset.sapResizable) {
                    return;
                }

                th.dataset.sapResizable = '1';
                th.style.position = 'relative';

                const handle = document.createElement('span');
                handle.className = 'sap-col-resizer';
                handle.setAttribute('aria-hidden', 'true');
                handle.addEventListener('mousedown', (event) => startDrag(event, th, table));

                // Double-click resets this column to its natural width, which
                // is the way out of having dragged one down to nothing.
                handle.addEventListener('dblclick', (event) => {
                    event.preventDefault();
                    th.style.width = '';
                    const widths = load();
                    delete widths[[...th.parentNode.children].indexOf(th)];
                    save(widths);
                });

                th.appendChild(handle);
            });
        };

        const scan = () => document
            .querySelectorAll('.sap-contents table')
            .forEach((table) => decorate(table));

        window.addEventListener('resize', scan);
        document.addEventListener('DOMContentLoaded', scan);
        document.addEventListener('livewire:navigated', scan);

        // The repeater is re-rendered on every round trip, which throws the
        // handles away; put them back as soon as the new markup lands.
        new MutationObserver(() => scan()).observe(document.body, {
            childList: true,
            subtree: true,
        });

        scan();
    })();
</script>
