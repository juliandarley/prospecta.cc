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
		.sha-short{max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:help}
		.citation-meta{font-size:.85em;color:#666;margin-top:2px}
		.citation-meta .field{margin-right:12px}
	</style>
</head>
<body>
	<h1><a href="/" style="text-decoration:none;color:inherit">Prōspecta</a> · Resources</h1>
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
			<div class="row" style="gap:8px">
				<button id="refresh">Refresh</button>
				<label class="note">Sentinel model:
					<select id="sentinelModel"></select>
				</label>
			</div>
			<div class="note" id="summary"></div>
		</div>
		<div style="overflow:auto">
			<table id="tbl">
				<thead>
					<tr>
						<th>Filename</th>
						<th>Citation</th>
						<th>SHA256</th>
						<th>Size</th>
						<th>Pages</th>
						<th>Modified</th>
						<th>Dup (name)</th>
						<th>Dup (content)</th>
						<th>Suggested (Sentinel)</th>
						<th>Conf.</th>
						<th>Actions</th>
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
	const modelSel  = el('#sentinelModel');
	async function loadModels(){
		try{
			const res = await fetch('/rpc/sentinel-models.php');
			const models = await res.json();
			modelSel.innerHTML = '';
			for(const m of models){
				const opt = document.createElement('option');
				opt.value = m.id; opt.textContent = m.label + ' ['+m.modality+']';
				modelSel.appendChild(opt);
			}
		}catch(e){ /* ignore */ }
	}

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
				
				// Citation info
				let citationHtml = '';
				if (it.title) {
					citationHtml = `<strong>${it.title}</strong> <span class="note">(${it.title_source})</span>`;
				} else {
					citationHtml = '<span class="note">—</span>';
				}
				
				// Add citation metadata if available from Sentinel
				if (it.sentinel) {
					const meta = [];
					if (it.sentinel.doc_type) meta.push(`<span class="field">Type: ${it.sentinel.doc_type}</span>`);
					if (it.sentinel.publication) meta.push(`<span class="field">Pub: ${it.sentinel.publication}</span>`);
					if (it.sentinel.year) meta.push(`<span class="field">Year: ${it.sentinel.year}</span>`);
					if (it.sentinel.date && it.sentinel.date !== it.sentinel.year) meta.push(`<span class="field">Date: ${it.sentinel.date}</span>`);
					if (it.sentinel.authors && it.sentinel.authors.length > 0) {
						const authors = it.sentinel.authors.map(a => a.name).join(', ');
						meta.push(`<span class="field">Authors: ${authors}</span>`);
					}
					if (meta.length > 0) {
						citationHtml += `<div class="citation-meta">${meta.join('')}</div>`;
					}
				}
				tr.appendChild(mk(citationHtml));
				
				// Compact SHA256 with rollover
				const shaShort = it.sha256.substring(0, 16) + '...';
				tr.appendChild(mk(`<code class="sha-short" title="${it.sha256}">${shaShort}</code>`));
				tr.appendChild(mk(it.size_human));
				tr.appendChild(mk(it.pages ?? '')); 
				tr.appendChild(mk(it.mtime_human));
				tr.appendChild(mk(it.dup_name? '<span class="badge dup">dup</span>':'<span class="badge ok">ok</span>'));
				tr.appendChild(mk(it.dup_sha?  '<span class="badge dup">dup</span>':'<span class="badge ok">ok</span>'));
				// Sentinel suggestion
				const sug = it.sentinel && it.sentinel.canonical_title ? it.sentinel.canonical_title : '';
				const conf = it.sentinel && typeof it.sentinel.confidence === 'number' ? Math.round(it.sentinel.confidence*100)+'%' : '';
				tr.appendChild(mk(sug ? sug : '<span class="note">—</span>'));
				tr.appendChild(mk(conf));
				const actions = document.createElement('td');
				const btn = document.createElement('button'); btn.textContent='Analyse with Sentinel';
				btn.addEventListener('click', ()=>analyse(it.filename)); actions.appendChild(btn);
				tr.appendChild(actions);
				tbody.appendChild(tr);
			}
			summary.textContent = `${data.items.length} files` + (data.dups? `, ${data.dups.content} content dups, ${data.dups.name} name dups` : '');
		}catch(e){
			summary.textContent = 'Error: ' + e.message;
		}
	}

	async function analyse(filename){
		console.log('Starting Sentinel analysis for:', filename, 'with model:', modelSel.value);
		try{
			summary.textContent = `Analysing ${filename} with ${modelSel.options[modelSel.selectedIndex].text}...`;
			const body = { filename, modelId: modelSel.value };
			console.log('Sending request to /rpc/sentinel-extract.php with:', body);
			const res = await fetch('/rpc/sentinel-extract.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
			console.log('Response status:', res.status, res.statusText);
			const txt = await res.text();
			console.log('Response text length:', txt.length, 'first 200 chars:', txt.substring(0, 200));
			let data; 
			try{ 
				data = JSON.parse(txt); 
			}catch{ 
				console.error('Failed to parse JSON response:', txt);
				throw new Error(txt || ('HTTP '+res.status)); 
			}
			if (!res.ok || data.error) {
				const extra = data.raw_saved ? ` (details: ${data.raw_saved})` : '';
				throw new Error((data.error || ('HTTP '+res.status)) + extra);
			}
			console.log('Analysis successful, result:', data.result);
			summary.textContent = `Analysis complete for ${filename}`;
			await listDocs();
		}catch(e){
			const errorMsg = 'Sentinel error for '+filename+': '+(e && e.message ? e.message : e);
			summary.textContent = errorMsg;
			console.error('Sentinel analysis error:', e);
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
	loadModels();
	listDocs();
	</script>
</body>
</html>


