# Prospecta.cc – Reference Checking & Sentinel Handover (2025‑08‑15)

## What’s running (LAN)
- Base URL: `http://prospecta.orcus.lan:35232/`
- Document root: `/var/www/prospecta.cc/html`
- Pretty URLs enabled (vhost Directory rewrites)

### Key pages
- Home: `/`
- Resources (upload + list + dedupe + titles + Sentinel): `/resources`
- Reference checking (pdftotext v1): `/reference-checking`
- Tests → Ollama connectivity UI: `/test/ollama`
- Health: `/health`

## Directory layout
- `html/` web root
  - `resources.php` (UI)
  - `reference-checking.php` (v1 UI)
  - `rpc/` server endpoints
    - `resources-upload.php` (stores PDFs to `data/pdfs`)
    - `resources-list.php` (lists PDFs, SHA256, pages, title extraction)
    - `resources-download.php` (secure proxy to stream PDFs)
    - `sentinel-extract.php` (Sentinel metadata extraction – text/vision)
    - `sentinel-models.php` (serve models config)
    - `ollama-tags.php` (list models from delphi)
    - `ollama-generate.php` (server proxy to `/api/generate`)
    - `ollama-chat.php` (server proxy to `/api/chat`)
  - `test/`
    - `index.php` (links)
    - `ollama/index.php` (interactive model list + generate/chat)
- `data/pdfs/` uploaded PDFs (outside web root)
- `tmp/textcache/` per‑page pdftotext cache
- `tmp/titlecache/` title cache + Sentinel raw dumps (`*.sentinel.raw.txt`) + images for vision
- `config/` runtime config (not web‑served)
  - `v1-apache-vhost.config`, `v2-apache-vhost.config`
  - `sentinel-models.json` (dropdown models)
  - `sentinel-endpoints.json` (maps model ids → endpoints/models)
- `logs/`
  - `sentinel.log`, `sentinel.error.log` (rule 14)
  - vhost access/error logs (Apache)

## System requirements
- PHP‑FPM 8.3 with limits (already applied for FPM):
  - `upload_max_filesize = 200M`, `post_max_size = 210M`, `max_file_uploads = 50`, `max_execution_time = 180`
- poppler utilities: `pdfinfo`, `pdftotext`, `pdftoppm`
- Optional: `acl` package if using ACL approach for group RWX
- Permissions (Option B pattern):
  - `chgrp -R www-data /var/www/prospecta.cc/data/pdfs /var/www/prospecta.cc/tmp`
  - `chmod -R 2775 /var/www/prospecta.cc/data/pdfs /var/www/prospecta.cc/tmp`
  - `setfacl -R -m g:www-data:rwx -m d:g:www-data:rwx /var/www/prospecta.cc/data/pdfs /var/www/prospecta.cc/tmp`

## Sentinel (LLM) – current config
- Endpoint (Ollama on delphi): `http://delphi.lan:11434`
- `config/sentinel-endpoints.json` → `ollama-local`:
  - `textModel`: `gemma3:4b`
  - `visionModel`: `gemma3:4b-vision` (set to an installed vision model; if not present, adjust)
- `config/sentinel-models.json` dropdown entries map to the endpoint ref `ollama-local` (and cloud placeholders)

### How Sentinel is called
- Resources → “Analyse with Sentinel” sends `{ filename, modelId }` to `/rpc/sentinel-extract.php`
- The endpoint gathers:
  - `pdfinfo` text
  - pages 1–3 text (`pdftotext`)
  - for vision, pages 1–3 PNG (`pdftoppm`) and base64 embeds
- For Ollama, we call `/api/generate` with `format: json` and parse `response` → JSON
- On failure, we save the raw model output to `tmp/titlecache/<sha>.sentinel.raw.txt` and log a line to `logs/sentinel.error.log`

## Current features
- Upload (PDF) with SHA256 and filename collision handling
- List with columns: Filename, Title (pdfinfo/cache/heuristic), SHA256, Size, Pages, Modified, duplicate flags, Sentinel suggestion + confidence
- Secure streaming of PDFs via RPC (no direct web access to `data/`)
- Reference Checking (v1) using case‑insensitive substring on per‑page `pdftotext`
- Test UI to interact with Ollama: list models, `generate` (JSON or prose), `chat` (prose)

## Troubleshooting
- Upload returns 400 → check PHP limits (FPM) and temp dir perms
- Titles missing → ensure `pdfinfo` exists; for heuristics, page 1 must be text or OCR’d
- Sentinel errors:
  - `bad sentinel JSON http=404 ...raw.txt` → model path not found or wrong model name; check `/test/ollama/` and update `sentinel-endpoints.json`
  - `ollama not reachable` → confirm `curl http://delphi.lan:11434/api/tags`
  - See `logs/sentinel.error.log` and open referenced raw file to see exact LLM response
- CORS issues → all browser calls go via RPC proxies (generate/chat) to avoid CORS

## Next steps (urgent)
1. Confirm available models on delphi (`/test/ollama/`). Set `textModel` and `visionModel` to exactly installed names.
2. Improve title extraction (titles from pages 1–3; dehyphenate; expand alias list) and store canonical via UI action.
3. Add Sentinel model dropdown to Resources table header (done) with analysis per row; wire approval of canonical title (PG soon).
4. Plan Postgres schema for `documents`, `works` (book/chapter/article), `creators` (authors/editors), `evidence`, `jobs`.
5. Replace v1 search with OpenSearch per‑page index; keep v1 as small‑corpus fallback.

## Useful URLs
- Tags (server): `GET /rpc/ollama-tags.php`
- Generate proxy: `POST /rpc/ollama-generate.php` body `{model,prompt,format?,stream:false}`
- Chat proxy: `POST /rpc/ollama-chat.php` body `{model,prompt,system?,stream:false}`
- Sentinel extract: `POST /rpc/sentinel-extract.php` body `{filename,modelId}`

## Notes
- All errors should surface in‑page (no browser alerts) and be logged under `logs/`.
- Vision models require `pdftoppm` and may be slower; we cache PNG previews under `tmp/titlecache/`.
- Keep configs (`config/*.json`) out of web root; use RPC to expose necessary metadata only.



