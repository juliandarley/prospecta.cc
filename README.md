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
- `html/` public web root
- `logs/` Apache logs

## Next steps
1. Add extensionless URLs in vhost (keep .php working)
2. Add `info.php` and `health.php`
3. Prepare public proxy + TLS

## VHost snippet
```
<VirtualHost *:35232>
    ServerName prospecta.orcus.lan
    DocumentRoot /var/www/prospecta.cc/html

    <Directory /var/www/prospecta.cc/html>
        Options FollowSymLinks -MultiViews
        AllowOverride None
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog /var/www/prospecta.cc/logs/error.log
    CustomLog /var/www/prospecta.cc/logs/access.log combined
</VirtualHost>
```
