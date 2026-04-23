<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Tracer — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:        #0a0c0f;
            --surface:   #111418;
            --border:    #1e2530;
            --border-hi: #2d3848;
            --text:      #c8d4e0;
            --muted:     #4a5a6e;
            --accent:    #00e5ff;
            --accent-dim:#005f6b;
            --green:     #00ff88;
            --red:       #ff4060;
            --amber:     #ffb300;
            --glow:      0 0 20px rgba(0,229,255,.12);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── Grid noise bg ── */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0;
            background-image:
                    linear-gradient(rgba(0,229,255,.03) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(0,229,255,.03) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        /* ── Top bar ── */
        header {
            position: relative; z-index: 10;
            display: flex; align-items: center; gap: 16px;
            padding: 16px 28px;
            border-bottom: 1px solid var(--border);
            background: rgba(10,12,15,.92);
            backdrop-filter: blur(12px);
        }

        .logo-dot {
            width: 10px; height: 10px;
            background: var(--accent);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--accent), 0 0 28px rgba(0,229,255,.4);
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .5; transform: scale(.75); }
        }

        header h1 {
            font-family: 'Syne', sans-serif;
            font-size: 17px; font-weight: 800;
            letter-spacing: .06em;
            color: #fff;
        }
        header h1 span { color: var(--accent); }

        .badge {
            margin-left: auto;
            padding: 4px 10px;
            border: 1px solid var(--border-hi);
            border-radius: 4px;
            font-size: 10px;
            color: var(--muted);
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        /* ── Layout ── */
        main {
            position: relative; z-index: 1;
            display: grid;
            grid-template-columns: 300px 1fr;
            grid-template-rows: auto 1fr;
            gap: 0;
            height: calc(100vh - 57px);
        }

        /* ── Sidebar ── */
        aside {
            grid-row: 1 / 3;
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            overflow-y: auto;
            background: var(--surface);
        }

        .section-label {
            padding: 18px 20px 8px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .16em;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        .field-group {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; flex-direction: column; gap: 6px;
        }

        label {
            font-size: 10px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        input[type="text"] {
            background: var(--bg);
            border: 1px solid var(--border-hi);
            border-radius: 4px;
            color: var(--text);
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            padding: 8px 10px;
            width: 100%;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        input[type="text"]::placeholder { color: var(--muted); }
        input[type="text"]:focus {
            border-color: var(--accent-dim);
            box-shadow: 0 0 0 2px rgba(0,229,255,.07);
        }

        .toggle-row {
            display: flex; align-items: center; gap: 8px;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
        }
        .toggle-row label { cursor: pointer; margin: 0; color: var(--text); text-transform: none; font-size: 12px; letter-spacing: 0; }
        input[type="checkbox"] { accent-color: var(--accent); width: 14px; height: 14px; cursor: pointer; }

        /* Buttons */
        .btn-group {
            display: flex; flex-direction: column; gap: 8px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }

        button {
            cursor: pointer;
            border: none;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: .06em;
            padding: 10px 14px;
            text-transform: uppercase;
            transition: all .18s;
            display: flex; align-items: center; gap: 8px;
            position: relative; overflow: hidden;
        }
        button::after {
            content: '';
            position: absolute; inset: 0;
            background: rgba(255,255,255,.06);
            opacity: 0;
            transition: opacity .18s;
        }
        button:hover::after { opacity: 1; }

        .btn-start {
            background: var(--accent);
            color: #000;
        }
        .btn-start:hover { background: #33ecff; box-shadow: var(--glow); }

        .btn-stop {
            background: transparent;
            border: 1px solid var(--red);
            color: var(--red);
        }
        .btn-stop:hover { background: rgba(255,64,96,.08); }

        .btn-export {
            background: transparent;
            border: 1px solid var(--border-hi);
            color: var(--text);
        }
        .btn-export:hover { border-color: var(--accent-dim); color: var(--accent); }

        /* Session info */
        .session-info {
            padding: 16px 20px;
            display: flex; flex-direction: column; gap: 10px;
        }
        .info-row {
            display: flex; justify-content: space-between; align-items: center;
        }
        .info-key  { font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .info-val  { font-size: 11px; color: var(--text); max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* Status strip */
        #statusStrip {
            margin: 0 20px 16px;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 11px;
            background: rgba(0,229,255,.05);
            border: 1px solid var(--border);
            color: var(--muted);
            min-height: 34px;
            line-height: 1.5;
            display: none;
        }
        #statusStrip.visible { display: block; }
        #statusStrip.ok    { border-color: var(--accent-dim); color: var(--accent); }
        #statusStrip.error { border-color: var(--red); color: var(--red); }

        /* ── Top toolbar for logs ── */
        .log-toolbar {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }
        .log-toolbar-title {
            font-family: 'Syne', sans-serif;
            font-size: 12px; font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text);
        }
        .pill {
            padding: 3px 8px;
            border-radius: 20px;
            background: rgba(0,229,255,.08);
            border: 1px solid var(--accent-dim);
            font-size: 10px;
            color: var(--accent);
            letter-spacing: .05em;
        }
        .ml-auto { margin-left: auto; }

        .btn-toolbar {
            background: transparent;
            border: 1px solid var(--border-hi);
            color: var(--muted);
            padding: 6px 12px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .08em;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-toolbar:hover {
            border-color: var(--accent-dim);
            color: var(--accent);
        }
        .btn-toolbar:disabled {
            opacity: .35;
            cursor: not-allowed;
            pointer-events: none;
        }
        .btn-toolbar svg { flex-shrink: 0; }

        .btn-toolbar.btn-go-live {
            border-color: var(--accent-dim);
            color: var(--accent);
        }
        .btn-toolbar.btn-go-live:hover {
            box-shadow: var(--glow);
        }

        .live-dot {
            width: 8px; height: 8px;
            background: var(--green);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--green);
            animation: pulse 1.4s ease-in-out infinite;
            display: none;
        }
        .live-dot.active { display: block; }

        /* ── Log panel ── */
        #logs {
            overflow-y: auto;
            height: 100%;
            padding: 8px 0;
            scrollbar-width: thin;
            scrollbar-color: var(--border-hi) transparent;
        }

        .log-empty {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 100%;
            color: var(--muted);
            gap: 10px;
        }
        .log-empty svg { opacity: .2; }
        .log-empty p { font-size: 12px; letter-spacing: .05em; }

        .log-entry {
            display: grid;
            grid-template-columns: 180px 90px 1fr;
            gap: 0;
            padding: 0;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background .12s;
        }
        .log-entry:hover { background: rgba(255,255,255,.025); }
        .log-entry:last-child { border-bottom: none; }

        .log-col {
            padding: 9px 14px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .log-col-ts { color: var(--muted); font-size: 11px; border-right: 1px solid var(--border); }
        .log-col-type {
            font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .08em;
            border-right: 1px solid var(--border);
        }
        .log-col-body { color: var(--text); font-size: 11px; }

        .type-request  { color: var(--accent); }
        .type-response { color: var(--green); }
        .type-error    { color: var(--red); }
        .type-event    { color: var(--amber); }
        .type-default  { color: var(--muted); }

        /* Detail panel */
        #detail {
            position: fixed; right: 0; top: 57px;
            width: 480px; height: calc(100vh - 57px);
            background: var(--surface);
            border-left: 1px solid var(--border-hi);
            display: flex; flex-direction: column;
            transform: translateX(100%);
            transition: transform .25s cubic-bezier(.4,0,.2,1);
            z-index: 100;
        }
        #detail.open { transform: translateX(0); }

        .detail-header {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            background: var(--bg);
        }
        .detail-title {
            font-family: 'Syne', sans-serif;
            font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .1em;
            color: var(--text);
        }
        .close-btn {
            margin-left: auto;
            background: none; border: 1px solid var(--border-hi);
            color: var(--muted); font-size: 14px;
            width: 26px; height: 26px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 3px; padding: 0; cursor: pointer;
        }
        .close-btn:hover { border-color: var(--accent-dim); color: var(--accent); }

        #detailBody {
            overflow-y: auto; flex: 1; padding: 16px;
        }
        .detail-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 12px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }
        .detail-tab {
            background: transparent;
            border: 1px solid var(--border-hi);
            color: var(--muted);
            padding: 6px 10px;
            font-size: 10px;
            border-radius: 4px;
            letter-spacing: .08em;
        }
        .detail-tab.active {
            border-color: var(--accent-dim);
            color: var(--accent);
            background: rgba(0,229,255,.08);
        }

        #detailBody pre {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-all;
            color: var(--text);
        }
        .request-meta {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 10px;
            color: var(--muted);
            text-transform: uppercase;
        }

        /* Scrollbar style */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-hi); border-radius: 3px; }

        /* Fade-in for entries */
        @keyframes fadeIn {
            from { opacity:0; transform: translateY(4px); }
            to   { opacity:1; transform: translateY(0); }
        }
        .log-entry { animation: fadeIn .18s ease both; }
    </style>
