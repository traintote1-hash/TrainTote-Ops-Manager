(function () {
    const box = document.getElementById('photoLightbox');
    if (!box) return;
    const image = box.querySelector('img');
    const closeButton = box.querySelector('button');
    let trigger = null;
    function close() {
        box.classList.remove('is-open'); image.removeAttribute('src');
        if (trigger) trigger.focus();
    }
    document.querySelectorAll('.tt-photo-button').forEach(function (button) {
        button.addEventListener('click', function () {
            trigger = button; image.src = button.dataset.src;
            image.alt = button.dataset.alt || 'Car photo'; box.classList.add('is-open'); closeButton.focus();
        });
    });
    closeButton.addEventListener('click', close);
    box.addEventListener('click', function (event) { if (event.target === box) close(); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && box.classList.contains('is-open')) close(); });
})();
