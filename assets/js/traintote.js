document.querySelectorAll('.clickable-row').forEach(row => {

    row.addEventListener('click', function(e) {

        if (
            e.target.closest('a') ||
            e.target.closest('button') ||
            e.target.closest('input')
        ) {
            return;
        }

        window.location = this.dataset.href;

    });

});