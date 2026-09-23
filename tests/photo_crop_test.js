// Exercise the actual editor script with canvas/DOM adapters, without a database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const php = fs.readFileSync(path.join(__dirname, '../photo_editor/edit.php'), 'utf8');
const script = php.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/<\?php[\s\S]*?\?>/g, 'null')
    .replace("loadSource(currentImage, 'Photo loaded.');", '');

function editor(pointerEvents, screenWidth = 390) {
    const elements = new Map();
    const ctx = new Proxy({}, { get: () => () => {} });
    function element() {
        return {
            width: 300, height: 150, clientWidth: screenWidth - 30,
            style: {}, listeners: {}, classList: { add() {}, remove() {} },
            getContext: () => ctx,
            addEventListener(name, fn) { this.listeners[name] = fn; },
            getBoundingClientRect() { return { left: 10, top: 20, width: this.width, height: this.height }; },
            setPointerCapture(id) { this.captured = id; }
        };
    }
    const win = element();
    win.innerHeight = 844;
    win.PointerEvent = pointerEvents;
    win.getComputedStyle = () => ({ paddingLeft: '12', paddingRight: '12' });
    const sandbox = {
        window: win, console,
        document: {
            getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); },
            querySelectorAll: () => [], createElement: element
        }
    };
    vm.createContext(sandbox);
    vm.runInContext(script, sandbox);
    vm.runInContext('sourceImage = { naturalWidth: 1200, naturalHeight: 800 }; renderEditor(true);', sandbox);
    const canvas = elements.get('photoCanvas');
    function send(target, type, fields = {}) {
        const event = { clientX: 30, clientY: 40, pointerId: 1, button: 0, isPrimary: true,
            preventDefault() { this.prevented = true; }, ...fields };
        target.listeners[type](event);
        return event;
    }
    return { canvas, win, elements, send,
        value: code => JSON.parse(JSON.stringify(vm.runInContext(code, sandbox))) };
}

for (const width of [320, 375, 390, 430, 768, 1024, 1440]) {
    const e = editor(true, width);
    const initial = e.value('cropRect');
    assert.ok(e.canvas.width <= width - 56, 'Canvas fits the stage padding');
    e.send(e.canvas, 'pointerdown');
    assert.equal(e.canvas.captured, 1);
    e.send(e.canvas, 'pointerup');
    assert.deepEqual(e.value('cropRect'), initial, 'A tap preserves the crop');
    e.send(e.canvas, 'pointerdown', { clientX: 100, clientY: 100 });
    e.send(e.canvas, 'pointermove', { clientX: 30, clientY: 40, pointerId: 2 });
    assert.deepEqual(e.value('cropRect'), initial, 'Second fingers cannot change the selection');
    e.send(e.canvas, 'pointerup', { clientX: 30, clientY: 40 });
    assert.deepEqual(e.value('cropRect'), { x: 20, y: 20, width: 70, height: 60 }, 'Reverse dragging works');
    const selected = e.value('cropRect');
    e.send(e.canvas, 'pointerdown');
    e.send(e.canvas, 'pointermove', { clientX: 200, clientY: 150 });
    e.send(e.canvas, 'pointercancel');
    assert.deepEqual(e.value('cropRect'), selected, 'Cancelled gestures restore the previous crop');
    const ratio = e.value('cropRect.width / canvas.width');
    const outputBefore = e.value('(() => { const c = buildEditedOutputCanvas(true); return [c.width, c.height]; })()');
    e.elements.get('photoStage').clientWidth = width / 2;
    e.send(e.win, 'resize');
    assert.ok(Math.abs(e.value('cropRect.width / canvas.width') - ratio) < 1e-9, 'Resize preserves source crop');
    assert.deepEqual(e.value('(() => { const c = buildEditedOutputCanvas(true); return [c.width, c.height]; })()'), outputBefore, 'Saved crop dimensions survive orientation changes');
    e.send(e.canvas, 'pointerdown');
    e.send(e.canvas, 'pointerup', { clientX: 9000, clientY: 9000 });
    assert.ok(e.value('cropRect.x + cropRect.width <= canvas.width && cropRect.y + cropRect.height <= canvas.height'), 'Crop stays inside image');
    e.value('setRotation(90); true');
    const rotated = e.value('cropRect');
    e.send(e.canvas, 'pointerdown');
    e.send(e.canvas, 'pointerup', { clientX: 200, clientY: 150 });
    assert.deepEqual(e.value('cropRect'), rotated, 'Rotation lock is respected');
}

const legacy = editor(false);
const touch = (x, y) => ({ identifier: 7, clientX: x, clientY: y });
legacy.send(legacy.canvas, 'touchstart', { touches: [touch(30, 40)], changedTouches: [touch(30, 40)] });
const move = legacy.send(legacy.canvas, 'touchmove', { changedTouches: [touch(130, 140)] });
assert.equal(move.prevented, true, 'Older iOS touch dragging prevents page scrolling');
legacy.send(legacy.canvas, 'touchend', { changedTouches: [touch(130, 140)] });
assert.deepEqual(legacy.value('cropRect'), { x: 20, y: 20, width: 100, height: 100 });
legacy.send(legacy.canvas, 'mousedown');
legacy.send(legacy.win, 'mouseup', { clientX: 100, clientY: 100 });
assert.deepEqual(legacy.value('cropRect'), { x: 20, y: 20, width: 70, height: 60 }, 'Legacy desktop mouse still works');
console.log('Photo crop interaction tests passed (7 viewport widths, pointer, touch, mouse, cancellation, resize, rotation lock).');
