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

<button onclick="startSession()">Start</button>
<button onclick="stopSession()">Stop</button>
<button onclick="exportLogs()">Export</button>

<p>Session: <span id="sessionId">-</span></p>

<div id="logs"></div>

<script>
    let sessionId = null;

    async function startSession() {
        const res = await fetch('/debug/start', { method: 'POST' });
        const data = await res.json();

        sessionId = data.session_id;
        document.getElementById('sessionId').innerText = sessionId;

        pollLogs();
    }

    async function stopSession() {
        await fetch('/debug/stop', { method: 'POST' });
    }

    function exportLogs() {
        if (!sessionId) return;
        window.location = `/debug/export/${sessionId}`;
    }

    async function pollLogs() {
        if (!sessionId) return;

        setInterval(async () => {
            const res = await fetch(`/debug-dashboard/logs/${sessionId}`);
            const data = await res.json();

            document.getElementById('logs').innerHTML =
                data.map(e => JSON.stringify(e)).join('<br>');
        }, 2000);
    }
</script>

</body>
</html>