import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const runtimeSource = await readFile(
    new URL('../../public/tracking-assets/tracking.js', import.meta.url),
    'utf8',
);

class MemoryStorage {
    constructor() {
        this.values = new Map();
    }

    getItem(key) {
        return this.values.get(key) ?? null;
    }

    setItem(key, value) {
        this.values.set(key, String(value));
    }
}

function createRuntime(config, context) {
    const scripts = [];
    const warnings = [];
    const appendScript = script => scripts.push(script);
    const document = {
        readyState: 'complete',
        scripts,
        title: 'Tracking test',
        head: { appendChild: appendScript },
        documentElement: { appendChild: appendScript },
        addEventListener() {},
        dispatchEvent() {},
        getElementById(id) {
            return scripts.find(script => script.id === id) ?? null;
        },
        createElement() {
            return {
                id: '',
                src: '',
                async: false,
                defer: false,
                attributes: {},
                setAttribute(name, value) {
                    this.attributes[name] = value;
                },
            };
        },
        querySelector() {
            return null;
        },
    };
    const window = {
        REPLICANTFY_TRACKING_CONFIG: config,
        REPLICANTFY_TRACKING_CONTEXT: context,
        REPLICANTFY_TRACKING_QUEUE: [],
        crypto: { randomUUID: () => 'runtime-test-event-id' },
        localStorage: new MemoryStorage(),
        sessionStorage: new MemoryStorage(),
        location: { href: 'https://store.example.test/checkout/pix/ORDER-1' },
    };

    vm.runInNewContext(runtimeSource, {
        window,
        document,
        CustomEvent: class {
            constructor(name, options) {
                this.name = name;
                this.detail = options?.detail;
            }
        },
        console: {
            warn: (...values) => warnings.push(values),
        },
        Date,
        Math,
        Set,
        Map,
        Array,
        Object,
        String,
        Number,
        Boolean,
        JSON,
        RegExp,
        encodeURIComponent,
        parseFloat,
        parseInt,
        isFinite,
    });

    return { document, scripts, warnings, window };
}

test('loads the UTMify optimization pixel sitewide before purchase', () => {
    const runtime = createRuntime({
        integrations: [{
            id: 1,
            provider: 'utmify',
            public_id: 'UTMIFY-PIXEL-1',
            browser_enabled: true,
            events: {
                pix_generated: true,
                purchase: true,
            },
            settings: {
                optimization_pixel_enabled: true,
            },
        }],
    }, {
        event: 'page_view',
    });

    assert.equal(runtime.window.pixelId, 'UTMIFY-PIXEL-1');
    assert.equal(
        runtime.scripts.some(script => script.src === 'https://cdn.utmify.com.br/scripts/pixel/pixel.js'),
        true,
    );
});

test('does not load an UTMify optimization pixel unless explicitly enabled', () => {
    const runtime = createRuntime({
        integrations: [{
            id: 1,
            provider: 'utmify',
            public_id: 'UTMIFY-PIXEL-1',
            browser_enabled: true,
            events: {
                purchase: true,
            },
            settings: {},
        }],
    }, {
        event: 'page_view',
    });

    assert.equal(runtime.window.pixelId, undefined);
    assert.equal(
        runtime.scripts.some(script => script.src === 'https://cdn.utmify.com.br/scripts/pixel/pixel.js'),
        false,
    );
});

test('selects the UTMify optimization pixel that matches the product scope', () => {
    const runtime = createRuntime({
        integrations: [{
            id: 1,
            provider: 'utmify',
            public_id: 'UTMIFY-PRODUCT-2',
            browser_enabled: true,
            events: { purchase: true },
            scope_mode: 'include',
            product_ids: ['2'],
            settings: { optimization_pixel_enabled: true },
        }, {
            id: 2,
            provider: 'utmify',
            public_id: 'UTMIFY-PRODUCT-1',
            browser_enabled: true,
            events: { purchase: true },
            scope_mode: 'include',
            product_ids: ['1'],
            settings: { optimization_pixel_enabled: true },
        }],
    }, {
        context_type: 'product',
        event: 'view_content',
        contents: [{
            id: '1',
            name: 'Chocolate',
            quantity: 1,
            item_price: 190.80,
        }],
    });

    assert.equal(runtime.window.pixelId, 'UTMIFY-PRODUCT-1');
    assert.equal(runtime.warnings.length, 0);
});

test('uses the frozen order integration snapshot for the purchase event', () => {
    const runtime = createRuntime({
        integrations: [{
            id: 10,
            provider: 'meta',
            public_id: '111111111111111',
            browser_enabled: true,
            events: {
                page_view: true,
                purchase: true,
            },
            scope_mode: 'global',
            product_ids: [],
            settings: {},
        }],
    }, {
        context_type: 'order',
        event: 'purchase',
        event_id: 'stable-order-event-id',
        order_id: 'ORDER-1',
        value: 190.80,
        currency: 'BRL',
        contents: [{
            id: '123',
            name: 'Chocolate',
            quantity: 1,
            item_price: 190.80,
        }],
        integrations: [{
            id: 20,
            provider: 'meta',
            public_id: '222222222222222',
            browser_enabled: true,
            events: {
                purchase: true,
            },
            scope_mode: 'include',
            product_ids: ['999'],
            settings: {},
        }],
    });

    const calls = runtime.window.fbq.queue.map(call => Array.from(call));

    assert.equal(
        calls.some(call => call[0] === 'trackSingle'
            && call[1] === '111111111111111'
            && call[2] === 'PageView'),
        true,
    );
    assert.equal(
        calls.some(call => call[0] === 'trackSingle'
            && call[1] === '222222222222222'
            && call[2] === 'Purchase'
            && call[4]?.eventID === 'stable-order-event-id'),
        true,
    );
    assert.equal(
        calls.some(call => call[0] === 'trackSingle'
            && call[1] === '111111111111111'
            && call[2] === 'Purchase'),
        false,
    );
});

test('sends every Google Ads conversion label configured for the same account', () => {
    const runtime = createRuntime({
        integrations: [{
            id: 30,
            provider: 'google_ads',
            public_id: 'AW-123456789',
            browser_enabled: true,
            events: { purchase: true },
            scope_mode: 'global',
            settings: { conversion_label: 'LABEL_ONE' },
        }, {
            id: 31,
            provider: 'google_ads',
            public_id: 'AW-123456789',
            browser_enabled: true,
            events: { purchase: true },
            scope_mode: 'global',
            settings: { conversion_label: 'LABEL_TWO' },
        }],
    }, {
        event: 'page_view',
    });

    runtime.window.ReplicantTracking.track('Purchase', {
        order_id: 'ORDER-ADS-1',
        value: 190.80,
        currency: 'BRL',
        contents: [{
            id: '1',
            name: 'Chocolate',
            quantity: 1,
            item_price: 190.80,
        }],
    }, {
        dedupe: 'none',
    });

    const calls = Array.from(runtime.window.dataLayer, call => Array.from(call));
    const destinations = calls
        .filter(call => call[0] === 'event' && call[1] === 'conversion')
        .map(call => call[2]?.send_to)
        .sort();

    assert.deepEqual(destinations, [
        'AW-123456789/LABEL_ONE',
        'AW-123456789/LABEL_TWO',
    ]);
});
