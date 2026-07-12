(function () {
    const pendingOutcome = 'pending';
    const movedOutcome = 'moved';
    const notMovedOutcome = 'not_moved';
    const allowedReasonCodes = new Set([
        'track_blocked',
        'car_inaccessible',
        'industry_track_full',
        'bad_order',
        'wrong_car',
        'customer_not_ready',
        'locomotive_or_crew_issue',
        'other'
    ]);

    function getLocalStorage() {
        try {
            return window.localStorage || null;
        }
        catch (error) {
            return null;
        }
    }

    function readStoredValue(storageKey) {
        const storage = getLocalStorage();

        if (!storageKey || !storage) {
            return null;
        }

        try {
            return JSON.parse(storage.getItem(storageKey) || 'null');
        }
        catch (error) {
            return null;
        }
    }

    function writeStoredValue(storageKey, value) {
        const storage = getLocalStorage();

        if (!storageKey || !storage) {
            return;
        }

        try {
            storage.setItem(storageKey, JSON.stringify(value));
        }
        catch (error) {
            return;
        }
    }

    function clearStoredValue(storageKey) {
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

    function loadCompletedKeys(storageKey) {
        const parsed = readStoredValue(storageKey);

        if (!Array.isArray(parsed)) {
            return new Set();
        }

        return new Set(parsed.filter(function (value) {
            return typeof value === 'string' && value !== '';
        }));
    }

    function saveCompletedKeys(storageKey, completedKeys) {
        writeStoredValue(storageKey, Array.from(completedKeys));
    }

    function updateSimpleProgress(container, checkboxes) {
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

    function setSimpleRowState(checkbox) {
        const row = checkbox.closest('tr');

        if (row) {
            row.classList.toggle('is-switch-move-complete', checkbox.checked);
        }
    }

    function initializeSimpleProgress(container, storageKey) {
        const completedKeys = loadCompletedKeys(storageKey);
        const checkboxes = Array.from(
            container.querySelectorAll('.tt-switch-move-checkbox[data-switch-move-key]')
        );

        checkboxes.forEach(function (checkbox) {
            const moveKey = checkbox.dataset.switchMoveKey || '';

            checkbox.checked = completedKeys.has(moveKey);
            setSimpleRowState(checkbox);

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

                setSimpleRowState(checkbox);
                saveCompletedKeys(storageKey, completedKeys);
                updateSimpleProgress(container, checkboxes);
            });
        });

        updateSimpleProgress(container, checkboxes);
    }

    function normalizeMoveState(value) {
        const state = value && typeof value === 'object' && !Array.isArray(value)
            ? value
            : {};
        const outcome = [movedOutcome, notMovedOutcome].includes(state.outcome)
            ? state.outcome
            : pendingOutcome;

        return {
            outcome: outcome,
            reason_code: typeof state.reason_code === 'string' ? state.reason_code : '',
            reason_notes: typeof state.reason_notes === 'string' ? state.reason_notes : '',
            destination_track: typeof state.destination_track === 'string' ? state.destination_track : ''
        };
    }

    function loadMoveStates(storageKey, rows) {
        const parsed = readStoredValue(storageKey);
        const states = {};

        if (Array.isArray(parsed)) {
            parsed.forEach(function (moveKey) {
                if (typeof moveKey === 'string' && moveKey !== '') {
                    states[moveKey] = normalizeMoveState({outcome: movedOutcome});
                }
            });
        }
        else if (parsed && typeof parsed === 'object') {
            Object.keys(parsed).forEach(function (moveKey) {
                if (moveKey !== '') {
                    states[moveKey] = normalizeMoveState(parsed[moveKey]);
                }
            });
        }

        const currentStates = {};

        rows.forEach(function (row) {
            const moveKey = row.dataset.switchMoveKey || '';

            if (moveKey !== '') {
                currentStates[moveKey] = states[moveKey] || normalizeMoveState(null);
            }
        });

        return currentStates;
    }

    function getMoveControls(row) {
        return {
            checkbox: row.querySelector('.tt-switch-move-checkbox'),
            notMovedButton: row.querySelector('[data-switch-not-moved]'),
            outcomeInput: row.querySelector('[data-switch-outcome]'),
            reasonFields: row.querySelector('[data-switch-exception-fields]'),
            reasonSelect: row.querySelector('[data-switch-reason]'),
            reasonNotes: row.querySelector('[data-switch-reason-notes]'),
            destinationTrack: row.querySelector('[data-switch-destination-track]')
        };
    }

    function applyMoveState(row, state) {
        const controls = getMoveControls(row);
        const isMoved = state.outcome === movedOutcome;
        const isNotMoved = state.outcome === notMovedOutcome;

        row.classList.toggle('is-switch-move-complete', isMoved);
        row.classList.toggle('is-switch-move-skipped', isNotMoved);

        if (controls.checkbox) {
            controls.checkbox.checked = isMoved;
        }

        if (controls.notMovedButton) {
            controls.notMovedButton.setAttribute('aria-pressed', isNotMoved ? 'true' : 'false');
            controls.notMovedButton.classList.toggle('is-selected', isNotMoved);
        }

        if (controls.outcomeInput) {
            controls.outcomeInput.value = state.outcome;
        }

        if (controls.reasonFields) {
            controls.reasonFields.hidden = !isNotMoved;
        }

        if (controls.reasonSelect) {
            controls.reasonSelect.disabled = !isNotMoved;
            controls.reasonSelect.required = isNotMoved;
            controls.reasonSelect.value = state.reason_code;
        }

        if (controls.reasonNotes) {
            controls.reasonNotes.disabled = !isNotMoved;
            controls.reasonNotes.required = isNotMoved && state.reason_code === 'other';
            controls.reasonNotes.value = state.reason_notes;
        }

        if (controls.destinationTrack) {
            controls.destinationTrack.disabled = !isMoved;
            controls.destinationTrack.value = state.destination_track;
        }
    }

    function getOutcomeCounts(states) {
        const counts = {
            moved: 0,
            notMoved: 0,
            pending: 0,
            invalidExceptions: 0
        };

        Object.keys(states).forEach(function (moveKey) {
            const state = states[moveKey];

            if (state.outcome === movedOutcome) {
                counts.moved += 1;
            }
            else if (state.outcome === notMovedOutcome) {
                counts.notMoved += 1;

                if (
                    !allowedReasonCodes.has(state.reason_code)
                    || (state.reason_code === 'other' && state.reason_notes.trim() === '')
                ) {
                    counts.invalidExceptions += 1;
                }
            }
            else {
                counts.pending += 1;
            }
        });

        return counts;
    }

    function updateCompletionSummary(container, states) {
        const counts = getOutcomeCounts(states);
        const counter = container.querySelector('[data-switch-progress-counter]');
        const completeButton = container.querySelector('[data-switch-complete-button]');
        const completionStatus = container.querySelector('[data-switch-completion-status]');

        if (counter) {
            counter.textContent = counts.moved + ' moved, '
                + counts.notMoved + ' not moved, '
                + counts.pending + ' pending';
        }

        if (completeButton) {
            completeButton.disabled = counts.pending > 0 || counts.invalidExceptions > 0;
        }

        if (completionStatus) {
            if (counts.pending > 0) {
                completionStatus.textContent = 'Resolve every pending move before completing the switch list.';
            }
            else if (counts.invalidExceptions > 0) {
                completionStatus.textContent = 'Choose a reason for every Not Moved car. Other also requires a note.';
            }
            else {
                completionStatus.textContent = 'Ready to record ' + counts.moved
                    + ' moved and ' + counts.notMoved + ' not moved.';
            }
        }

        return counts;
    }

    function initializeExceptionProgress(container, storageKey) {
        const rows = Array.from(container.querySelectorAll('[data-switch-move-row]'));
        const states = loadMoveStates(storageKey, rows);
        const form = container.querySelector('[data-switch-completion-form]');

        function saveAndRefresh() {
            writeStoredValue(storageKey, states);
            updateCompletionSummary(container, states);
        }

        rows.forEach(function (row) {
            const moveKey = row.dataset.switchMoveKey || '';
            const controls = getMoveControls(row);

            if (!moveKey || !states[moveKey]) {
                return;
            }

            applyMoveState(row, states[moveKey]);

            if (controls.checkbox) {
                controls.checkbox.addEventListener('click', function (event) {
                    event.stopPropagation();
                });

                controls.checkbox.addEventListener('change', function () {
                    states[moveKey].outcome = controls.checkbox.checked
                        ? movedOutcome
                        : pendingOutcome;
                    applyMoveState(row, states[moveKey]);
                    saveAndRefresh();
                });
            }

            if (controls.notMovedButton) {
                controls.notMovedButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    states[moveKey].outcome = states[moveKey].outcome === notMovedOutcome
                        ? pendingOutcome
                        : notMovedOutcome;
                    applyMoveState(row, states[moveKey]);
                    saveAndRefresh();
                });
            }

            if (controls.reasonSelect) {
                controls.reasonSelect.addEventListener('change', function () {
                    states[moveKey].reason_code = controls.reasonSelect.value;
                    applyMoveState(row, states[moveKey]);
                    saveAndRefresh();
                });
            }

            if (controls.reasonNotes) {
                controls.reasonNotes.addEventListener('input', function () {
                    states[moveKey].reason_notes = controls.reasonNotes.value;
                    saveAndRefresh();
                });
            }

            if (controls.destinationTrack) {
                controls.destinationTrack.addEventListener('input', function () {
                    states[moveKey].destination_track = controls.destinationTrack.value;
                    saveAndRefresh();
                });
            }
        });

        writeStoredValue(storageKey, states);
        updateCompletionSummary(container, states);

        if (form) {
            form.addEventListener('submit', function (event) {
                const counts = updateCompletionSummary(container, states);

                if (counts.pending > 0 || counts.invalidExceptions > 0) {
                    event.preventDefault();
                    return;
                }

                const confirmed = window.confirm(
                    'Complete this switch list? '
                    + counts.moved + ' car(s) will move and '
                    + counts.notMoved + ' car(s) will remain in place.'
                );

                if (!confirmed) {
                    event.preventDefault();
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-switch-clear-storage-key]').forEach(function (element) {
            clearStoredValue(element.dataset.switchClearStorageKey || '');
        });

        document.querySelectorAll('[data-switch-progress]').forEach(function (container) {
            const storageKey = container.dataset.switchStorageKey || '';

            if (container.dataset.switchClearOnLoad === '1') {
                clearStoredValue(storageKey);
            }

            if (container.dataset.switchExceptions === '1') {
                initializeExceptionProgress(container, storageKey);
            }
            else {
                initializeSimpleProgress(container, storageKey);
            }
        });
    });
}());
