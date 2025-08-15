<?php
// Simple landing page with navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Prōspecta.cc</title>
	<style>
		body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;margin:2rem;line-height:1.4}
		h1{margin-top:0}
		.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin-top:16px}
		.card{border:1px solid #e1e4e8;border-radius:8px;padding:14px;background:#fff}
		.card h3{margin:0 0 6px}
		.card p{margin:0;color:#555}
		.card a{display:inline-block;margin-top:8px;text-decoration:none;color:#0b6bcf}
	</style>
</head>
<body>
	<h1>Prōspecta.cc</h1>
	<p>Skeleton app on LAN vhost. Use the tools below to get started.</p>
	<div class="grid">
		<div class="card">
			<h3>Reference Checking</h3>
			<p>Search PDFs in <code>/var/www/prospecta.cc/data/pdfs</code> for names/phrases.</p>
			<a href="/reference-checking">Open</a>
		</div>
		<div class="card">
			<h3>Resources</h3>
			<p>Upload PDFs/URLs, list documents, detect duplicates, and view titles.</p>
			<a href="/resources">Open</a>
		</div>
		<div class="card">
			<h3>Health</h3>
			<p>Simple health/status endpoint.</p>
			<a href="/health">Open</a>
		</div>
		<div class="card">
			<h3>Tests</h3>
			<p>Connectivity tests and utilities (Ollama on delphi.lan).</p>
			<a href="/test">Open</a>
		</div>
	</div>
</body>
</html>



