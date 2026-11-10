const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const {runInNewContext} = require('node:vm');

const source = readFileSync(join(__dirname, '../../bin/backend/controls/OpenItems/OpenItems.js'), 'utf8');

function fixture() {
    let definition;
    const requests = [];
    const renders = [];
    const errors = [];
    const heights = [];
    const gridData = [];
    const rowChanges = [];
    const timers = new Map();
    const currencyRequests = [];
    const total = {
        offsetHeight: 70,
        hidden: false,
        attributes: {},
        setAttribute(key, value) { this.attributes[key] = value; },
        removeAttribute(key) { delete this.attributes[key]; }
    };

    runInNewContext(source, {
        Class: function(options) { return options; },
        define(name, names, factory) {
            const dependencies = {
                Locale: {get: (group, key) => key},
                Ajax: {get: (names, callback, options) => currencyRequests.push({names, callback, options})},
                'qui/controls/contextmenu/Item': class { constructor(options) { this.options = options; } },
                Mustache: {render(template, data) { renders.push(data); return 'Rendered totals'; }}
            };
            definition = factory(...names.map(name => dependencies[name] || {}));
        },
        console: {error: error => errors.push(error)},
        setTimeout(callback) {
            const id = timers.size + 1;
            timers.set(id, callback);
            return id;
        },
        clearTimeout: id => timers.delete(id)
    });

    const panel = Object.assign({}, definition, {
        $refreshId: 0,
        $destroyed: false,
        $refreshTotalsOnly: false,
        $currentSearch: '',
        $Total: total,
        $Search: {value: ''},
        $Currency: {
            value: 'EUR',
            getAttribute() { return this.value; },
            setAttribute(key, value) { if (key === 'value') this.value = value; },
            appendChild() {}, enable() {}, hide() {},
            getContextMenu(callback) { callback({setAttribute() {}}); }
        },
        $currentRecordsUserId: 'customer-42',
        $Grid: {
            options: {perPage: 10, page: 2, sortOn: 'customerId', sortBy: 'ASC'},
            setData: data => gridData.push(data),
            getData: () => [{userId: 'customer-42'}],
            setDataByRow: (index, row) => rowChanges.push({index, row}),
            deleteRow: index => rowChanges.push({index, deleted: true}),
            setHeight: height => heights.push(height),
            setWidth() {}
        },
        Loader: {visible: false, show() { this.visible = true; }, hide() { this.visible = false; }},
        getContent: () => ({getSize: () => ({x: 1420, y: 600})}),
        $refreshButtonStatus() {},
        $parseGridRow: row => row,
        $search: params => new Promise((resolve, reject) => requests.push({params, resolve, reject}))
    });
    return {panel, total, requests, renders, errors, heights, gridData, rowChanges, timers, currencyRequests};
}

function metrics(render) {
    return [...render.metrics, ...render.settlementMetrics];
}

function response(prefix = 'current') {
    return {
        grid: {data: [{userId: 'customer-42'}], total: 155},
        totals: Object.fromEntries([
            ['open_items_count', 320],
            ...['net', 'vat', 'gross', 'paid', 'open'].map(field => ['display_' + field, prefix + '-' + field])
        ])
    };
}

test('the permanent bar starts with six placeholders and never hides while loading', () => {
    const {panel, renders, total, heights} = fixture();
    total.setAttribute('aria-busy', 'true');
    panel.$renderTotals();
    assert.equal(total.hidden, false);
    assert.equal(metrics(renders[0]).length, 6);
    assert.ok(metrics(renders[0]).every(metric => metric.value === '—'));
    assert.equal(renders[0].count, '—');
    assert.equal(total.attributes['aria-busy'], 'true');
    assert.equal(heights.at(-1), 510);
});

test('refresh renders all six metrics, the selected currency and filtered customer count', async () => {
    const {panel, renders, requests, total} = fixture();
    panel.$Search.value = '  C-42  ';
    const loading = panel.refresh();
    assert.equal(total.hidden, false);
    assert.equal(total.attributes['aria-busy'], 'true');
    assert.deepEqual({...requests[0].params}, {
        perPage: 10, page: 2, sortOn: 'customerId', sortBy: 'ASC', search: 'C-42', currency: 'EUR'
    });
    requests[0].resolve(response());
    await loading;
    assert.deepEqual(Array.from(metrics(renders[0]), metric => metric.type), ['items', 'net', 'vat', 'gross', 'paid', 'open']);
    assert.deepEqual(Array.from(metrics(renders[0]), metric => metric.value), [320, 'current-net', 'current-vat', 'current-gross', 'current-paid', 'current-open']);
    assert.equal(renders[0].count, 155);
    assert.equal(renders[0].currency, 'EUR');
    assert.equal(total.attributes['aria-busy'], undefined);
    assert.equal(total.hidden, false);
});

test('late responses cannot replace the current currency or filtered totals', async () => {
    const {panel, requests, renders, gridData} = fixture();
    const oldLoading = panel.refresh();
    panel.$Currency.value = 'USD';
    const latestLoading = panel.refresh();
    requests[1].resolve(response('USD'));
    await latestLoading;
    requests[0].resolve(response('EUR'));
    await oldLoading;
    assert.equal(gridData.length, 1);
    assert.equal(renders.length, 1);
    assert.equal(renders[0].currency, 'USD');
    assert.equal(metrics(renders[0])[1].value, 'USD-net');
});

