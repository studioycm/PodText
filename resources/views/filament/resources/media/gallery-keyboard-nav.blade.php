{{-- Arrow-key navigation between gallery card selection checkboxes.
     Focus a card checkbox, then Left/Right move to the neighboring card
     (direction-aware for RTL) and Up/Down move one grid row; Space keeps
     its native toggle. Delegated once per browser session, inert outside
     a table content grid. --}}
<script data-podtext-gallery-keyboard-nav>
    (() => {
        if (window.podtextGalleryKeyboardNav) {
            return;
        }
        window.podtextGalleryKeyboardNav = true;

        document.addEventListener('keydown', (event) => {
            const target = event.target;

            if (
                !(target instanceof HTMLInputElement) ||
                !target.classList.contains('fi-ta-record-checkbox') ||
                event.altKey ||
                event.ctrlKey ||
                event.metaKey ||
                event.shiftKey
            ) {
                return;
            }

            const grid = target.closest('.fi-ta-content-grid');

            if (!grid) {
                return;
            }

            const boxes = Array.from(grid.querySelectorAll('.fi-ta-record-checkbox')).filter((box) => !box.disabled);
            const index = boxes.indexOf(target);

            if (index === -1) {
                return;
            }

            const rtl = getComputedStyle(grid).direction === 'rtl';
            const records = boxes.map((box) => box.closest('.fi-ta-record'));
            const firstTop = records[0].offsetTop;
            const columns = records.filter((record) => Math.abs(record.offsetTop - firstTop) < 2).length;
            const step = {
                ArrowRight: rtl ? -1 : 1,
                ArrowLeft: rtl ? 1 : -1,
                ArrowDown: columns,
                ArrowUp: -columns,
            }[event.key];

            if (step === undefined) {
                return;
            }

            event.preventDefault();
            boxes[index + step]?.focus();
        });
    })();
</script>
