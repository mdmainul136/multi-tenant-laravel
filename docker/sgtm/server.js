/**
 * Custom sGTM Tracking Proxy (Node.js)
 * 
 * Replaces Google's GCR sGTM image with a lightweight, 
 * fully-controlled tracking proxy.
 *
 * Flow:
 *   Client → NGINX → This Container → Laravel Power-Ups API → Destinations
 *
 * Environment Variables:
 *   CONTAINER_ID     - GTM container identifier (e.g. GTM-XXXXXXX)
 *   API_SECRET       - Shared secret for Laravel Power-Ups API
 *   POWERUPS_URL     - Laravel Power-Ups endpoint (e.g. http://app:8000/api/tracking/proxy)
 *   PORT             - Listen port (default: 8080)
 */

const http = require('http');
const url = require('url');
const fs = require('fs');
const path = require('path');
const { v4: uuidv4 } = require('uuid');
const vm = require('vm'); // For sandboxed custom scripting

// ── Config ──────────────────────────────────────────────────
const PORT = process.env.PORT || 8080;
const CONTAINER_ID = process.env.CONTAINER_ID || 'unknown';
const API_SECRET = process.env.API_SECRET || '';
const POWERUPS_URL = process.env.POWERUPS_URL || 'http://localhost:8000/api/tracking/proxy';
const COOKIE_NAME = process.env.COOKIE_NAME || '_stape_id';
const COOKIE_MAX_AGE = parseInt(process.env.COOKIE_MAX_AGE || '31536000', 10); // 1 year
const DEBUG_AUTH = process.env.DEBUG_AUTH || '';  // Optional auth token for debug UI
const CUSTOM_SCRIPT = process.env.CUSTOM_SCRIPT || ''; // Custom transformation JS snippet
const LOADER_PATH = process.env.LOADER_PATH || '';     // Obfuscated GTM loader path (e.g. '/cdn/a7x.js')
const CLICK_ID_RESTORE = process.env.CLICK_ID_RESTORE === 'true'; // Enable Click ID Restorer

// ── Metrics ─────────────────────────────────────────────────
const metrics = {
    requests: 0,
    errors: 0,
    forwarded: 0,
    blocked_by_adblocker: 0,
    click_ids_restored: 0,
    startedAt: new Date().toISOString(),
};

// ── Debug: SSE Clients ──────────────────────────────────────
const debugClients = new Set();

// ── Debug: Event Ring Buffer (last 200 events) ──────────────
const eventBuffer = [];
const MAX_BUFFER = 200;

function pushToDebug(eventData) {
    eventBuffer.unshift(eventData);
    if (eventBuffer.length > MAX_BUFFER) eventBuffer.pop();

    // Broadcast to all connected debug SSE clients
    const data = JSON.stringify(eventData);
    for (const client of debugClients) {
        try {
            client.write(`data: ${data}\n\n`);
        } catch {
            debugClients.delete(client);
        }
    }
}

// ── Server ──────────────────────────────────────────────────
const server = http.createServer(async (req, res) => {
    const parsed = url.parse(req.url, true);

    // CORS preflight
    if (req.method === 'OPTIONS') {
        setCorsHeaders(res);
        res.writeHead(204);
        return res.end();
    }

    setCorsHeaders(res);

    // ── Routes ──────────────────────────────────────────────

    // Click ID Restorer: restore stripped gclid/fbclid/msclkid/ttclid
    if (CLICK_ID_RESTORE) {
        restoreClickIds(req, parsed);
    }

    switch (parsed.pathname) {
        case '/healthz':
            return respondJson(res, 200, { status: 'ok', container: CONTAINER_ID });

        case '/metrics':
            return respondJson(res, 200, metrics);

        case '/collect':
        case '/g/collect':
        case '/e':    // Obfuscated collect alias
        case '/x/e':  // Obfuscated collect alias
            return handleCollect(req, res, parsed);

        case '/gtm.js':
        case '/gtag/js':
            return handleGtmJs(req, res, parsed);

        // ── Debug / Preview Routes ────────────────────────────
        case '/debug':
        case '/gtm/debug':
            return handleDebugUI(req, res, parsed);

        case '/debug/stream':
        case '/gtm/debug/stream':
            return handleDebugStream(req, res, parsed);

        case '/debug/recent':
        case '/gtm/debug/recent':
            return respondJson(res, 200, { events: eventBuffer.slice(0, 50) });

        default:
            // Custom Loader Path Match (obfuscated GTM/GA4 loader)
            if (LOADER_PATH && parsed.pathname === LOADER_PATH) {
                return handleCustomLoader(req, res, parsed);
            }

            // Catch-all: forward everything to Power-Ups API
            if (req.method === 'POST') {
                return handleCollect(req, res, parsed);
            }
            return respondJson(res, 404, { error: 'Not found' });
    }
});

