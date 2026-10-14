(() => {
    const page = document.querySelector('.tt-status-page');
    if (!page) return;

    // Move the original controls instead of duplicating names, IDs or values.
    // Both views therefore submit exactly the same form, including unsaved edits.
    const fields = [];
    page.querySelectorAll('[data-status-equipment]').forEach(row => {
        const card = page.querySelector(`[data-status-card="${row.dataset.statusEquipment}"]`);
        if (!card) return;
        row.querySelectorAll('[data-status-field]').forEach(cell => {
            const field = cell.dataset.statusField;
            const slot = card.querySelector(`[data-status-slot="${field}"]`);
            if (!slot) return;
            const control = document.createElement('div');
            control.append(...cell.childNodes);
            const input = control.querySelector('select, input:not([type="hidden"])');
            if (input) {
                if (!input.id) input.id = `status-card-${field}-${row.dataset.statusEquipment}`;
                card.querySelector(`label[for="status-card-${field}-${row.dataset.statusEquipment}"]`)
                    .htmlFor = input.id;
            }
            cell.append(control);
            fields.push({ cell, slot, control });
        });
    });

    function updateView() {
        const cards = page.dataset.displayView === 'cards';
        fields.forEach(({ cell, slot, control }) => (cards ? slot : cell).append(control));
    }
    updateView();
    new MutationObserver(updateView).observe(page, { attributes: true, attributeFilter: ['data-display-view'] });

    const selectAll = document.getElementById('selectAll');
    const selectAllCards = document.getElementById('selectAllCards');
    const selections = [...page.querySelectorAll('input[name="equipment_ids[]"]')];
    function updateSelection() {
        const count = selections.filter(input => input.checked).length;
        [selectAll, selectAllCards].filter(Boolean).forEach(input => {
            input.checked = selections.length > 0 && count === selections.length;
            input.indeterminate = count > 0 && count < selections.length;
        });
    }
    [selectAll, selectAllCards].filter(Boolean).forEach(input => {
        input.addEventListener('change', () => {
            selections.forEach(selection => { selection.checked = input.checked; });
            updateSelection();
        });
    });
    selections.forEach(input => input.addEventListener('change', updateSelection));
    updateSelection();
})();
