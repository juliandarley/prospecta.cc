# `Prōspecta`

### domains
prospecta.orcus.lan:35232 (LAN vhost on orcus)  
orcus.prospecta.cc 

### description 
Prōspecta is a PHP web application for research, citation, and company intelligence. It ingests Excel data, normalizes and deduplicates company names, and provides analysis and curation tools. 

orcus.prospecta.cc currently has none of the corporate or search functions see in the felix version, but these will be integrated once the citation/RAG part is working.

### proposed features (in progress):
- Cross‑document reference checking across PDFs/EPUBs/Web with page/section‑level evidence
- Evidence‑first RAG drafting for 1–2k word sections with verified citations (local LLM first, cloud optional)
- Hybrid search backend (OpenSearch BM25 + vectors) and Postgres metadata/graph‑lite
- Python service for ingest, embeddings, retrieval fusion, generation, and verification

## Purpose
Prospecta.cc is the new home for the Prospecta platform. It will host a clean Apache/PHP vhost on the LAN first, then be exposed publicly via the jumpbox. Over time, code and functionality from the current `felix.prospecta.cc` deployment will be integrated and refactored into this repository, preserving working features while improving structure (extensionless routes, clearer separation of public `html/` and operational folders).

- VHost on port 35232 at `prospecta.orcus.lan`
- DocumentRoot: `/var/www/prospecta.cc/html`
- Logs: `/var/www/prospecta.cc/logs/`
- PHP via php-fpm socket `/run/php/php8.3-fpm.sock`

## Structure
- `html/` public web root (index.php, health.php, reference-checking.php, `rpc/` APIs)
- `logs/` Apache logs
- `config/` vhost templates/snippets to apply under `/etc/apache2/sites-available/`
- `templates/` reverse proxy examples for jumpbox/public exposure

## Next steps
1. Reference checking UX
   - Add file/date filters and hit counts; link each hit to open PDF at the exact page (via pdf.js or native viewer with `#page=N`).
   - Return multiple occurrences per page with character offsets.
   - Improve normalization: dehyphenate across line breaks; fold smart quotes/dashes and accents.
2. Backend robustness
   - Add config file for paths and limits (`data/pdfs`, cache dir, max hits).
   - Better error reporting/logging in `/html/rpc/reference-search.php`.
   - Optional OCR fallback (ocrmypdf) for scanned PDFs before extraction.
3. Scale-out search (planned)
   - Introduce an ingest service (Python) to extract and normalize per-page text incrementally.
   - Index into OpenSearch (page docs) to support fast phrase/proximity, synonyms, and highlights.
   - Keep the current PHP scan as a dev/small-corpus mode.
4. Ops / deployment
   - Apply the vhost config below; ensure `rewrite` module is enabled and logs writable.
   - Use templates in `templates/` for reverse proxy and TLS on the jumpbox.
5. Git workflow
   - Continue work on `ref-checking` branch; when verified, open PR and merge to `main`.

## VHost config (copy for ref - apply to `/etc/apache2/sites-available/prospecta-cc.conf`)

Note:
- We keep the latest vhost template in `config/v2-apache-vhost.config` (current). It scopes pretty-URL rewrites inside the `<Directory>` block and guards them with `<IfModule mod_rewrite.c>` so Apache won’t fail if the module isn’t enabled. The older variant remains as `config/v1-apache-vhost.config` for reference.
```
<VirtualHost *:35232>
    ServerName prospecta.orcus.lan
    DocumentRoot /var/www/prospecta.cc/html
    DirectoryIndex index.php index.html

    <Directory /var/www/prospecta.cc/html>
        Options -Indexes +FollowSymLinks -MultiViews
        AllowOverride None
        Require all granted
    </Directory>

    # Pretty URLs
    RewriteEngine On

    # Don’t rewrite real files/dirs
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # Root and /index
    RewriteRule ^/?$ /index.php [L,QSA]
    RewriteRule ^/?index/?$ /index.php [L,QSA]

    # Whitelist routes → .php
    RewriteRule ^/(health|reference-checking)?$ /$1.php [L,QSA]

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog /var/www/prospecta.cc/logs/error.log
    CustomLog /var/www/prospecta.cc/logs/access.log combined
</VirtualHost>
```

## Repository snapshot (current)
```
prospecta.cc/
  config/
    copy-of-apache-vhost.config
  data/
    pdfs/
      <place PDFs here>
  html/
    index.php
    index.html
    health.php
    reference-checking.php
    rpc/
      reference-search.php
  logs/
    access.log
    error.log
  scripts/
    add-changelog-entry.sh
  templates/
    changelog.template.md
    chat-discussion.template.md
    commit.template
    felix-prospecta-proxy.conf
    felix-tunnel.conf
    orcus_prospecta-tunnel.service
    prospecta-apache.conf
  tmp/
    textcache/        (created at runtime for per-page cache)
  .gitignore
  CHANGELOG.md
  README.md
  .cursorrules
```