// ── Debug UI Handler ────────────────────────────────────────
function handleDebugUI(req, res, parsed) {
    // Optional auth check
    if (DEBUG_AUTH && parsed.query.gtm_auth !== DEBUG_AUTH) {
        return respondJson(res, 401, { error: 'Invalid debug auth token' });
    }

    // Serve the debug HTML UI
    const htmlPath = path.join(__dirname, 'debug-ui.html');

    try {
        const html = fs.readFileSync(htmlPath, 'utf-8');
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(html);
    } catch (err) {
        res.writeHead(500);
        res.end('Debug UI file not found. Make sure debug-ui.html is in the container.');
    }
}

// ── Debug SSE Stream ────────────────────────────────────────
function handleDebugStream(req, res, parsed) {
    // Optional auth check
    if (DEBUG_AUTH && parsed.query.auth !== DEBUG_AUTH) {
        return respondJson(res, 401, { error: 'Invalid auth' });
    }

    // SSE headers
    res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive',
        'X-Accel-Buffering': 'no',  // Disable NGINX buffering for SSE
    });

    // Send init message with container info
    const initData = JSON.stringify({
        type: 'init',
        container_id: CONTAINER_ID,
        started_at: metrics.startedAt,
        total_events: metrics.requests,
    });
    res.write(`data: ${initData}\n\n`);

    // Send buffered recent events (so UI isn't empty on connect)
    for (const event of eventBuffer.slice(0, 20).reverse()) {
        res.write(`data: ${JSON.stringify(event)}\n\n`);
    }

    // Register this client for live updates
    debugClients.add(res);
    console.log(`[Debug] Client connected. Active: ${debugClients.size}`);

    // Heartbeat every 15s to keep connection alive
    const heartbeat = setInterval(() => {
        try { res.write(': heartbeat\n\n'); } catch { clearInterval(heartbeat); }
    }, 15000);

    // Cleanup on disconnect
    req.on('close', () => {
        debugClients.delete(res);
        clearInterval(heartbeat);
        console.log(`[Debug] Client disconnected. Active: ${debugClients.size}`);
    });
}

// ── Custom Script Sandbox ────────────────────────────────────
function transformEvent(payload) {
    if (!CUSTOM_SCRIPT) return payload;

    try {
        const sandbox = {
            event: JSON.parse(JSON.stringify(payload)), // Deep clone for safety
            console: { log: (...args) => console.log(`[Script:${CONTAINER_ID}]`, ...args) },
            JSON: JSON,
            Math: Math,
            Date: Date
        };

        const script = new vm.Script(`
            (function() {
                try {
                    ${CUSTOM_SCRIPT}
                    return event;
                } catch (e) {
                    return { ...event, _script_error: e.message };
                }
            })()
        `);

        // Execute with a 50ms timeout to prevent infinite loops
        const result = script.runInNewContext(sandbox, { timeout: 50 });
        return result || payload;
    } catch (err) {
        console.error(`[sGTM] Custom script error:`, err.message);
        return { ...payload, _script_error: err.message };
    }
}

