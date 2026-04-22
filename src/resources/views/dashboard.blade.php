<!DOCTYPE html>
<html>
<head>
    <title>Debug Dashboard</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        button { margin-right: 10px; }
        #logs { background:#111; color:#0f0; padding:10px; height:400px; overflow:auto; }
    </style>
</head>
<body>

<h2>Debug Session Dashboard</h2>

<p>
    <label for="tokenInput">API Token:</label>
    <input id="tokenInput" type="text" placeholder="Enter token">
</p>

<button onclick="startSession()">Start token tracing</button>
<button onclick="stopSession()">Stop token tracing</button>
<button onclick="exportLogs()">Export logs</button>

<p>Session: <span id="sessionId">-</span></p>
<p id="status"></p>

<div id="logs"></div>

<script>
    let sessionId = null;
    let pollHandle = null;

    function tokenValue() {
        return document.getElementById('tokenInput').value.trim();
    }

    function setStatus(text, isError = false) {
        const el = document.getElementById('status');
        el.innerText = text;
        el.style.color = isError ? '#b00' : '#222';
    }

    async function startSession() {
        const token = tokenValue();

        if (!token) {
            setStatus('Please enter a token.', true);
            return;
        }

        const res = await fetch('/debug/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token })
        });

        if (!res.ok) {
            setStatus('Unable to start trace session.', true);
            return;
        }

        const data = await res.json();

        sessionId = data.session_id;
        document.getElementById('sessionId').innerText = sessionId;
        setStatus(`Tracing active for token ${token}`);

        pollLogs();
    }

    async function stopSession() {
        const token = tokenValue();

        if (!sessionId || !token) {
            setStatus('Start a session with a token first.', true);
            return;
        }

        const res = await fetch('/debug/stop', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Debug-Token': token
            },
            body: JSON.stringify({ session_id: sessionId })
        });

        if (!res.ok) {
            setStatus('Unable to stop trace session.', true);
            return;
        }

        setStatus(`Tracing stopped for token ${token}`);
    }

    function exportLogs() {
        const token = tokenValue();

        if (!sessionId || !token) {
            setStatus('Start a session with a token first.', true);
            return;
        }

        window.location = `/debug/export/${sessionId}?token=${encodeURIComponent(token)}`;
    }

    async function pollLogs() {
        const token = tokenValue();

        if (!sessionId || !token) return;

        if (pollHandle) {
            clearInterval(pollHandle);
        }

        pollHandle = setInterval(async () => {
            const res = await fetch(`/debug-dashboard/logs/${sessionId}`, {
                headers: {
                    'X-Debug-Token': token
                }
            });

            if (!res.ok) {
                setStatus('Unable to load logs for this token/session.', true);
                return;
            }

            const data = await res.json();

            document.getElementById('logs').innerHTML =
                data.map(e => JSON.stringify(e)).join('<br>');
        }, 2000);
    }
</script>

</body>
</html>
