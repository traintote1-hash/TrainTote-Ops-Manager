/*
=========================================================
AUTO SUBMIT FILTERS
=========================================================
*/

function getFilterSectionTitle(header) {

    if (!header) {

        return '';

    }

    const copy = header.cloneNode(true);
    const arrow = copy.querySelector('.arrow');

    if (arrow) {

        arrow.remove();

    }

    return copy.textContent.trim();

}

document.querySelectorAll('.auto-filter').forEach(control => {

    control.addEventListener('change', () => {

        const section =
            control.closest('.filter-section');

        const header = section
            ? section.querySelector('.section-header')
            : null;

        if (header) {

            const title =
                getFilterSectionTitle(header);

            if (title) {

                localStorage.setItem(
                    'filter_' + title,
                    'open'
                );

            }

        }

        const filterForm =
            document.getElementById('filterForm');

        if (filterForm) {

            filterForm.submit();

        }

    });

});

/*
=========================================================
PER PAGE
=========================================================
*/

const perPage = document.getElementById('perPage');

if (perPage) {

    perPage.addEventListener('change', function () {

        const url = new URL(window.location);

        url.searchParams.set(
            'per_page',
            this.value
        );

        url.searchParams.set(
            'page',
            1
        );

        window.location = url;

    });

}

/*
=========================================================
ROW CLICK
=========================================================
*/

document.querySelectorAll('.clickable-row').forEach(row => {

    row.addEventListener('click', () => {

        window.location =
            row.dataset.href;

    });

});

/*
=========================================================
SELECT ALL
=========================================================
*/

const selectAll =
    document.getElementById('selectAll');

if (selectAll) {

    selectAll.addEventListener('change', function () {

        document.querySelectorAll(
            'input[name="equipment_ids[]"]'
        ).forEach(box => {

            box.checked =
                this.checked;

        });

    });

}
/*
=========================================================
COLLAPSIBLE FILTERS
=========================================================
*/

document.querySelectorAll('.section-header').forEach(header => {

    header.addEventListener('click', () => {

        const content =
            header.nextElementSibling;

        const arrow =
            header.querySelector('.arrow');

        if (!content || !arrow) {

            return;

        }

        content.classList.toggle('collapsed');

        const title =
            getFilterSectionTitle(header);

        if (!title) {

            return;

        }

        if (content.classList.contains('collapsed')) {

            arrow.textContent = '►';

            localStorage.setItem(
                'filter_' + title,
                'closed'
            );

        }
        else {

            arrow.textContent = '▼';

            localStorage.setItem(
                'filter_' + title,
                'open'
            );

        }

    });

});

/*
=========================================================
DEFAULT OPEN STATES
=========================================================
*/

document.querySelectorAll('.filter-section').forEach(section => {

    const header =
        section.querySelector('.section-header');

    const title =
        getFilterSectionTitle(header);

    const content =
        section.querySelector('.section-content');

    const arrow =
        section.querySelector('.arrow');

    if (!header || !title || !content || !arrow) {

        return;

    }

    const storageKey =
        'filter_' + title;

    let saved =
        localStorage.getItem(storageKey);

    if (saved !== 'open' && saved !== 'closed') {

        const legacyOpenArrow =
            localStorage.getItem(
                'filter_\u25BC ' + title
            );

        const legacyClosedArrow =
            localStorage.getItem(
                'filter_\u25BA ' + title
            );

        const legacySaved =
            legacyOpenArrow === 'open' ||
            legacyOpenArrow === 'closed'
                ? legacyOpenArrow
                : legacyClosedArrow;

        if (
            legacySaved === 'open' ||
            legacySaved === 'closed'
        ) {

            saved = legacySaved;

            localStorage.setItem(
                storageKey,
                saved
            );

        }

    }

    /*
    ---------------------------------------------
    Saved state wins
    ---------------------------------------------
    */

    if (saved === 'open') {

        content.classList.remove(
            'collapsed'
        );

        arrow.textContent = '▼';

        return;

    }

    if (saved === 'closed') {

        content.classList.add(
            'collapsed'
        );

        arrow.textContent = '►';

        return;

    }

    /*
    ---------------------------------------------
    First visit defaults
    ---------------------------------------------
    */

    if (title === 'Equipment Type') {

        content.classList.remove(
            'collapsed'
        );

        arrow.textContent = '▼';

    }
    else {

        content.classList.add(
            'collapsed'
        );

        arrow.textContent = '►';

    }

});
/*
=========================================================
PREVENT ROW CLICK FROM BUTTONS
=========================================================
*/

document.querySelectorAll(
    '.btn, .btn-sm, .btn-outline-primary'
).forEach(button => {

    button.addEventListener('click', function (e) {

        e.stopPropagation();

    });

});

/*
=========================================================
PREVENT ROW CLICK FROM CHECKBOXES
=========================================================
*/

document.querySelectorAll(
    'input[type="checkbox"]'
).forEach(box => {

    box.addEventListener('click', function (e) {

        e.stopPropagation();

    });

});

/*
=========================================================
ACTIVE SORT COLUMN
=========================================================
*/

const params =
    new URLSearchParams(
        window.location.search
    );

const currentSort =
    params.get('sort');

document.querySelectorAll(
    '.sort-link'
).forEach(link => {

    if (
        currentSort &&
        link.href.includes(
            'sort=' + currentSort
        )
    ) {

        link.classList.add(
            'active'
        );

    }

});

/*
=========================================================
SEARCH ENTER KEY
=========================================================
*/

const searchBox =
    document.querySelector(
        'input[name="search"]'
    );

if (searchBox) {

    searchBox.addEventListener(
        'keypress',
        function (e) {

            if (e.key === 'Enter') {

                e.preventDefault();

                document.getElementById(
                    'filterForm'
                ).submit();

            }

        }
    );

}

/*
=========================================================
DONE
=========================================================
*/
