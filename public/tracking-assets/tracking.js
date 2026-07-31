/**
 * Replicantfy browser tracking
 *
 * Public contract:
 *
 * window.REPLICANTFY_TRACKING_CONFIG = [
 *   {
 *     id: 1,
 *     provider: 'meta' | 'tiktok' | 'ga4' | 'google_ads' | 'utmify',
 *     public_id: 'provider-public-id',
 *     browser_enabled: true,
 *     events: { page_view: true, purchase: true },
 *     scope_mode: 'all' | 'include' | 'exclude',
 *     product_ids: ['1', '2'],
 *     settings: {}
 *   }
 * ];
 *
 * window.REPLICANTFY_TRACKING_CONTEXT = {
 *   event: 'page_view' | 'view_content' | 'initiate_checkout' |
 *          'pix_generated' | 'purchase',
 *   event_id: 'optional-stable-event-id',
 *   value: 10.50,
 *   currency: 'BRL',
 *   content_ids: ['1'],
 *   contents: [{ id: '1', name: 'Product', quantity: 1, item_price: 10.50 }],
 *   order_id: 'optional-order-number',
 *   payment_method: 'optional-payment-method'
 * };
 *
 * Runtime API:
 *   window.ReplicantTracking.track(event, payload, options)
 *   window.ReplicantTracking.trackCartMutation(cartResponse, mutation)
 *   window.ReplicantTracking.trackCheckoutPayment(form)
 *
 * This bridge deliberately accepts only commerce fields. It never reads or
 * forwards customer name, e-mail, phone, document, address or card fields.
 */