// ── Event Collection Handler ────────────────────────────────
async function handleCollect(req, res, parsed) {
    metrics.requests++;

    try {
        // Parse body
        const body = await readBody(req);
        let eventData = {};

        try {
            eventData = body ? JSON.parse(body) : {};
        } catch {
            // Could be query-string style (GA4 collect format)
            eventData = parsed.query || {};
        }

        // Extract/generate client ID (first-party cookie)
        const cookies = parseCookies(req);
        let clientId = cookies[COOKIE_NAME] || eventData.client_id || uuidv4();

        // Build enriched payload
        const payload = {
            event_name: eventData.event_name || eventData.en || 'page_view',
            event_id: eventData.event_id || uuidv4(),
            client_id: clientId,
            container_id: CONTAINER_ID,
            timestamp: new Date().toISOString(),
            source_ip: req.headers['x-forwarded-for'] || req.socket.remoteAddress,
            user_agent: req.headers['user-agent'] || '',
            referrer: req.headers['referer'] || '',
            page_url: eventData.page_url || eventData.dl || '',
            consent: eventData.consent !== undefined ? eventData.consent : true,
            user_data: eventData.user_data || {},
            custom_data: eventData.custom_data || eventData.ep || {},
            _ext_cookie: clientId,
            // Pass raw payload for Power-Ups to process
            _raw: eventData,
        };

        // Apply Custom Script Transformation
        const transformedPayload = transformEvent(payload);

        // Forward to Laravel Power-Ups API
        const powerUpResult = await forwardToPowerUps(transformedPayload);

        // Enrich event with Power-Ups result for Debug UI
        const debugEvent = {
            ...transformedPayload,
            _status: powerUpResult?.status === 'dropped' ? 'dropped' :
                powerUpResult?.success === false ? 'error' : 'success',
            _destinations: powerUpResult?._destinations || [],
            _power_ups: powerUpResult?._power_ups || [],
            _processed_at: new Date().toISOString(),
        };

        // Push to debug stream
        pushToDebug(debugEvent);

        // Set first-party cookie
        res.setHeader('Set-Cookie',
            `${COOKIE_NAME}=${clientId}; ` +
            `Max-Age=${COOKIE_MAX_AGE}; ` +
            `Path=/; ` +
            `SameSite=Lax; ` +
            `Secure; HttpOnly`
        );

        metrics.forwarded++;

        // Return 1x1 transparent GIF for img-based tracking, JSON for POST
        if (req.method === 'GET') {
            return respondPixel(res);
        }

        return respondJson(res, 200, {
            status: 'ok',
            event_id: payload.event_id,
            processed: powerUpResult,
        });

    } catch (err) {
        metrics.errors++;
        console.error(`[sGTM] Error processing event:`, err.message);

        // Push error to debug stream too
        pushToDebug({
            event_name: 'error',
            event_id: uuidv4(),
            timestamp: new Date().toISOString(),
            _status: 'error',
            _error: err.message,
            _destinations: [],
            _power_ups: [],
        });

        return respondJson(res, 500, { error: 'Processing failed' });
    }
}

// ── GTM.js Proxy (Optional: serve GTM snippet) ─────────────
async function handleGtmJs(req, res, parsed) {
    const gtmId = parsed.query.id || CONTAINER_ID;
    const isGtag = parsed.pathname.includes('gtag');

    // Proxy from Google's CDN
    try {
        const sourceUrl = isGtag
            ? `https://www.googletagmanager.com/gtag/js?id=${gtmId}`
            : `https://www.googletagmanager.com/gtm.js?id=${gtmId}`;
        const gtmRes = await fetch(sourceUrl);
        let script = await gtmRes.text();

        // Rewrite Google's transport endpoint to point to our proxy
        // This makes analytics calls go through this server instead of directly to Google
        script = script.replace(
            /https:\/\/www\.google-analytics\.com\/g\/collect/g,
            '/g/collect'
        );
        script = script.replace(
            /https:\/\/www\.google-analytics\.com\/collect/g,
            '/collect'
        );

        res.writeHead(200, {
            'Content-Type': 'application/javascript',
            'Cache-Control': 'public, max-age=3600',
        });
        res.end(script);
    } catch {
        res.writeHead(502);
        res.end('// GTM proxy error');
    }
}

