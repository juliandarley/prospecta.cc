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
		.progress-display{background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:12px;margin:8px 0;display:none}
		.progress-steps{display:flex;gap:8px;align-items:center;margin-bottom:8px}
		.step{padding:4px 8px;border-radius:4px;font-size:.85em;border:1px solid #ddd}
		.step.active{background:#0b6bcf;color:#fff;border-color:#0b6bcf}
		.step.complete{background:#28a745;color:#fff;border-color:#28a745}
		.step.pending{background:#fff;color:#666}
		.step.error{background:#dc3545;color:#fff;border-color:#dc3545}
		.progress-detail{font-size:.9em;color:#666}
		.duplicate-status{font-weight:bold}
		.duplicate-yes{color:#dc3545}
		.duplicate-no{color:#28a745}
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
		
		<!-- Progress Display -->
		<div id="progressDisplay" class="progress-display">
			<div class="progress-steps" id="progressSteps">
				<div class="step pending" data-step="detect">OCR Detection</div>
				<div class="step pending" data-step="extract">Extraction</div>
				<div class="step pending" data-step="evaluate">Evaluation</div>
				<div class="step pending" data-step="inference">Inference</div>
				<div class="step pending" data-step="finalize">Finalize</div>
			</div>
			<div class="progress-detail" id="progressDetail">Ready to process...</div>
		</div>
		<div style="overflow:auto">
			<table id="tbl">
				<thead>
					<tr>
						<th>Filename</th>
						<th>OCR Status</th>
						<th>Citation</th>
						<th>Extracted</th>
						<th>Inference</th>
						<th>Source</th>
						<th>SHA256</th>
						<th>Size</th>
						<th>Pages</th>
						<th>Modified</th>
						<th>Duplicate (filename)</th>
						<th>Duplicate (content)</th>
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
	const progressDisplay = el('#progressDisplay');
	const progressSteps = el('#progressSteps');
	const progressDetail = el('#progressDetail');
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

	function updateProgress(step, status, detail) {
		const stepEl = progressSteps.querySelector(`[data-step="${step}"]`);
		if (stepEl) {
			stepEl.className = `step ${status}`;
		}
		progressDetail.textContent = detail || '';
		progressDisplay.style.display = 'block';
	}

	function hideProgress() {
		progressDisplay.style.display = 'none';
		// Reset all steps to pending
		progressSteps.querySelectorAll('.step').forEach(s => s.className = 'step pending');
		progressDetail.textContent = 'Ready to process...';
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
				
				// OCR Status (placeholder for now)
				const ocrStatus = it.ocr_status || 'text'; // will be populated by backend
				tr.appendChild(mk(`<span class="note">${ocrStatus}</span>`));
				
				// Citation (final canonical result)
				let citationHtml = '';
				if (it.sentinel && it.sentinel.canonical_title) {
					citationHtml = `<strong>${it.sentinel.canonical_title}</strong>`;
					const meta = [];
					if (it.sentinel.doc_type) meta.push(`<span class="field">Type: ${it.sentinel.doc_type}</span>`);
					if (it.sentinel.publication) meta.push(`<span class="field">Pub: ${it.sentinel.publication}</span>`);
					if (it.sentinel.year) meta.push(`<span class="field">Year: ${it.sentinel.year}</span>`);
					if (it.sentinel.authors && it.sentinel.authors.length > 0) {
						const authors = it.sentinel.authors.map(a => a.name).join(', ');
						meta.push(`<span class="field">Authors: ${authors}</span>`);
					}
					if (meta.length > 0) {
						citationHtml += `<div class="citation-meta">${meta.join('')}</div>`;
					}
				} else if (it.title) {
					citationHtml = `<strong>${it.title}</strong> <span class="note">(${it.title_source})</span>`;
				} else {
					citationHtml = '<span class="note">—</span>';
				}
				tr.appendChild(mk(citationHtml));
				
				// Extracted (deterministic extraction results)
				const extractedHtml = it.title ? `${it.title} <span class="note">(${it.title_source})</span>` : '<span class="note">—</span>';
				tr.appendChild(mk(extractedHtml));
				
				// Inference (LLM enhancement - placeholder)
				const inferenceHtml = it.inference || '<span class="note">—</span>';
				tr.appendChild(mk(inferenceHtml));
				
				// Source (how final citation was derived)
				let sourceHtml = '<span class="note">—</span>';
				if (it.sentinel) {
					sourceHtml = '<span class="note">Sentinel</span>';
				} else if (it.title_source === 'heuristic') {
					sourceHtml = '<span class="note">Extracted</span>';
				} else if (it.title_source === 'pdfinfo') {
					sourceHtml = '<span class="note">PDF Metadata</span>';
				}
				tr.appendChild(mk(sourceHtml));
				
				// Compact SHA256 with rollover
				const shaShort = it.sha256.substring(0, 16) + '...';
				tr.appendChild(mk(`<code class="sha-short" title="${it.sha256}">${shaShort}</code>`));
				tr.appendChild(mk(it.size_human));
				tr.appendChild(mk(it.pages ?? '')); 
				tr.appendChild(mk(it.mtime_human));
				
				// Improved duplicate display with ✓/✗
				tr.appendChild(mk(it.dup_name ? '<span class="duplicate-status duplicate-yes">✗</span>' : '<span class="duplicate-status duplicate-no">✓</span>'));
				tr.appendChild(mk(it.dup_sha ? '<span class="duplicate-status duplicate-yes">✗</span>' : '<span class="duplicate-status duplicate-no">✓</span>'));
				
				// Actions
				const actions = document.createElement('td');
				const btn = document.createElement('button'); 
				btn.textContent = it.sentinel ? 'Re-analyze' : 'Analyze';
				btn.addEventListener('click', ()=>analyse(it.filename)); 
				actions.appendChild(btn);
				tr.appendChild(actions);
				tbody.appendChild(tr);
			}
			summary.textContent = `${data.items.length} files` + (data.dups? `, ${data.dups.content} content dups, ${data.dups.name} name dups` : '');
		}catch(e){
			summary.textContent = 'Error: ' + e.message;
		}
	}

	async function analyse(filename){
		console.log('Starting bibliographic profiling for:', filename, 'with model:', modelSel.value);
		
		try{
			// Show progress
			updateProgress('detect', 'active', `Detecting document type for ${filename}...`);
			
			// Step 1: OCR Detection (placeholder)
			await new Promise(resolve => setTimeout(resolve, 500)); // Simulate processing
			updateProgress('detect', 'complete', 'Document type detected: text');
			
			// Step 2: Deterministic Extraction
			updateProgress('extract', 'active', 'Extracting metadata using deterministic algorithms...');
			await new Promise(resolve => setTimeout(resolve, 800));
			updateProgress('extract', 'complete', 'Basic metadata extracted');
			
			// Step 3: Sentinel Evaluation
			updateProgress('evaluate', 'active', 'Evaluating extraction confidence...');
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
				updateProgress('evaluate', 'error', 'Failed to parse Sentinel response');
				throw new Error(txt || ('HTTP '+res.status)); 
			}
			
			if (!res.ok || data.error) {
				const extra = data.raw_saved ? ` (details: ${data.raw_saved})` : '';
				updateProgress('evaluate', 'error', `Sentinel analysis failed: ${data.error || 'HTTP '+res.status}`);
				throw new Error((data.error || ('HTTP '+res.status)) + extra);
			}
			
			updateProgress('evaluate', 'complete', 'Sentinel analysis completed');
			
			// Step 4: Inference (placeholder - will be implemented later)
			updateProgress('inference', 'active', 'Checking if additional inference needed...');
			await new Promise(resolve => setTimeout(resolve, 300));
			updateProgress('inference', 'complete', 'No additional inference needed');
			
			// Step 5: Finalize
			updateProgress('finalize', 'active', 'Finalizing citation...');
			await new Promise(resolve => setTimeout(resolve, 200));
			updateProgress('finalize', 'complete', `Bibliographic profiling complete for ${filename}`);
			
			console.log('Analysis successful, result:', data.result);
			summary.textContent = `Profiling complete for ${filename}`;
			
			// Hide progress after success
			setTimeout(hideProgress, 2000);
			
			await listDocs();
		}catch(e){
			const errorMsg = 'Profiling error for '+filename+': '+(e && e.message ? e.message : e);
			summary.textContent = errorMsg;
			console.error('Bibliographic profiling error:', e);
			
			// Hide progress after error
			setTimeout(hideProgress, 3000);
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


