<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP App Server — Test Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f;
            --surface: #111118;
            --border: #1e1e2e;
            --accent: #00ff88;
            --accent2: #00aaff;
            --danger: #ff4466;
            --warn: #ffaa00;
            --text: #e0e0f0;
            --muted: #555570;
            --mono: 'JetBrains Mono', monospace;
            --sans: 'Syne', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--mono);
            min-height: 100vh;
            padding: 2rem;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(0, 255, 136, .03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 136, .03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        .wrapper {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
        }

        header {
            display: flex;
            align-items: baseline;
            gap: 1rem;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        header h1 {
            font-family: var(--sans);
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--accent);
        }

        .badge {
            font-size: .65rem;
            font-weight: 600;
            padding: .2rem .5rem;
            border-radius: 3px;
            background: var(--border);
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .1em;
        }

        .badge.online {
            background: rgba(0, 255, 136, .1);
            color: var(--accent);
        }

        .meta {
            margin-left: auto;
            font-size: .75rem;
            color: var(--muted);
            text-align: right;
            line-height: 1.6;
        }

        .meta span {
            color: var(--accent2);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .75rem;
            margin-bottom: 2rem;
        }

        .stat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: .9rem 1rem;
        }

        .stat-label {
            font-size: .6rem;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--muted);
            margin-bottom: .4rem;
        }

        .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent);
        }

        .stat-value.blue {
            color: var(--accent2);
        }

        .stat-value.warn {
            color: var(--warn);
        }

        .stat-value.danger {
            color: var(--danger);
        }

        .stat-value.ok {
            color: var(--accent);
        }

        .tabs {
            display: flex;
            gap: .25rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .tab {
            padding: .5rem 1rem;
            font-size: .75rem;
            font-family: var(--mono);
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            border: none;
            background: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: color .15s, border-color .15s;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .tab:hover {
            color: var(--text);
        }

        .tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .field {
            margin-bottom: .75rem;
        }

        label {
            display: block;
            font-size: .7rem;
            color: var(--muted);
            margin-bottom: .3rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        input[type=text],
        input[type=file],
        textarea,
        select {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            color: var(--text);
            font-family: var(--mono);
            font-size: .8rem;
            padding: .5rem .75rem;
            outline: none;
            transition: border-color .15s;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: var(--accent);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .row {
            display: flex;
            gap: .5rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: .75rem;
        }

        .toggle {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .72rem;
            color: var(--muted);
            cursor: pointer;
            user-select: none;
        }

        .toggle input {
            width: auto;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .5rem 1.1rem;
            font-family: var(--mono);
            font-size: .78rem;
            font-weight: 600;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: opacity .15s, transform .1s;
        }

        .btn:active {
            transform: scale(.97);
        }

        .btn-green {
            background: var(--accent);
            color: #000;
        }

        .btn-blue {
            background: var(--accent2);
            color: #000;
        }

        .btn-ghost {
            background: var(--border);
            color: var(--text);
        }

        .btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .result {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: .75rem 1rem;
            font-size: .75rem;
            line-height: 1.7;
            margin-top: .75rem;
            min-height: 60px;
            max-height: 320px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .result .ok {
            color: var(--accent);
        }

        .result .err {
            color: var(--danger);
        }

        .result .info {
            color: var(--accent2);
        }

        .result .warn {
            color: var(--warn);
        }

        .meter-wrap {
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
            margin: .3rem 0 .6rem;
        }

        .meter-bar {
            height: 100%;
            border-radius: 3px;
            transition: width .4s ease;
            background: var(--accent);
        }

        .ttfb-row {
            display: flex;
            justify-content: space-between;
            font-size: .7rem;
            color: var(--muted);
        }

        .ttfb-val {
            font-weight: 700;
        }

        .server-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .72rem;
        }

        .server-table tr:not(:last-child) td {
            border-bottom: 1px solid var(--border);
        }

        .server-table td {
            padding: .35rem .5rem;
            vertical-align: top;
        }

        .server-table td:first-child {
            color: var(--muted);
            width: 38%;
            word-break: break-all;
        }

        .server-table td:last-child {
            color: var(--accent2);
            word-break: break-all;
        }

        @media (max-width: 600px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <header>
            <h1>⚡ PHP App Server</h1>
            <span class="badge online">online</span>
            <div class="meta">
                Saat: <span><?= $time ?></span><br>
                PHP <span><?= PHP_VERSION ?></span> &mdash; PID <span><?= getmypid() ?></span>
            </div>
        </header>

        <div class="stats">
            <div class="stat">
                <div class="stat-label">TTFB</div>
                <div class="stat-value" id="stat-ttfb">—</div>
            </div>
            <div class="stat">
                <div class="stat-label">Method</div>
                <div class="stat-value blue"><?= $_SERVER['REQUEST_METHOD'] ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Keep-Alive</div>
                <div class="stat-value" id="stat-ka">—</div>
            </div>
            <div class="stat">
                <div class="stat-label">Worker PID</div>
                <div class="stat-value warn"><?= getmypid() ?></div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" data-tab="ttfb">TTFB</button>
            <button class="tab" data-tab="post">POST / JSON</button>
            <button class="tab" data-tab="upload">File Upload</button>
            <button class="tab" data-tab="globals">Superglobals</button>
        </div>

        <!-- TTFB TAB -->
        <div class="tab-content active" id="tab-ttfb">
            <div class="card">
                <div class="card-title">Response Time Test</div>
                <div class="field">
                    <label>Endpoint</label>
                    <input type="text" id="ttfb-url" value="/">
                </div>
                <div class="row">
                    <div style="flex:1">
                        <label>İstek sayısı</label>
                        <input type="text" id="ttfb-count" value="10">
                    </div>
                    <label class="toggle" style="margin-top:1.2rem">
                        <input type="checkbox" id="ttfb-keepalive"> Keep-Alive
                    </label>
                </div>
                <button class="btn btn-green" onclick="runTTFB()">▶ Çalıştır</button>
                <div id="ttfb-results" style="margin-top:1rem"></div>
            </div>
        </div>

        <!-- POST / JSON TAB -->
        <div class="tab-content" id="tab-post">
            <div class="card">
                <div class="card-title">POST / JSON Test</div>
                <div class="field">
                    <label>Content-Type</label>
                    <select id="post-ct">
                        <option value="json">application/json</option>
                        <option value="form">application/x-www-form-urlencoded</option>
                    </select>
                </div>
                <div class="field">
                    <label>Body</label>
                    <textarea id="post-body">{"name": "Mustafa", "test": true}</textarea>
                </div>
                <button class="btn btn-green" onclick="runPost()">▶ Gönder</button>
                <div class="result" id="post-result">—</div>
            </div>
        </div>

        <!-- FILE UPLOAD TAB -->
        <div class="tab-content" id="tab-upload">
            <div class="card">
                <div class="card-title">File Upload Test</div>
                <div class="field">
                    <label>Dosya seç</label>
                    <input type="file" id="upload-file" multiple>
                </div>
                <button class="btn btn-green" onclick="runUpload()">▶ Yükle</button>
                <div class="result" id="upload-result">—</div>
            </div>
        </div>

        <!-- SUPERGLOBALS TAB -->
        <div class="tab-content" id="tab-globals">
            <div class="card">
                <div class="card-title">$_SERVER</div>
                <table class="server-table">
                    <?php foreach ($_SERVER as $k => $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($k) ?></td>
                            <td><?= htmlspecialchars((string)$v) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="card">
                <div class="card-title">$_GET</div>
                <div class="result"><?= $_GET ? htmlspecialchars(json_encode($_GET, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '<span class="warn">boş</span>' ?></div>
            </div>
            <div class="card">
                <div class="card-title">$_POST</div>
                <div class="result"><?= $_POST ? htmlspecialchars(json_encode($_POST, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '<span class="warn">boş</span>' ?></div>
            </div>
        </div>
    </div>

    <script>
        // Tabs
        document.querySelectorAll('.tab').forEach(t => {
            t.addEventListener('click', () => {
                document.querySelectorAll('.tab, .tab-content').forEach(el => el.classList.remove('active'));
                t.classList.add('active');
                document.getElementById('tab-' + t.dataset.tab).classList.add('active');
            });
        });

        // Keep-alive check
        (async () => {
            const t0 = performance.now();
            await fetch('/');
            const t1 = performance.now() - t0;
            document.getElementById('stat-ttfb').textContent = t1.toFixed(1) + 'ms';
            document.getElementById('stat-ttfb').className = 'stat-value ' + (t1 < 20 ? '' : t1 < 100 ? 'warn' : 'danger');

            const r = await fetch('/');
            document.getElementById('stat-ka').textContent = r.headers.get('connection') || 'keep-alive';
            document.getElementById('stat-ka').className = 'stat-value ok';
        })();

        // TTFB test
        async function runTTFB() {
            const url = document.getElementById('ttfb-url').value;
            const count = parseInt(document.getElementById('ttfb-count').value) || 5;
            const keepAlive = document.getElementById('ttfb-keepalive').checked;
            const el = document.getElementById('ttfb-results');
            el.innerHTML = '';

            const headers = keepAlive ? {} : {
                'Connection': 'close'
            };

            const requests = Array.from({
                length: count
            }, (_, i) => {
                const t0 = performance.now();
                return fetch(url + '?_t=' + Date.now() + i + (keepAlive ? '' : '&_close=1'), {
                        headers
                    })
                    .then(r => ({
                        r,
                        ms: performance.now() - t0,
                        idx: i
                    }));
            });

            const results = await Promise.all(requests);
            const times = [];

            for (const {
                    r,
                    ms,
                    idx
                }
                of results) {
                const wid = r.headers.get('x-worker-id') || '?';
                times.push(ms);
                const pct = Math.min((ms / 200) * 100, 100);
                const cls = ms < 20 ? 'var(--accent)' : ms < 100 ? 'var(--warn)' : 'var(--danger)';
                el.innerHTML += `
            <div class="ttfb-row">
                <span>#${idx + 1} <span style="color:var(--muted)">w:${wid} - </span></span>
                <span class="ttfb-val" style="color:${cls}">${ms.toFixed(2)}ms</span>
            </div>
            <div class="meter-wrap">
                <div class="meter-bar" style="width:${pct}%;background:${cls}"></div>
            </div>`;
            }

            const avg = times.reduce((a, b) => a + b, 0) / times.length;
            const min = Math.min(...times);
            const max = Math.max(...times);
            el.innerHTML += `
        <div style="margin-top:.75rem;font-size:.72rem;color:var(--muted);display:flex;gap:1.5rem">
            <span>avg <b style="color:var(--accent)">${avg.toFixed(2)}ms</b></span>
            <span>min <b style="color:var(--accent)">${min.toFixed(2)}ms</b></span>
            <span>max <b style="color:var(--warn)">${max.toFixed(2)}ms</b></span>
        </div>`;

            document.getElementById('stat-ttfb').textContent = avg.toFixed(1) + 'ms';
        }

        // POST test
        async function runPost() {
            const ct = document.getElementById('post-ct').value;
            const raw = document.getElementById('post-body').value;
            const el = document.getElementById('post-result');
            el.innerHTML = '<span class="info">Gönderiliyor...</span>';

            const headers = {};
            let body;

            if (ct === 'json') {
                headers['Content-Type'] = 'application/json';
                body = raw;
            } else {
                headers['Content-Type'] = 'application/x-www-form-urlencoded';
                try {
                    const obj = JSON.parse(raw);
                    body = new URLSearchParams(obj).toString();
                } catch {
                    body = raw;
                }
            }

            try {
                const t0 = performance.now();
                const r = await fetch('/test-echo', {
                    method: 'POST',
                    headers,
                    body
                });
                const ms = performance.now() - t0;
                const txt = await r.text();
                el.innerHTML = `<span class="ok">HTTP ${r.status} — ${ms.toFixed(1)}ms</span>\n\n${escHtml(txt)}`;
            } catch (e) {
                el.innerHTML = `<span class="err">${escHtml(e.message)}</span>`;
            }
        }

        // Upload test
        async function runUpload() {
            const files = document.getElementById('upload-file').files;
            const el = document.getElementById('upload-result');
            if (!files.length) {
                el.innerHTML = '<span class="warn">Dosya seçilmedi</span>';
                return;
            }

            el.innerHTML = '<span class="info">Yükleniyor...</span>';
            const fd = new FormData();
            for (const f of files) fd.append('files[]', f);

            try {
                const t0 = performance.now();
                const r = await fetch('/test-upload', {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'Connection': 'close'
                    }
                });
                const ms = performance.now() - t0;
                const txt = await r.text();
                el.innerHTML = `<span class="ok">HTTP ${r.status} — ${ms.toFixed(1)}ms</span>\n\n${escHtml(txt)}`;
            } catch (e) {
                el.innerHTML = `<span class="err">${escHtml(e.message)}</span>`;
            }
        }

        function escHtml(s) {
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    </script>
</body>

</html>