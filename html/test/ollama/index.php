<?php
// Simple Ollama connectivity test to delphi.lan
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Ollama Test</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;margin:2rem}
textarea{width:100%;height:140px}
select,button{padding:8px}
.mono{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;white-space:pre-wrap;background:#f7f7f7;border:1px solid #e1e4e8;border-radius:6px;padding:10px}
</style>
</head>
<body>
<h1><a href="/" style="text-decoration:none;color:inherit">Prōspecta</a> · Ollama connectivity test</h1>
<p>Models on delphi.lan:</p>
<pre id="models" class="mono">Loading...</pre>

<h3>Generate / Chat</h3>
<label>Model:
  <select id="model"></select>
</label>
<br/><br/>
<label>Prompt</label>
<textarea id="prompt">Return pure JSON: {"ping":"pong"}</textarea>
<br/>
<label><input type="checkbox" id="forceJson" /> Expect JSON</label>
<button id="run">Run (generate)</button>
<button id="chat">Run (chat)</button>

<h3>Response</h3>
<pre id="out" class="mono"></pre>

<script>
async function loadModels(){
  const res = await fetch('/rpc/ollama-tags.php');
  const data = await res.json();
  const list = data.models || [];
  document.getElementById('models').textContent = JSON.stringify(list.map(m=>m.name), null, 2);
  const sel = document.getElementById('model');
  sel.innerHTML='';
  for(const m of list){ const opt=document.createElement('option'); opt.value=m.name; opt.textContent=m.name; sel.appendChild(opt); }
}
async function run(){
  const out = document.getElementById('out');
  try{
    const model = document.getElementById('model').value;
    const prompt = document.getElementById('prompt').value;
    const forceJson = document.getElementById('forceJson').checked;
    const payload = { model, prompt, stream:false };
    if (forceJson) payload.format = 'json';
    const res = await fetch('/rpc/ollama-generate.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload), cache:'no-store' });
    const txt = await res.text();
    let body; try { body = JSON.parse(txt); } catch { body = null }
    let content = body && typeof body.response === 'string' ? body.response : txt;
    // If forceJson and content looks like JSON inside a string, pretty print; else show raw prose
    let pretty;
    try { pretty = JSON.stringify(JSON.parse(content), null, 2); }
    catch { pretty = content; }
    out.textContent = 'HTTP '+res.status+'\n'+pretty;
  }catch(e){
    out.textContent = 'Error: '+(e && e.message ? e.message : e);
  }
}
document.getElementById('run').addEventListener('click', run);
document.getElementById('chat').addEventListener('click', async ()=>{
  const out = document.getElementById('out');
  out.textContent = 'Running chat...';
  try{
    const model = document.getElementById('model').value;
    const prompt = document.getElementById('prompt').value;
    const body = { model, prompt, system: 'You are a helpful assistant. Answer clearly in prose.' };
    const res = await fetch('/rpc/ollama-chat.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body), cache:'no-store' });
    const txt = await res.text();
    let json; try{ json = JSON.parse(txt); }catch{ json = null; }
    const prose = json && json.message && typeof json.message.content === 'string' ? json.message.content : txt;
    out.textContent = 'HTTP '+res.status+'\n'+prose;
  }catch(e){ out.textContent = 'Error: '+(e.message||e); }
});
loadModels();
</script>
</body>
</html>