test('a totals-only refresh preserves the expanded customer records', async () => {
    const {panel, requests, renders, gridData} = fixture();
    panel.$refreshTotalsOnly = true;
    const loading = panel.refresh();
    requests[0].resolve(response());
    await loading;
    assert.equal(gridData.length, 0);
    assert.equal(panel.$currentRecordsUserId, 'customer-42');
    assert.equal(panel.$refreshTotalsOnly, false);
    assert.equal(renders.length, 1);
});

test('after a payment the customer row and totals refresh in the selected currency', async () => {
    const {panel, requests, renders, rowChanges, gridData} = fixture();
    const updating = panel.$refreshUserEntry('customer-42');
    assert.deepEqual({...requests[0].params}, {userId: 'customer-42', currency: 'EUR'});
    requests[0].resolve(response('paid'));
    await new Promise(setImmediate);
    assert.equal(rowChanges.length, 1);
    assert.equal(requests.length, 2);
    requests[1].resolve(response('paid'));
    await updating;
    assert.equal(gridData.length, 0);
    assert.equal(panel.$currentRecordsUserId, 'customer-42');
    assert.equal(metrics(renders[0])[4].value, 'paid-paid');
});

test('a stale customer-row refresh cannot change the newly filtered grid', async () => {
    const {panel, requests, rowChanges} = fixture();
    const updating = panel.$refreshUserEntry('customer-42');
    panel.$Currency.value = 'USD';
    const loading = panel.refresh();
    requests[1].resolve(response());
    await loading;
    requests[0].resolve(response());
    await updating;
    assert.equal(rowChanges.length, 0);
    assert.equal(requests.length, 2);
});

test('a failed request leaves the last totals visible and clears loading', async () => {
    const {panel, requests, total, renders, errors} = fixture();
    panel.$renderTotals(response().totals, 155);
    const loading = panel.refresh();
    requests[0].reject(new Error('Search failed'));
    await loading;
    assert.equal(total.hidden, false);
    assert.equal(renders.length, 1);
    assert.equal(total.attributes['aria-busy'], undefined);
    assert.equal(panel.Loader.visible, false);
    assert.equal(errors.length, 1);
});

test('zero items remain actual zero values in all six badges', () => {
    const {panel, renders} = fixture();
    const totals = {open_items_count: 0};
    for (const field of ['net', 'vat', 'gross', 'paid', 'open']) totals['display_' + field] = '0,00 €';
    panel.$renderTotals(totals, 0);
    assert.equal(renders[0].count, 0);
    assert.equal(metrics(renders[0])[0].value, 0);
    assert.ok(metrics(renders[0]).slice(1).every(metric => metric.value === '0,00 €'));
});

test('closing the panel ignores pending responses and clears delayed searches', async () => {
    const {panel, requests, renders, timers} = fixture();
    panel.$searchDelay = 1;
    timers.set(1, () => {});
    const loading = panel.refresh();
    panel.$onDestroy();
    requests[0].resolve(response());
    await loading;
    assert.equal(renders.length, 0);
    assert.equal(timers.size, 0);
});

test('resizing measures the current bar height including wrapped badges and padding', () => {
    const {panel, total, heights} = fixture();
    total.offsetHeight = 170;
    panel.$onResize();
    assert.equal(heights.at(-1), 410);
    total.offsetHeight = 1000;
    panel.$onResize();
    assert.equal(heights.at(-1), 100);
});

test('the loader is visible while currencies load and remains until the first grid response', async () => {
    const {panel, currencyRequests, requests} = fixture();
    panel.$onInject();
    assert.equal(panel.Loader.visible, true);
    assert.equal(currencyRequests.length, 1);
    assert.equal(requests.length, 0);
    currencyRequests[0].callback([{code: 'EUR', sign: '€'}], {code: 'EUR'});
    assert.equal(panel.Loader.visible, true);
    assert.equal(requests.length, 1);
    requests[0].resolve(response());
    await new Promise(setImmediate);
    assert.equal(panel.Loader.visible, false);
});

test('currency initialization errors clear the loader without leaving a blocked panel', () => {
    const {panel, currencyRequests, errors} = fixture();
    panel.$onInject();
    currencyRequests[0].options.onError(new Error('Currency lookup failed'));
    assert.equal(panel.Loader.visible, false);
    assert.equal(errors.length, 1);
});

test('Enter submits the native search on the first page, while normal typing waits', async () => {
    const {panel, requests, timers} = fixture();
    panel.$Search.value = ' C-42 ';
    panel.$onSearchKeyUp({type: 'keyup', key: 'x'});
    assert.equal(timers.size, 0);
    panel.$onSearchKeyUp({type: 'keyup', key: 'Enter'});
    assert.equal(timers.size, 1);
    const timer = timers.values().next().value;
    const loading = timer();
    assert.equal(requests[0].params.page, 1);
    assert.equal(requests[0].params.search, 'C-42');
    requests[0].resolve(response());
    await loading;
});

test('clearing the native search refreshes on the first page', async () => {
    const {panel, requests, timers} = fixture();
    panel.$currentSearch = 'C-42';
    panel.$onSearchKeyUp({type: 'input'});
    const loading = timers.values().next().value();
    assert.equal(requests[0].params.search, '');
    assert.equal(requests[0].params.page, 1);
    requests[0].resolve(response());
    await loading;
});