</head>
<body>

<header>
    <div class="logo-dot"></div>
    <h1>Debug<span>Tracer</span></h1>
    <div class="badge">Laravel</div>
</header>

<main>
    <!-- ── Sidebar ── -->
    <aside>
        <div class="section-label">Authentication</div>

        <div class="field-group">
            <label for="tokenInput">API Token</label>
            <input id="tokenInput" type="text" placeholder="Enter token…">
        </div>

        <div class="section-label">Trace Filter</div>

        <div class="field-group">
            <label for="traceIdInput">Trace ID</label>
            <input id="traceIdInput" type="text" placeholder="From X-Debug-Trace-Id header">
        </div>

        <div class="toggle-row">
            <input type="checkbox" id="showAllTraces" checked>
            <label for="showAllTraces">Show all requests in session</label>
        </div>

        <div class="section-label">Controls</div>

        <div class="btn-group">
            <button class="btn-start" onclick="startSession()">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="6" fill="currentColor" opacity=".2"/><polygon points="4,3 10,6 4,9" fill="currentColor"/></svg>
                Start Tracing
            </button>
            <button class="btn-stop" onclick="stopSession()">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><rect x="2" y="2" width="8" height="8" rx="1" fill="currentColor"/></svg>
                Stop Session
            </button>
            <button class="btn-export" onclick="exportLogs()">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1v7M3 5.5l3 3 3-3M2 10h8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Export Logs
            </button>
        </div>

        <div class="section-label">Session</div>

        <div class="session-info">
            <div class="info-row">
                <span class="info-key">Session ID</span>
                <span class="info-val" id="sessionId">—</span>
            </div>
            <div class="info-row">
                <span class="info-key">Events</span>
                <span class="info-val" id="eventCount">0</span>
            </div>
            <div class="info-row">
                <span class="info-key">Last Poll</span>
                <span class="info-val" id="lastPoll">—</span>
            </div>
        </div>

        <div id="statusStrip"></div>
    </aside>

    <!-- ── Log toolbar ── -->
    <div class="log-toolbar">
        <span class="log-toolbar-title">Request Stream</span>
        <span class="pill" id="countPill">0 events</span>
        <button type="button" class="btn-toolbar ml-auto" id="btnRefresh" onclick="refreshLogs()" disabled title="Fetch now and pause auto-refresh">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M10 6a4 4 0 1 0-1.17 2.83M10 6V3M10 6H7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Refresh
        </button>
        <button type="button" class="btn-toolbar btn-go-live" id="btnGoLive" onclick="startPolling()" style="display:none" title="Resume polling every 2s">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.2"/><polygon points="5,4 9,6 5,8" fill="currentColor"/></svg>
            Go live
        </button>
        <div class="live-dot" id="liveDot"></div>
    </div>

    <!-- ── Log list ── -->
    <div id="logs">
        <div class="log-empty" id="emptyState">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                <rect x="6" y="10" width="36" height="28" rx="3" stroke="#4a5a6e" stroke-width="2"/>
                <path d="M14 20h20M14 26h14M14 32h8" stroke="#4a5a6e" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <p>No events yet — start a session to begin tracing</p>
        </div>
    </div>
