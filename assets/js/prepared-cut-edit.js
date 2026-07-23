(() => {
    const form = document.getElementById('editCutForm');
    const count = document.getElementById('selectedCarCount');
    if (!form || !count) return;
    const boxes = [...form.querySelectorAll('input[name="car_ids[]"]')];
    const update = () => {
        const selected = boxes.filter(box => box.checked).length;
        count.textContent = `${selected} car${selected === 1 ? '' : 's'} selected`;
    };
    boxes.forEach(box => box.addEventListener('change', update));
    update();
})();