(function (window, document) {
    'use strict';

    const CONFIG_GLOBAL = 'REPLICANTFY_TRACKING_CONFIG';
    const CONTEXT_GLOBAL = 'REPLICANTFY_TRACKING_CONTEXT';
    const QUEUE_GLOBAL = 'REPLICANTFY_TRACKING_QUEUE';
    const STORAGE_PREFIX = 'replicantfy_tracking:v1:';

    const EVENT_ALIASES = {
        pageview: 'PageView',
        page_view: 'PageView',
        viewcontent: 'ViewContent',
        view_content: 'ViewContent',
        view_item: 'ViewContent',
        addtocart: 'AddToCart',
        add_to_cart: 'AddToCart',
        initiatecheckout: 'InitiateCheckout',
        initiate_checkout: 'InitiateCheckout',
        begin_checkout: 'InitiateCheckout',
        addpaymentinfo: 'AddPaymentInfo',
        add_payment_info: 'AddPaymentInfo',
        pixgenerated: 'PixGenerated',
        pix_generated: 'PixGenerated',
        purchase: 'Purchase',
        completepayment: 'Purchase',
        complete_payment: 'Purchase',
    };

    const EVENT_KEYS = {
        PageView: 'page_view',
        ViewContent: 'view_content',
        AddToCart: 'add_to_cart',
        InitiateCheckout: 'initiate_checkout',
        AddPaymentInfo: 'add_payment_info',
        PixGenerated: 'pix_generated',
        Purchase: 'purchase',
    };

    const GA4_EVENTS = {
        PageView: 'page_view',
        ViewContent: 'view_item',
        AddToCart: 'add_to_cart',
        InitiateCheckout: 'begin_checkout',
        AddPaymentInfo: 'add_payment_info',
        PixGenerated: 'pix_generated',
        Purchase: 'purchase',
    };

    const TIKTOK_EVENTS = {
        ViewContent: 'ViewContent',
        AddToCart: 'AddToCart',
        InitiateCheckout: 'InitiateCheckout',
        AddPaymentInfo: 'AddPaymentInfo',
        PixGenerated: 'PixGenerated',
        Purchase: 'Purchase',
    };

    const state = {
        prepared: false,
        booted: false,
        contextTracked: false,
        integrations: [],
        documentSeen: new Set(),
        metaIds: new Set(),
        tiktokIds: new Set(),
        gaIds: new Set(),
        googleAdsIds: new Set(),
        gtagStarted: false,
        utmifyPixelId: null,
        utmifyWarningIds: new Set(),
    };

    function isObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function parseObject(value) {
        if (isObject(value)) return value;
        if (typeof value !== 'string' || value.trim() === '') return {};

        try {
            const parsed = JSON.parse(value);
            return isObject(parsed) ? parsed : {};
        } catch (_) {
            return {};
        }
    }

    function parseArray(value) {
        if (Array.isArray(value)) return value;
        if (typeof value !== 'string' || value.trim() === '') return [];

        try {
            const parsed = JSON.parse(value);
            if (Array.isArray(parsed)) return parsed;
        } catch (_) {
            // A comma/newline separated value is also accepted.
        }

        return value.split(/[,;\r\n]+/).map(item => item.trim()).filter(Boolean);
    }

    function toBoolean(value, fallback) {
        if (value === undefined || value === null || value === '') return fallback;
        if (typeof value === 'boolean') return value;
        if (typeof value === 'number') return value !== 0;
        return !['0', 'false', 'off', 'no'].includes(String(value).toLowerCase());
    }

    function finiteNumber(value, fallback) {
        const number = Number(value);
        return Number.isFinite(number) ? number : fallback;
    }

    function positiveInteger(value, fallback) {
        const number = Math.trunc(finiteNumber(value, fallback));
        return number > 0 ? number : fallback;
    }

    function safeString(value, maxLength) {
        if (value === undefined || value === null) return '';
        return String(value).trim().slice(0, maxLength || 255);
    }

    function normalizeCurrency(value) {
        const currency = safeString(value || 'BRL', 3).toUpperCase();
        return /^[A-Z]{3}$/.test(currency) ? currency : 'BRL';
    }

    function normalizeProvider(value) {
        const provider = safeString(value, 40).toLowerCase().replace(/[\s-]+/g, '_');

        if (['facebook', 'facebook_pixel', 'meta_pixel', 'meta'].includes(provider)) return 'meta';
        if (['tik_tok', 'tiktok', 'tiktok_pixel'].includes(provider)) return 'tiktok';
        if (['google_analytics', 'google_analytics_4', 'analytics', 'ga4'].includes(provider)) return 'ga4';
        if (['googleads', 'google_ads', 'ads'].includes(provider)) return 'google_ads';
        if (['utmify', 'utmify_pixel', 'utmify_optimization'].includes(provider)) return 'utmify';

        return '';
    }

    function normalizeEvent(value) {
        const raw = safeString(value, 80);
        if (!raw) return '';
        if (Object.prototype.hasOwnProperty.call(EVENT_KEYS, raw)) return raw;

        const key = raw.replace(/[\s-]+/g, '_').toLowerCase();
        return EVENT_ALIASES[key] || EVENT_ALIASES[key.replace(/_/g, '')] || '';
    }

    function normalizePublicIds(value, provider) {
        const candidates = Array.isArray(value)
            ? value
            : safeString(value, 2000).split(/[,;\r\n]+/);

        return Array.from(new Set(candidates
            .map(id => safeString(id, 180))
            .filter(id => validPublicId(id, provider))));
    }

    function validPublicId(id, provider) {
        if (!id) return false;
        if (provider === 'meta') return /^\d{5,32}$/.test(id);
        if (provider === 'tiktok') return /^[A-Za-z0-9_-]{5,80}$/.test(id);
        if (provider === 'ga4') return /^(?:G|GT)-[A-Za-z0-9-]{4,80}$/.test(id);
        if (provider === 'google_ads') {
            return /^AW-\d{4,32}(?:\/[A-Za-z0-9_-]{2,120})?$/.test(id);
        }
        if (provider === 'utmify') return /^[A-Za-z0-9_-]{5,160}$/.test(id);
        return false;
    }

    function normalizeEvents(value) {
        const enabled = new Set();
        const source = typeof value === 'string'
            ? (value.trim().startsWith('{') ? parseObject(value) : parseArray(value))
            : value;

        if (Array.isArray(source)) {
            source.forEach(name => {
                const event = normalizeEvent(name);
                if (event) enabled.add(event);
            });
            return { configured: source.length > 0, enabled };
        }

        if (isObject(source)) {
            Object.keys(source).forEach(name => {
                const event = normalizeEvent(name);
                if (event && toBoolean(source[name], false)) enabled.add(event);
            });
            return { configured: Object.keys(source).length > 0, enabled };
        }

        return { configured: false, enabled };
    }

    function normalizeProductIds(value) {
        return Array.from(new Set(parseArray(value)
            .map(id => safeString(id, 128))
            .filter(Boolean)));
    }

    function flattenRawConfig(raw) {
        if (Array.isArray(raw)) return raw;
        if (!isObject(raw)) return [];
        if (Array.isArray(raw.integrations)) return raw.integrations;

        const rows = [];
        Object.keys(raw).forEach(provider => {
            const values = Array.isArray(raw[provider]) ? raw[provider] : [raw[provider]];
            values.filter(isObject).forEach(value => rows.push(Object.assign({ provider }, value)));
        });
        return rows;
    }

    function normalizeIntegrations(raw) {
        const integrations = [];

        flattenRawConfig(raw).forEach((row, rowIndex) => {
            if (!isObject(row) || !toBoolean(row.browser_enabled, true)) return;

            const provider = normalizeProvider(row.provider);
            if (!provider) return;

            const publicIds = normalizePublicIds(
                row.public_id ?? row.pixel_id ?? row.measurement_id ?? row.conversion_id,
                provider,
            );
            if (!publicIds.length) return;

            const events = normalizeEvents(row.events);
            const settings = parseObject(row.settings);
            const recordId = safeString(row.id ?? `row-${rowIndex}`, 100);

            integrations.push({
                key: `${provider}:${recordId}:${rowIndex}`,
                provider,
                publicIds,
                events,
                scopeMode: safeString(row.scope_mode || 'global', 60).toLowerCase(),
                productIds: normalizeProductIds(row.product_ids),
                settings,
            });
        });

        return integrations;
    }

    function normalizeContent(content) {
        if (!isObject(content)) return null;

        const id = safeString(
            content.id ?? content.content_id ?? content.item_id ?? content.product_id,
            128,
        );
        if (!id) return null;

        const itemPrice = Math.max(0, finiteNumber(
            content.item_price ?? content.price ?? content.unit_price,
            0,
        ));

        return {
            id,
            name: safeString(content.name ?? content.content_name ?? content.item_name, 255),
            quantity: positiveInteger(content.quantity, 1),
            item_price: itemPrice,
        };
    }

    function normalizePayload(input) {
        const source = isObject(input) ? input : {};
        const contents = Array.isArray(source.contents)
            ? source.contents.map(normalizeContent).filter(Boolean)
            : [];

        const ids = Array.isArray(source.content_ids)
            ? source.content_ids.map(id => safeString(id, 128)).filter(Boolean)
            : [];

        contents.forEach(content => {
            if (!ids.includes(content.id)) ids.push(content.id);
        });

        const calculatedValue = contents.reduce(
            (sum, content) => sum + content.item_price * content.quantity,
            0,
        );
        const explicitValue = finiteNumber(source.value, NaN);
        const value = Number.isFinite(explicitValue)
            ? Math.max(0, explicitValue)
            : Math.max(0, calculatedValue);

        const payload = {
            value,
            currency: normalizeCurrency(source.currency),
            content_ids: Array.from(new Set(ids)),
            contents,
        };

        const orderId = safeString(source.order_id ?? source.transaction_id, 128);
        const paymentMethod = safeString(source.payment_method ?? source.payment_type, 60);
        const contentName = safeString(source.content_name, 255);
        const contentCategory = safeString(source.content_category, 255);
        const coupon = safeString(source.coupon, 100);

        if (orderId) payload.order_id = orderId;
        if (paymentMethod) payload.payment_method = paymentMethod;
        if (contentName) payload.content_name = contentName;
        if (contentCategory) payload.content_category = contentCategory;
        if (coupon) payload.coupon = coupon;

        payload.num_items = contents.reduce((sum, content) => sum + content.quantity, 0);
        return payload;
    }

    function itemComparableIds(id) {
        const raw = safeString(id, 128);
        const ids = new Set([raw]);
        const productMatch = raw.match(/^(?:product:|p)?(\d+)(?:[_:-]|$)/i);
        if (productMatch) ids.add(productMatch[1]);
        return ids;
    }

    function payloadProductIds(payload) {
        const ids = new Set();
        (payload.content_ids || []).forEach(id => {
            itemComparableIds(id).forEach(candidate => ids.add(candidate));
        });
        return ids;
    }

    function inScope(integration, payload) {
        const mode = integration.scopeMode.replace(/[\s-]+/g, '_');
        if (['', 'all', 'global', 'site', 'store'].includes(mode)) return true;

        const configured = new Set();
        integration.productIds.forEach(id => {
            itemComparableIds(id).forEach(candidate => configured.add(candidate));
        });

        if (!configured.size) {
            return !['include', 'products', 'product', 'selected_products', 'include_products', 'only_products']
                .includes(mode);
        }

        const current = payloadProductIds(payload);
        const intersects = Array.from(current).some(id => configured.has(id));

        if (['include', 'products', 'product', 'selected_products', 'include_products', 'only_products']
            .includes(mode)) {
            return intersects;
        }

        if (['exclude', 'exclude_products', 'except_products'].includes(mode)) return !intersects;
        if (['checkout'].includes(mode)) {
            return Boolean(payload.order_id || payload.contents.length);
        }

        return true;
    }

    function eventEnabled(integration, event) {
        return !integration.events.configured || integration.events.enabled.has(event);
    }

    function hashString(value) {
        let hash = 2166136261;
        const input = String(value);
        for (let index = 0; index < input.length; index += 1) {
            hash ^= input.charCodeAt(index);
            hash = Math.imul(hash, 16777619);
        }
        return (hash >>> 0).toString(36);
    }

    function newEventId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return `web-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
    }

    function stablePayloadKey(event, payload) {
        const contents = payload.contents
            .map(content => `${content.id}:${content.quantity}:${content.item_price}`)
            .sort()
            .join('|');

        return [
            event,
            payload.order_id || '',
            payload.payment_method || '',
            payload.currency,
            payload.value.toFixed(2),
            contents || payload.content_ids.slice().sort().join('|'),
        ].join('::');
    }

    function defaultDedupeMode(event) {
        if (event === 'PageView' || event === 'ViewContent') return 'document';
        if (event === 'InitiateCheckout' || event === 'AddPaymentInfo') return 'session';
        if (event === 'PixGenerated' || event === 'Purchase') return 'persistent';
        return 'none';
    }

    function storageForMode(mode) {
        try {
            if (mode === 'session') return window.sessionStorage;
            if (mode === 'persistent') return window.localStorage;
        } catch (_) {
            return null;
        }
        return null;
    }

    function markIfNew(mode, key) {
        if (mode === 'none') return true;

        const hashedKey = `${STORAGE_PREFIX}${hashString(key)}`;
        if (state.documentSeen.has(hashedKey)) return false;

        if (mode === 'document') {
            state.documentSeen.add(hashedKey);
            return true;
        }

        const storage = storageForMode(mode);
        if (storage) {
            try {
                if (storage.getItem(hashedKey)) return false;
                storage.setItem(hashedKey, String(Date.now()));
            } catch (_) {
                // Storage may be disabled. The document guard remains useful.
            }
        }

        state.documentSeen.add(hashedKey);
        return true;
    }

    function resolveEventId(event, payload, requestedId) {
        const supplied = safeString(requestedId, 128);
        if (supplied) return supplied;

        if ((event === 'Purchase' || event === 'PixGenerated') && payload.order_id) {
            return `evt-${EVENT_KEYS[event]}-${hashString(payload.order_id)}`;
        }

        return newEventId();
    }

    function addScript(src, key, attributes) {
        const elementId = `replicantfy-tracking-${key}`;
        if (document.getElementById(elementId)) return document.getElementById(elementId);

        const existing = Array.from(document.scripts).find(script => script.src === src);
        if (existing) return existing;

        const script = document.createElement('script');
        script.id = elementId;
        script.async = true;
        script.defer = true;
        script.src = src;

        Object.keys(attributes || {}).forEach(name => {
            if (attributes[name] === false || attributes[name] === null) return;
            script.setAttribute(name, attributes[name] === true ? '' : String(attributes[name]));
        });

        (document.head || document.documentElement).appendChild(script);
        return script;
    }

    function ensureMeta(pixelId) {
        if (!window.fbq) {
            const fbq = function () {
                fbq.callMethod
                    ? fbq.callMethod.apply(fbq, arguments)
                    : fbq.queue.push(arguments);
            };
            if (!window._fbq) window._fbq = fbq;
            fbq.push = fbq;
            fbq.loaded = true;
            fbq.version = '2.0';
            fbq.queue = [];
            window.fbq = fbq;
            addScript('https://connect.facebook.net/en_US/fbevents.js', 'meta');
        }

        if (!state.metaIds.has(pixelId)) {
            window.fbq('init', pixelId);
            state.metaIds.add(pixelId);
        }
    }

    function ensureTikTokBase() {
        window.TiktokAnalyticsObject = window.TiktokAnalyticsObject || 'ttq';
        const objectName = window.TiktokAnalyticsObject;
        const ttq = window[objectName] = window[objectName] || [];

        if (ttq.methods) return ttq;

        ttq.methods = [
            'page', 'track', 'identify', 'instances', 'debug', 'on', 'off',
            'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie',
            'holdConsent', 'revokeConsent', 'grantConsent',
        ];
        ttq.setAndDefer = function (target, method) {
            target[method] = function () {
                target.push([method].concat(Array.prototype.slice.call(arguments, 0)));
            };
        };
        ttq.methods.forEach(method => ttq.setAndDefer(ttq, method));
        ttq.instance = function (pixelId) {
            const instance = ttq._i[pixelId] || [];
            ttq.methods.forEach(method => ttq.setAndDefer(instance, method));
            return instance;
        };
        ttq.load = function (pixelId, options) {
            const base = 'https://analytics.tiktok.com/i18n/pixel/events.js';
            ttq._i = ttq._i || {};
            ttq._i[pixelId] = ttq._i[pixelId] || [];
            ttq._i[pixelId]._u = base;
            ttq._t = ttq._t || {};
            ttq._t[pixelId] = +new Date();
            ttq._o = ttq._o || {};
            ttq._o[pixelId] = options || {};
            addScript(
                `${base}?sdkid=${encodeURIComponent(pixelId)}&lib=${encodeURIComponent(objectName)}`,
                `tiktok-${hashString(pixelId)}`,
            );
        };

        return ttq;
    }

    function ensureTikTok(pixelId) {
        const ttq = ensureTikTokBase();
        if (!state.tiktokIds.has(pixelId)) {
            ttq.load(pixelId);
            state.tiktokIds.add(pixelId);
        }
        return ttq;
    }

    function ensureGtag(seedId) {
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };

        if (!state.gtagStarted) {
            window.gtag('js', new Date());
            state.gtagStarted = true;
        }

        addScript(
            `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(seedId)}`,
            'gtag',
        );
    }

    function ensureGa4(measurementId) {
        ensureGtag(measurementId);
        if (!state.gaIds.has(measurementId)) {
            window.gtag('config', measurementId, { send_page_view: false });
            state.gaIds.add(measurementId);
        }
    }

    function googleAdsParts(publicId, integration) {
        const pieces = publicId.split('/');
        return {
            id: pieces[0],
            label: safeString(integration.settings.conversion_label || pieces[1], 120),
        };
    }

    function ensureGoogleAds(publicId, integration) {
        const parts = googleAdsParts(publicId, integration);
        ensureGtag(parts.id);
        if (!state.googleAdsIds.has(parts.id)) {
            window.gtag('config', parts.id, { send_page_view: false });
            state.googleAdsIds.add(parts.id);
        }
        return parts;
    }

    function ensureUtmify(pixelId, integration) {
        if (toBoolean(
            integration.settings.utm_script_enabled
                ?? integration.settings.install_utm_script,
            false,
        )) {
            const attributes = {};
            if (toBoolean(integration.settings.prevent_xcod_sck, false)) {
                attributes['data-utmify-prevent-xcod-sck'] = true;
            }
            if (toBoolean(integration.settings.prevent_subids, false)) {
                attributes['data-utmify-prevent-subids'] = true;
            }
            addScript(
                'https://cdn.utmify.com.br/scripts/utms/latest.js',
                'utmify-utms',
                attributes,
            );
        }

        if (!toBoolean(integration.settings.optimization_pixel_enabled, false)) {
            return false;
        }

        // UTMify's official optimization script reads one global `pixelId`.
        // Multiple records are accepted by this bridge, but the vendor runtime
        // supports one optimization pixel per page. The first eligible ID wins.
        if (state.utmifyPixelId && state.utmifyPixelId !== pixelId) {
            if (!state.utmifyWarningIds.has(pixelId)) {
                state.utmifyWarningIds.add(pixelId);
                console.warn('ReplicantTracking: UTMify supports one optimization pixel per page; extra ID ignored.');
            }
            return false;
        }

        if (!state.utmifyPixelId) {
            state.utmifyPixelId = pixelId;
            window.pixelId = pixelId;
            addScript('https://cdn.utmify.com.br/scripts/pixel/pixel.js', 'utmify-pixel');
        }

        return true;
    }

    function metaParameters(payload) {
        const params = {
            content_ids: payload.content_ids,
            content_type: 'product',
            contents: payload.contents.map(content => ({
                id: content.id,
                quantity: content.quantity,
                item_price: content.item_price,
            })),
            value: payload.value,
            currency: payload.currency,
            num_items: payload.num_items,
        };

        if (payload.content_name || payload.contents.length === 1) {
            params.content_name = payload.content_name || payload.contents[0].name;
        }
        if (payload.content_category) params.content_category = payload.content_category;
        if (payload.order_id) params.order_id = payload.order_id;
        if (payload.payment_method) params.payment_method = payload.payment_method;

        return params;
    }

    function dispatchMeta(pixelId, event, payload, eventId) {
        ensureMeta(pixelId);
        const params = metaParameters(payload);
        const eventOptions = { eventID: eventId };

        if (event === 'PixGenerated') {
            window.fbq('trackSingleCustom', pixelId, event, params, eventOptions);
            return;
        }

        window.fbq('trackSingle', pixelId, event, params, eventOptions);
    }

    function tiktokParameters(payload) {
        const params = {
            content_type: 'product',
            content_ids: payload.content_ids,
            contents: payload.contents.map(content => ({
                content_id: content.id,
                content_type: 'product',
                content_name: content.name,
                quantity: content.quantity,
                price: content.item_price,
            })),
            value: payload.value,
            currency: payload.currency,
        };

        if (payload.order_id) params.order_id = payload.order_id;
        if (payload.payment_method) params.payment_method = payload.payment_method;
        return params;
    }

    function dispatchTikTok(pixelId, event, payload, eventId) {
        const ttq = ensureTikTok(pixelId);
        const instance = typeof ttq.instance === 'function' ? ttq.instance(pixelId) : ttq;

        if (event === 'PageView') {
            instance.page({ event_id: eventId });
            return;
        }

        instance.track(
            TIKTOK_EVENTS[event] || event,
            tiktokParameters(payload),
            { event_id: eventId },
        );
    }

    function gaItems(payload) {
        return payload.contents.map(content => ({
            item_id: content.id,
            item_name: content.name || content.id,
            price: content.item_price,
            quantity: content.quantity,
        }));
    }

    function gaParameters(payload, eventId) {
        const params = {
            currency: payload.currency,
            value: payload.value,
            items: gaItems(payload),
            event_id: eventId,
        };

        if (payload.order_id) params.transaction_id = payload.order_id;
        if (payload.payment_method) params.payment_type = payload.payment_method;
        if (payload.coupon) params.coupon = payload.coupon;
        return params;
    }

    function dispatchGa4(measurementId, event, payload, eventId) {
        ensureGa4(measurementId);
        const params = gaParameters(payload, eventId);
        params.send_to = measurementId;

        if (event === 'PageView') {
            params.page_title = document.title;
            params.page_location = window.location.href;
        }

        window.gtag('event', GA4_EVENTS[event], params);
    }

    function dispatchGoogleAds(publicId, integration, event, payload, eventId) {
        const parts = ensureGoogleAds(publicId, integration);
        const params = gaParameters(payload, eventId);

        if (event === 'Purchase' && parts.label) {
            params.send_to = `${parts.id}/${parts.label}`;
            window.gtag('event', 'conversion', params);
            return;
        }

        params.send_to = parts.id;
        if (event === 'PageView') {
            params.page_title = document.title;
            params.page_location = window.location.href;
        }
        window.gtag('event', GA4_EVENTS[event], params);
    }

    function dispatch(integration, publicId, event, payload, eventId) {
        if (integration.provider === 'meta') {
            dispatchMeta(publicId, event, payload, eventId);
            return true;
        }
        if (integration.provider === 'tiktok') {
            dispatchTikTok(publicId, event, payload, eventId);
            return true;
        }
        if (integration.provider === 'ga4') {
            dispatchGa4(publicId, event, payload, eventId);
            return true;
        }
        if (integration.provider === 'google_ads') {
            dispatchGoogleAds(publicId, integration, event, payload, eventId);
            return true;
        }
        if (integration.provider === 'utmify') {
            // The official optimization pixel owns its browser event lifecycle.
            // Replicantfy only installs it; order status is synced server-side.
            return ensureUtmify(publicId, integration);
        }
        return false;
    }

    function prepare() {
        if (state.prepared) return;
        state.integrations = normalizeIntegrations(window[CONFIG_GLOBAL]);
        state.prepared = true;

        // O Pixel de Otimização da UTMify é um script sitewide: ele precisa
        // existir desde a entrada no site, mesmo que os únicos eventos
        // selecionados para a API sejam PIX gerado e compra aprovada. Quando
        // há escopo por produto, somente o pixel compatível com o contexto
        // atual pode disputar o único `window.pixelId` aceito pela UTMify.
        const rawContext = isObject(window[CONTEXT_GLOBAL])
            ? window[CONTEXT_GLOBAL]
            : {};
        const contextPayload = normalizePayload(rawContext);
        const frozenOrderContext = rawContext.context_type === 'order'
            && Array.isArray(rawContext.integrations);
        const candidates = frozenOrderContext
            ? normalizeIntegrations(rawContext.integrations)
            : state.integrations;

        candidates
            .filter(integration => integration.provider === 'utmify')
            .filter(integration => frozenOrderContext || inScope(integration, contextPayload))
            .forEach(integration => {
                integration.publicIds.forEach(publicId => {
                    try {
                        ensureUtmify(publicId, integration);
                    } catch (error) {
                        console.warn('ReplicantTracking: UTMify initialization failed.', error);
                    }
                });
            });
    }

    function track(eventName, inputPayload, options) {
        prepare();

        const event = normalizeEvent(eventName);
        if (!event) return false;

        const payload = normalizePayload(inputPayload);
        const opts = isObject(options) ? options : {};
        const integrations = Array.isArray(opts.integrations)
            ? normalizeIntegrations(opts.integrations)
            : state.integrations;
        const frozenScope = toBoolean(opts.frozenScope ?? opts.frozen_scope, false);
        const eventId = resolveEventId(event, payload, opts.eventId ?? opts.event_id);
        const dedupeMode = safeString(opts.dedupe || defaultDedupeMode(event), 20).toLowerCase();
        const dedupeSeed = safeString(opts.dedupeKey ?? opts.dedupe_key, 500)
            || stablePayloadKey(event, payload);
        const targetsSeenThisCall = new Set();
        let dispatched = false;

        integrations.forEach(integration => {
            if (!eventEnabled(integration, event)) return;
            if (!frozenScope && !inScope(integration, payload)) return;

            integration.publicIds.forEach(publicId => {
                const googleAdsDestination = integration.provider === 'google_ads'
                    && event === 'Purchase'
                    ? googleAdsParts(publicId, integration)
                    : null;
                const destinationKey = googleAdsDestination
                    ? `${googleAdsDestination.id}/${googleAdsDestination.label || '__sem_rotulo__'}`
                    : publicId;
                const target = `${integration.provider}:${destinationKey}:${event}`;
                if (targetsSeenThisCall.has(target)) return;
                targetsSeenThisCall.add(target);

                const dedupeKey = `${target}:${dedupeSeed}`;
                if (!markIfNew(dedupeMode, dedupeKey)) return;

                try {
                    if (!dispatch(integration, publicId, event, payload, eventId)) return;
                    dispatched = true;
                    document.dispatchEvent(new CustomEvent('replicantfy:tracking:sent', {
                        detail: {
                            provider: integration.provider,
                            public_id: publicId,
                            event,
                            event_id: eventId,
                        },
                    }));
                } catch (error) {
                    console.warn(`ReplicantTracking: ${integration.provider} dispatch failed.`, error);
                }
            });
        });

        return dispatched;
    }

    function cartItems(response) {
        if (!isObject(response)) return [];
        if (Array.isArray(response.items)) return response.items.filter(isObject);
        if (isObject(response.items)) return Object.values(response.items).filter(isObject);
        return [];
    }

    function cartMutationPayload(response, mutation) {
        const safeMutation = isObject(mutation) ? mutation : {};
        const serverTracking = isObject(response?.tracking)
            ? (isObject(response.tracking.payload) ? response.tracking.payload : response.tracking)
            : null;

        if (serverTracking) {
            return {
                payload: normalizePayload(serverTracking),
                eventId: safeString(response.tracking.event_id, 128),
            };
        }

        const key = safeString(safeMutation.key, 128);
        const productId = safeString(safeMutation.product_id, 128);
        const variantId = safeString(safeMutation.variant_id, 128);

        const item = cartItems(response).find(candidate => {
            if (key && safeString(candidate.key, 128) === key) return true;
            if (productId && safeString(candidate.product_id, 128) === productId) {
                if (!variantId) return !candidate.variant_id;
                return safeString(candidate.variant_id, 128) === variantId;
            }
            return false;
        });
        if (!item) return null;

        const quantity = positiveInteger(safeMutation.quantity ?? safeMutation.delta, 1);
        const price = Math.max(0, finiteNumber(item.price ?? item.unit_price, 0));
        const id = safeString(item.product_id ?? productId, 128);
        if (!id) return null;

        return {
            payload: normalizePayload({
                value: price * quantity,
                currency: response.currency || safeMutation.currency || 'BRL',
                content_ids: [id],
                contents: [{
                    id,
                    name: item.name,
                    quantity,
                    item_price: price,
                }],
            }),
            eventId: safeString(response?.tracking?.event_id, 128),
        };
    }

    function trackCartMutation(response, mutation) {
        const built = cartMutationPayload(response, mutation);
        if (!built) return false;

        return track('AddToCart', built.payload, {
            eventId: built.eventId || undefined,
            dedupe: 'none',
        });
    }

    function parseBrazilianMoney(value) {
        const raw = safeString(value, 60).replace(/[^\d,.-]/g, '');
        if (!raw) return 0;

        const normalized = raw.includes(',')
            ? raw.replace(/\./g, '').replace(',', '.')
            : raw;
        return Math.max(0, finiteNumber(normalized, 0));
    }

    function checkoutPayloadFromDom(form) {
        const root = form || document;
        const context = normalizePayload(window[CONTEXT_GLOBAL]);
        const items = [];

        root.querySelectorAll('[data-cart-item]').forEach(element => {
            const key = safeString(element.dataset.cartKey, 128);
            const idMatch = key.match(/^p(\d+)/i);
            const id = idMatch ? idMatch[1] : '';
            if (!id) return;

            const quantity = positiveInteger(
                element.dataset.cartQuantity
                    || element.querySelector('[data-cart-item-quantity]')?.textContent,
                1,
            );
            const itemPrice = Math.max(
                0,
                finiteNumber(element.dataset.cartUnitPrice, 0),
            );
            const name = safeString(
                element.querySelector('.checkout-summary__item-name')?.textContent,
                255,
            );

            items.push({ id, name, quantity, item_price: itemPrice });
        });

        const contents = items.length ? items : context.contents;
        const totalElement = document.querySelector('[data-total-amount]');
        const value = totalElement
            ? parseBrazilianMoney(totalElement.textContent)
            : context.value;
        const paymentMethod = safeString(
            root.querySelector('[name="payment_method"]:checked')?.value,
            60,
        );

        return normalizePayload({
            value,
            currency: context.currency,
            content_ids: contents.map(content => content.id),
            contents,
            payment_method: paymentMethod,
        });
    }

    function trackCheckoutPayment(form) {
        const payload = checkoutPayloadFromDom(form);
        const dedupeKey = stablePayloadKey('AddPaymentInfo', payload);
        return track('AddPaymentInfo', payload, {
            dedupe: 'session',
            dedupeKey,
        });
    }

    function trackPageContext() {
        if (state.contextTracked) return false;
        state.contextTracked = true;

        const rawContext = isObject(window[CONTEXT_GLOBAL])
            ? window[CONTEXT_GLOBAL]
            : {};
        const payload = normalizePayload(rawContext);
        const contextEvent = normalizeEvent(rawContext.event || 'page_view') || 'PageView';

        track('PageView', payload, {
            dedupe: 'document',
            dedupeKey: window.location.href,
        });

        if (contextEvent !== 'PageView') {
            track(contextEvent, payload, {
                eventId: rawContext.event_id,
                dedupe: defaultDedupeMode(contextEvent),
                dedupeKey: rawContext.dedupe_key || stablePayloadKey(contextEvent, payload),
                integrations: Array.isArray(rawContext.integrations)
                    ? rawContext.integrations
                    : undefined,
                frozenScope: rawContext.context_type === 'order',
            });
        }

        return true;
    }

    function drainQueue() {
        const queue = Array.isArray(window[QUEUE_GLOBAL]) ? window[QUEUE_GLOBAL] : [];
        window[QUEUE_GLOBAL] = [];

        queue.forEach(entry => {
            if (Array.isArray(entry)) {
                track(entry[0], entry[1], entry[2]);
                return;
            }
            if (!isObject(entry)) return;

            if (entry.type === 'cart_mutation') {
                trackCartMutation(entry.response, entry.mutation);
                return;
            }
            if (entry.type === 'checkout_payment') {
                trackCheckoutPayment(document.querySelector('[data-checkout-form]'));
                return;
            }
            track(entry.event, entry.payload, entry.options);
        });
    }

    function boot() {
        if (state.booted) return;
        state.booted = true;
        prepare();
        trackPageContext();
        drainQueue();
    }

    function refresh() {
        state.integrations = normalizeIntegrations(window[CONFIG_GLOBAL]);
        state.prepared = false;
        prepare();
        return state.integrations.length;
    }

    window.ReplicantTracking = Object.freeze({
        boot,
        refresh,
        track,
        trackPageContext,
        trackCartMutation,
        trackCheckoutPayment,
        cartMutationPayload,
        checkoutPayloadFromDom,
        normalizePayload,
        newEventId,
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(window, document);