</main>

<!-- ── Detail panel ── -->
<div id="detail">
    <div class="detail-header">
        <span class="detail-title">Event Detail</span>
        <button class="close-btn" onclick="closeDetail()">✕</button>
    </div>
    <div id="detailBody"></div>
</div>

<script>
    let sessionId = null;
    let pollHandle = null;
    let logsFetching = false;
    let requestViews = [];
    let activeDetail = null;

    function token() {
        return document.getElementById('tokenInput').value.trim();
    }

    function setStatus(text, type = 'ok') {
        const el = document.getElementById('statusStrip');
        el.textContent = text;
        el.className = 'visible ' + type;
    }

    function fmtTime(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                fractionalSecondDigits: 2
            });
        } catch {
            return iso;
        }
    }

    function parseTime(iso) {
        const ms = Date.parse(iso || '');
        return Number.isNaN(ms) ? 0 : ms;
    }

    function shortUrl(url) {
        if (!url) return '/';
        try {
            return new URL(url, window.location.origin).pathname;
        } catch {
            return String(url);
        }
    }

    async function startSession() {
        if (!token()) {
            setStatus('Please enter an API token.', 'error');
            return;
        }

        const res = await fetch('/debug/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token() })
        });
        if (!res.ok) {
            setStatus('Unable to start trace session.', 'error');
            return;
        }

        const data = await res.json();
        sessionId = data.session_id;
        requestViews = [];
        activeDetail = null;

        document.getElementById('sessionId').textContent = sessionId;
        document.getElementById('eventCount').textContent = '0';
        document.getElementById('btnRefresh').disabled = false;
        setStatus('Tracing active. Click a request row to inspect details.', 'ok');

        startPolling();
    }

    async function stopSession() {
        if (!sessionId || !token()) {
            setStatus('Start a session first.', 'error');
            return;
        }

        const res = await fetch('/debug/stop', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Debug-Token': token() },
            body: JSON.stringify({ session_id: sessionId })
        });
        if (!res.ok) {
            setStatus('Unable to stop session.', 'error');
            return;
        }

        stopPolling();
        document.getElementById('btnRefresh').disabled = true;
        document.getElementById('btnGoLive').style.display = 'none';
        setStatus('Session stopped.', 'ok');
    }

    function exportLogs() {
        if (!sessionId || !token()) {
            setStatus('Start a session first.', 'error');
            return;
        }

        window.location = `/debug/export/${sessionId}?token=${encodeURIComponent(token())}`;
    }

    async function fetchLogs() {
        if (!sessionId || !token() || logsFetching) return false;

        logsFetching = true;
        const btn = document.getElementById('btnRefresh');
        if (btn && !btn.disabled) btn.setAttribute('aria-busy', 'true');

        try {
            const params = new URLSearchParams();
            if (document.getElementById('showAllTraces').checked) {
                params.set('show_all', '1');
            } else {
                const tid = document.getElementById('traceIdInput').value.trim();
                if (tid) params.set('trace_id', tid);
            }

            const qs = params.toString();
            const url = `/debug-dashboard/logs/${sessionId}${qs ? '?' + qs : ''}`;
            const res = await fetch(url, { headers: { 'X-Debug-Token': token() } });

            if (!res.ok) {
                setStatus('Unable to load logs.', 'error');
                return false;
            }

            const events = await res.json();
            renderLogs(events);
            document.getElementById('lastPoll').textContent = new Date().toLocaleTimeString('en-US', { hour12: false });
            return true;
        } finally {
            logsFetching = false;
            if (btn) btn.removeAttribute('aria-busy');
        }
    }

    async function refreshLogs() {
        if (!sessionId || !token()) {
            setStatus('Start a session and enter your token to refresh.', 'error');
            return;
        }

        stopPolling();
        await fetchLogs();
        updateGoLiveButton();
    }

    function stopPolling() {
        if (pollHandle) {
            clearInterval(pollHandle);
            pollHandle = null;
        }
        document.getElementById('liveDot').classList.remove('active');
    }

    function updateGoLiveButton() {
        const btn = document.getElementById('btnGoLive');
        if (!btn) return;
        const show = Boolean(sessionId && token() && !pollHandle);
        btn.style.display = show ? 'inline-flex' : 'none';
    }

    function startPolling() {
        if (!sessionId || !token()) return;

        if (pollHandle) clearInterval(pollHandle);
        fetchLogs();
        pollHandle = setInterval(fetchLogs, 2000);
        document.getElementById('liveDot').classList.add('active');
        updateGoLiveButton();
    }

    function buildRequestViews(events) {
        const groups = new Map();

        events.forEach((event, index) => {
            const traceId = event.trace_id || `no-trace-${index}`;
            if (!groups.has(traceId)) {
                groups.set(traceId, {
                    trace_id: event.trace_id || null,
                    request: null,
                    response: null,
                    db: [],
                    errors: [],
                    all_events: []
                });
            }

            const group = groups.get(traceId);
            group.all_events.push(event);

            const type = String(event.type || event.event || '').toLowerCase();
            if (type === 'request') {
                group.request = event;
            } else if (type === 'response') {
                group.response = event;
            } else if (type === 'db') {
                group.db.push(event);
            } else if (type.includes('error') || type.includes('exception')) {
                group.errors.push(event);
            }
        });

        return Array.from(groups.values())
            .filter(group => group.request || group.response || group.db.length > 0 || group.errors.length > 0)
            .sort((a, b) => {
                const aTs = parseTime(a.request?.timestamp || a.response?.timestamp || a.all_events[0]?.timestamp);
                const bTs = parseTime(b.request?.timestamp || b.response?.timestamp || b.all_events[0]?.timestamp);
                return bTs - aTs;
            });
    }

    function renderLogs(events) {
        const container = document.getElementById('logs');
        const empty = document.getElementById('emptyState');
        const pill = document.getElementById('countPill');
        const countEl = document.getElementById('eventCount');

        requestViews = buildRequestViews(events);
        pill.textContent = `${requestViews.length} request${requestViews.length !== 1 ? 's' : ''}`;
        countEl.textContent = String(requestViews.length);

        Array.from(container.querySelectorAll('.log-entry')).forEach(el => el.remove());

        if (!requestViews.length) {
            empty.style.display = 'flex';
            return;
        }

        empty.style.display = 'none';

        requestViews.forEach((view, i) => {
            const req = view.request || {};
            const res = view.response || {};
            const ts = req.timestamp || res.timestamp;
            const method = (req.method || 'TRACE').toUpperCase();
            const status = res.status ? `HTTP ${res.status}` : (view.errors.length ? 'FAILED' : 'PENDING');
            const summary = `${method} ${shortUrl(req.url || '/')}  •  ${status}  •  DB ${view.db.length}`;

            const row = document.createElement('div');
            row.className = 'log-entry';
            row.style.animationDelay = `${Math.min(i * 12, 200)}ms`;
            row.innerHTML = `
                <div class="log-col log-col-ts">${fmtTime(ts)}</div>
                <div class="log-col log-col-type type-request">${method}</div>
                <div class="log-col log-col-body">${escapeHtml(summary)}</div>
            `;
            row.addEventListener('click', () => openRequestDetail(view));
            container.appendChild(row);
        });
    }

    function requestDetails(group) {
        const req = group.request || {};
        return {
            trace_id: group.trace_id,
            timestamp: req.timestamp || null,
            method: req.method || null,
            url: req.url || null,
            headers: req.headers || {},
            query_params: req.query_params || {},
            body_params: req.body_params || {}
        };
    }

    function dbDetails(group) {
        return group.db.map(item => ({
            timestamp: item.timestamp || null,
            query: item.query || null,
            duration_ms: item.duration_ms ?? null,
            bindings: item.bindings ?? null
        }));
    }

    function responseDetails(group) {
        const res = group.response || {};
        return {
            trace_id: group.trace_id,
            timestamp: res.timestamp || null,
            status: res.status ?? null,
            duration_ms: res.duration_ms ?? null,
            headers: res.headers || {},
            body: res.body ?? null,
            errors: group.errors.map(item => ({
                timestamp: item.timestamp || null,
                type: item.type || item.event || null,
                message: item.message || null,
                file: item.file || null,
                line: item.line || null
            }))
        };
    }

    function openRequestDetail(group) {
        activeDetail = group;
        const method = (group.request?.method || 'TRACE').toUpperCase();
        const title = `${method} ${shortUrl(group.request?.url || '/')}`;
        document.querySelector('.detail-title').textContent = title.slice(0, 52);
        renderDetailTab('request');
        document.getElementById('detail').classList.add('open');
    }

    function renderDetailTab(tab) {
        if (!activeDetail) return;

        const tabsHtml = `
            <div class="detail-tabs">
                <button type="button" class="detail-tab ${tab === 'request' ? 'active' : ''}" data-tab="request">Request Details</button>
                <button type="button" class="detail-tab ${tab === 'db' ? 'active' : ''}" data-tab="db">DB Details</button>
                <button type="button" class="detail-tab ${tab === 'response' ? 'active' : ''}" data-tab="response">Response Details</button>
            </div>
        `;

        let payload;
        if (tab === 'db') {
            payload = dbDetails(activeDetail);
        } else if (tab === 'response') {
            payload = responseDetails(activeDetail);
        } else {
            payload = requestDetails(activeDetail);
        }

        const meta = activeDetail.trace_id ? `<div class="request-meta"><span>Trace: ${escapeHtml(activeDetail.trace_id)}</span></div>` : '';
        document.getElementById('detailBody').innerHTML = `
            ${tabsHtml}
            ${meta}
            <pre>${escapeHtml(JSON.stringify(payload, null, 2))}</pre>
        `;

        document.querySelectorAll('.detail-tab').forEach(btn => {
            btn.addEventListener('click', () => renderDetailTab(btn.dataset.tab));
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function closeDetail() {
        document.getElementById('detail').classList.remove('open');
    }

    document.getElementById('showAllTraces').addEventListener('change', () => { fetchLogs(); });
    document.getElementById('traceIdInput').addEventListener('change', () => { fetchLogs(); });
    document.getElementById('tokenInput').addEventListener('input', () => { updateGoLiveButton(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetail(); });
</script>
</body>
</html>