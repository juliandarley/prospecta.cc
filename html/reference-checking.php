<?php
// Minimal UI stub for PDF reference checking
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Reference Checking</title>
    <style>
        body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;margin:2rem;line-height:1.4}
        .row{display:flex;gap:12px;flex-wrap:wrap}
        input[type=text]{flex:1;padding:10px;border:1px solid #ddd;border-radius:6px;min-width:260px}
        button{padding:10px 14px;border:1px solid #0b6bcf;background:#0b6bcf;color:#fff;border-radius:6px;cursor:pointer}
        button:disabled{opacity:.6;cursor:not-allowed}
        .results{margin-top:16px}
        .hit{border:1px solid #e1e4e8;border-radius:8px;padding:12px;margin-bottom:10px;background:#fff}
        .meta{color:#666;font-size:.9em}
        mark{background: #fcf29a}
    </style>
</head>
<body>
    <h1>Reference Checking</h1>
    <div class="row">
        <input id="q" type="text" placeholder="Search phrase or name (e.g., 'John Smith')" />
        <button id="go">Search PDFs</button>
    </div>
    <div class="results" id="results"></div>

    <script>
    const btn = document.getElementById('go');
    const q   = document.getElementById('q');
    const out = document.getElementById('results');
    btn.addEventListener('click', async () => {
        const query = q.value.trim();
        if (!query) return;
        btn.disabled = true; out.innerHTML = 'Searching...';
        try {
            const res = await fetch('/rpc/reference-search.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({query})
            });
            const data = await res.json();
            if (!Array.isArray(data.hits)) throw new Error('Bad response');
            out.innerHTML = data.hits.length ? '' : 'No results.';
            for (const h of data.hits) {
                const div = document.createElement('div');
                div.className = 'hit';
                div.innerHTML = `<div class="meta"><strong>${h.file}</strong> — page ${h.page}</div>` +
                                `<div>${h.snippet}</div>`;
                out.appendChild(div);
            }
        } catch (e) {
            out.textContent = 'Error: ' + e.message;
        } finally {
            btn.disabled = false;
        }
    });
    </script>
</body>
</html>