// ── Custom Loader (Obfuscated GTM/GA4 Loader) ──────────────
// Serves GTM scripts from a random path to bypass ad blockers.
async function handleCustomLoader(req, res, parsed) {
    metrics.blocked_by_adblocker++; // Count as ad-blocker-bypass usage
    const gtmId = parsed.query.id || CONTAINER_ID;
    const isGtag = parsed.query.type === 'gtag';

    try {
        const sourceUrl = isGtag
            ? `https://www.googletagmanager.com/gtag/js?id=${gtmId}`
            : `https://www.googletagmanager.com/gtm.js?id=${gtmId}`;
        const gtmRes = await fetch(sourceUrl);
        let script = await gtmRes.text();

        // Rewrite transport endpoints to proxy through this server
        script = script.replace(
            /https:\/\/www\.google-analytics\.com\/g\/collect/g,
            '/g/collect'
        );
        script = script.replace(
            /https:\/\/www\.google-analytics\.com\/collect/g,
            '/collect'
        );
        // Rewrite GTM's own reference to load through our domain
        script = script.replace(
            /https:\/\/www\.googletagmanager\.com/g,
            ''
        );

        res.writeHead(200, {
            'Content-Type': 'application/javascript',
            'Cache-Control': 'public, max-age=1800', // 30 min cache
            'X-Content-Type-Options': 'nosniff',
        });
        res.end(script);
    } catch {
        res.writeHead(502);
        res.end('// Loader error');
    }
}

// ── Click ID Restorer ───────────────────────────────────────
// Restores stripped ad click IDs (gclid, fbclid, msclkid, ttclid)
// from custom query params that survive Safari/Brave stripping.
function restoreClickIds(req, parsed) {
    const query = parsed.query || {};
    const RESTORE_MAP = {
        stclid: 'gclid',   // Google Ads click ID
        sfclid: 'fbclid',  // Facebook click ID  
        smclid: 'msclkid', // Microsoft Ads click ID
        stklid: 'ttclid',  // TikTok click ID
        ssclid: 'sclid',   // Snapchat click ID
    };

    let restored = false;
    for (const [custom, native] of Object.entries(RESTORE_MAP)) {
        if (query[custom] && !query[native]) {
            query[native] = query[custom];
            restored = true;
        }
    }

    if (restored) {
        metrics.click_ids_restored++;
        // Attach restored IDs to the request for downstream processing
        req._restoredClickIds = query;
    }
}

// ── Forward to Laravel Power-Ups API ────────────────────────
async function forwardToPowerUps(payload) {
    const targetUrl = `${POWERUPS_URL}/${CONTAINER_ID}`;

    try {
        const response = await fetch(targetUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Stape-Secret': API_SECRET,
                'X-Forwarded-For': payload.source_ip,
                'User-Agent': payload.user_agent,
            },
            body: JSON.stringify(payload),
            signal: AbortSignal.timeout(5000), // 5s timeout
        });

        return await response.json();
    } catch (err) {
        console.error(`[sGTM] Power-Ups API error:`, err.message);
        return { status: 'power_ups_unavailable' };
    }
}

// ── Helpers ─────────────────────────────────────────────────
function readBody(req) {
    return new Promise((resolve, reject) => {
        let data = '';
        req.on('data', chunk => { data += chunk; });
        req.on('end', () => resolve(data));
        req.on('error', reject);
    });
}

function parseCookies(req) {
    const cookies = {};
    const header = req.headers.cookie || '';
    header.split(';').forEach(pair => {
        const [key, ...val] = pair.trim().split('=');
        if (key) cookies[key] = val.join('=');
    });
    return cookies;
}

function setCorsHeaders(res) {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, X-Stape-Secret');
    res.setHeader('Access-Control-Max-Age', '86400');
}

function respondJson(res, status, data) {
    res.writeHead(status, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(data));
}

function respondPixel(res) {
    // 1x1 transparent GIF
    const pixel = Buffer.from(
        'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7', 'base64'
    );
    res.writeHead(200, {
        'Content-Type': 'image/gif',
        'Cache-Control': 'no-store',
        'Content-Length': pixel.length,
    });
    res.end(pixel);
}

// ── Start ───────────────────────────────────────────────────
server.listen(PORT, () => {
    console.log(`[sGTM] 🚀 Tracking proxy running on port ${PORT}`);
    console.log(`[sGTM]    Container: ${CONTAINER_ID}`);
    console.log(`[sGTM]    Power-Ups: ${POWERUPS_URL}`);
    console.log(`[sGTM]    Debug UI:  http://localhost:${PORT}/debug?id=${CONTAINER_ID}`);
});
