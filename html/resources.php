<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Resources</title>
	<style>
		body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;margin:2rem;line-height:1.4}
		h1{margin-top:0}
		.panel{border:1px solid #e1e4e8;border-radius:8px;padding:14px;background:#fff;margin-bottom:14px}
		.row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
		input[type=file]{padding:8px}
		button{padding:10px 14px;border:1px solid #0b6bcf;background:#0b6bcf;color:#fff;border-radius:6px;cursor:pointer}
		button:disabled{opacity:.6;cursor:not-allowed}
		table{border-collapse:collapse;width:100%}
		th,td{border:1px solid #e1e4e8;padding:8px;text-align:left}
		th{background:#f7f7f7}
		.badge{display:inline-block;padding:2px 6px;border-radius:4px;font-size:.85em}
		.dup{background:#ffe9e9;color:#c00}
		.ok{background:#eaffea;color:#175f17}
		.note{color:#666;font-size:.9em}
	</style>
</head>
<body>
	<h1>Resources</h1>
	<div class="panel">
		<h3>Upload PDF</h3>
		<div class="row">
			<input id="file" type="file" accept="application/pdf" />
			<button id="upload">Upload</button>
			<span id="status" class="note"></span>
		</div>
		<p class="note">Files are saved to <code>/var/www/prospecta.cc/data/pdfs</code>. Duplicates by content (SHA256) are flagged.</p>
	</div>

	<div class="panel">
		<h3>All Documents</h3>
		<div class="row" style="justify-content:space-between">
			<div>
				<button id="refresh">Refresh</button>
			</div>
			<div class="note" id="summary"></div>
		</div>
		<div style="overflow:auto">
			<table id="tbl">
				<thead>
					<tr>
						<th>Filename</th>
						<th>SHA256</th>
						<th>Size</th>
						<th>Pages</th>
						<th>Modified</th>
						<th>Dup (name)</th>
						<th>Dup (content)</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>

	<script>
	const el = sel => document.querySelector(sel);
	const fileInput = el('#file');
	const uploadBtn = el('#upload');
	const statusEl  = el('#status');
	const refreshBtn= el('#refresh');
	const tbody     = el('#tbl tbody');
	const summary   = el('#summary');

	async function listDocs(){
		try{
			summary.textContent = 'Loading...';
			const res = await fetch('/rpc/resources-list.php');
			let data;
			if (!res.ok) {
				const t = await res.text();
				try { data = JSON.parse(t); throw new Error(data.error || t || ('HTTP '+res.status)); }
				catch { throw new Error(t || ('HTTP '+res.status)); }
			}
			data = data || await res.json();
			if(!Array.isArray(data.items)) throw new Error('Bad response');
			tbody.innerHTML = '';
			for(const it of data.items){
				const tr = document.createElement('tr');
				const mk = (h)=>{const td=document.createElement('td');td.innerHTML=h;return td};
				const name = `<a href="/rpc/resources-download.php?name=${encodeURIComponent(it.filename)}" target="_blank">${it.filename}</a>`;
				tr.appendChild(mk(name));
				tr.appendChild(mk(`<code>${it.sha256}</code>`));
				tr.appendChild(mk(it.size_human));
				tr.appendChild(mk(it.pages ?? '')); 
				tr.appendChild(mk(it.mtime_human));
				tr.appendChild(mk(it.dup_name? '<span class="badge dup">dup</span>':'<span class="badge ok">ok</span>'));
				tr.appendChild(mk(it.dup_sha?  '<span class="badge dup">dup</span>':'<span class="badge ok">ok</span>'));
				tbody.appendChild(tr);
			}
			summary.textContent = `${data.items.length} files` + (data.dups? `, ${data.dups.content} content dups, ${data.dups.name} name dups` : '');
		}catch(e){
			summary.textContent = 'Error: ' + e.message;
		}
	}

	async function upload(){
		const f = fileInput.files[0];
		if(!f){ statusEl.textContent = 'Choose a PDF'; return; }
		uploadBtn.disabled = true; statusEl.textContent = 'Uploading...';
		try{
			const fd = new FormData(); fd.append('file', f);
			const res = await fetch('/rpc/resources-upload.php', { method:'POST', body: fd });
			let data;
			if (!res.ok) {
				const t = await res.text();
				try { data = JSON.parse(t); throw new Error(data.error || t || ('HTTP '+res.status)); }
				catch { throw new Error(t || ('HTTP '+res.status)); }
			}
			data = data || await res.json();
			if(data.error) throw new Error(data.error);
			statusEl.textContent = 'Uploaded: ' + data.filename + (data.renamed? ' (renamed)':'');
			fileInput.value = '';
			await listDocs();
		}catch(e){ statusEl.textContent = 'Error: ' + e.message; }
		finally{ uploadBtn.disabled = false; }
	}

	uploadBtn.addEventListener('click', upload);
	refreshBtn.addEventListener('click', listDocs);
	listDocs();
	</script>
</body>
</html>


