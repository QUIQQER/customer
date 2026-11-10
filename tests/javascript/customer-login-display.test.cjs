const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const {runInNewContext} = require('node:vm');

const source = readFileSync(join(__dirname, '../../bin/backend/controls/customer/Activation.js'), 'utf8');

class Element extends EventTarget {
    constructor() {
        super();
        this.dataset = {};
        this.attributes = {};
        this.value = '0';
        this.disabled = false;
    }

    setAttribute(key, value) { this.attributes[key] = value; }
    getAttribute(key) { return this.attributes[key] ?? null; }
    append(...children) { this.children = children; }
    after(sibling) { this.sibling = sibling; }
    remove() { this.removed = true; }
    click() { if (!this.disabled) this.dispatchEvent(new Event('click')); }
}

function fixture({active = 0, allowed = 1, value = '0', id = 'activation-42'} = {}) {
    let definition;
    const input = new Element();
    input.value = value;
    input.setAttribute('aria-describedby', 'description-42');
    const user = {active, getAttribute(key) { return this[key]; }};
    const changes = [];
    const fieldEvents = [];
    input.addEventListener('input', () => fieldEvents.push(['input', input.value]));
    input.addEventListener('change', () => fieldEvents.push(['change', input.value]));

    runInNewContext(source, {
        Class: function(options) { return options; },
        define(name, names, factory) {
            const dependencies = {
                Users: {get: () => user},
                Locale: {get: (group, key) => key}
            };
            definition = factory(...names.map(name => dependencies[name] || {}));
        },
        document: {createElement: () => new Element()},
        Event
    });

    const control = Object.assign({}, definition, {
        getElm: () => input,
        getId: () => id,
        getAttribute: key => key === 'allowed' ? allowed : 42,
        fireEvent: (name, args) => changes.push([name, args[1]])
    });
    control.$onClick = control.$onClick.bind(control);
    control.$onImport();
    return {control, input, user, changes, fieldEvents, button: control.$Button, status: control.$Status};
}

test('activation can be requested and cancelled before saving without activating the user', () => {
    const view = fixture();
    assert.equal(view.input.type, 'hidden');
    assert.equal(view.button.type, 'button');
    assert.equal(view.input.value, '0');
    assert.ok(view.status.textContent.endsWith('.inactive'));

    view.button.click();
    assert.equal(view.input.value, '1');
    assert.equal(view.user.active, 0);
    assert.equal(view.button.disabled, false);
    assert.ok(view.button.textContent.endsWith('.cancel'));
    assert.ok(view.status.textContent.endsWith('.pending'));

    view.button.click();
    assert.equal(view.input.value, '0');
    assert.equal(view.user.active, 0);
    assert.ok(view.button.textContent.endsWith('.action'));
    assert.ok(view.status.textContent.endsWith('.inactive'));
    assert.deepEqual(view.fieldEvents, [['input', '1'], ['change', '1'], ['input', '0'], ['change', '0']]);
    assert.deepEqual(view.changes, [['change', '1'], ['change', '0']]);
});

for (const active of [1, '1']) {
    test(`already activated customers cannot request activation (${typeof active})`, () => {
        const view = fixture({active, value: '1'});
        assert.equal(view.button.disabled, true);
        assert.equal(view.input.value, '0');
        assert.ok(view.status.textContent.endsWith('.active'));
        view.button.click();
        assert.equal(view.input.value, '0');
        assert.equal(view.changes.length, 0);
    });
}

for (const allowed of [0, '0']) {
    test(`globally disabled customer login clears pending activation (${typeof allowed})`, () => {
        const view = fixture({allowed, value: '1'});
        assert.equal(view.button.disabled, true);
        assert.equal(view.input.value, '0');
        view.button.click();
        assert.equal(view.input.value, '0');
        assert.equal(view.changes.length, 0);
    });
}

test('a restored pending request can be cancelled with the string options used by data-qui', () => {
    const view = fixture({active: '0', allowed: '1', value: '1'});
    assert.ok(view.status.textContent.endsWith('.pending'));
    view.button.click();
    assert.equal(view.input.value, '0');
    view.button.click();
    assert.equal(view.input.value, '1');
});

test('reopening the saved active customer shows its real state and clears the one-time request', () => {
    const before = fixture();
    before.button.click();
    const after = fixture({active: 1});
    assert.equal(after.input.value, '0');
    assert.ok(after.status.textContent.endsWith('.active'));
    assert.equal(after.button.disabled, true);
});

test('status announcements and descriptions are associated with the button and have unique IDs', () => {
    const first = fixture({id: 'activation-42'});
    const second = fixture({id: 'activation-43'});
    assert.notEqual(first.status.id, second.status.id);
    assert.equal(first.status.getAttribute('role'), 'status');
    assert.equal(first.button.getAttribute('aria-describedby'), first.status.id + ' description-42');
});

test('destroy removes the rendered control and its click handler', () => {
    const view = fixture();
    view.control.$onDestroy();
    assert.equal(view.control.$Wrapper.removed, true);
    view.button.click();
    assert.equal(view.input.value, '0');
    assert.equal(view.changes.length, 0);
});
