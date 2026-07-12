(function () {
    function getLocalStorage() {
        try {
            return window.localStorage || null;
        }
        catch (error) {
            return null;
        }
    }

    function loadCompletedKeys(storageKey) {
        const storage = getLocalStorage();

        if (!storageKey || !storage) {
            return new Set();
        }

        try {
            const parsed = JSON.parse(storage.getItem(storageKey) || '[]');

            if (!Array.isArray(parsed)) {
                return new Set();
            }

            return new Set(parsed.filter(function (value) {
                return typeof value === 'string' && value !== '';
            }));
        }
        catch (error) {
            return new Set();
        }
    }

    function saveCompletedKeys(storageKey, completedKeys) {
        const storage = getLocalStorage();

        if (!storageKey || !storage) {
            return;
        }

        try {
            storage.setItem(
                storageKey,
                JSON.stringify(Array.from(completedKeys))
            );
        }
        catch (error) {
            return;
        }
    }

    function clearCompletedKeys(storageKey) {
        const storage = getLocalStorage();

        if (!storageKey || !storage) {
            return;
        }

        try {
            storage.removeItem(storageKey);
        }
        catch (error) {
            return;
        }
    }

    function updateProgress(container, checkboxes) {
        const counter = container.querySelector('[data-switch-progress-counter]');

        if (!counter) {
            return;
        }

        const completeCount = checkboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;
        const totalCount = checkboxes.length;
        const moveLabel = totalCount === 1 ? 'move' : 'moves';

        counter.textContent = completeCount + ' of ' + totalCount + ' ' + moveLabel + ' complete';
    }

    function setRowState(checkbox) {
        const row = checkbox.closest('tr');

        if (row) {
            row.classList.toggle('is-switch-move-complete', checkbox.checked);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-switch-progress]').forEach(function (container) {
            const storageKey = container.dataset.switchStorageKey || '';

            if (container.dataset.switchClearOnLoad === '1') {
                clearCompletedKeys(storageKey);
            }

            const completedKeys = loadCompletedKeys(storageKey);
            const checkboxes = Array.from(
                container.querySelectorAll('.tt-switch-move-checkbox[data-switch-move-key]')
            );

            checkboxes.forEach(function (checkbox) {
                const moveKey = checkbox.dataset.switchMoveKey || '';

                checkbox.checked = completedKeys.has(moveKey);
                setRowState(checkbox);

                checkbox.addEventListener('click', function (event) {
                    event.stopPropagation();
                });

                checkbox.addEventListener('change', function () {
                    if (checkbox.checked) {
                        completedKeys.add(moveKey);
                    }
                    else {
                        completedKeys.delete(moveKey);
                    }

                    setRowState(checkbox);
                    saveCompletedKeys(storageKey, completedKeys);
                    updateProgress(container, checkboxes);
                });
            });

            updateProgress(container, checkboxes);
        });
    });
}());
