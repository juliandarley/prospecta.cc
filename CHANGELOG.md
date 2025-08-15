# Changelog for `Prōspecta.cc`

#### 2025-08-15 11:44 UTC (+00:00) [20ed654] [ref-checking]

##### [SNC] Resources: secure downloads and robust uploads

- UI: resources.php links now proxy via /rpc/resources-download.php (no direct file exposure)

- API: add resources-download.php with range support and filename validation

- API: resources-upload.php improved error reporting (size limits, tmp errors) and SHA256 handling

- API: resources-list.php shows pages/dups and sizes



#### 2025-08-14 13:30 UTC (+00:00) [22cf95d] [ref-checking]

##### copy and rename `reference-checking` to `ref-check-pdftotext.php` as backup ahead of major changes





#### 2025-08-14 13:07 UTC (+00:00) [b17c7e0] [ref-checking]

##### updated vhost.config shadow





#### 2025-08-14 10:09 UTC (+00:00) [a7a0ef1] [ref-checking]

##### update readme





#### 2025-08-14 09:53 UTC (+00:00) [8059ba5] [ref-checking]

##### add `templates/` from `prospecta`





#### 2025-08-14 09:52 UTC (+00:00) [e040cd9] [ref-checking]

##### [SNC] Reference checking alpha version - not OpenSearch (LAN)

- UI: add html/reference-checking.php (AJAX search)

- API: add html/rpc/reference-search.php with per-page pdftotext extraction + cache

- Health: add html/health.php (JSON)

- Landing: add html/index.php

- Docs: update README; add CHANGELOG header; add vhost template under config/

- Build: add .gitignore and scripts/add-changelog-entry.sh



#### 2025-08-13 17:36 UTC (+00:00) [cee9d3b] [main]

##### [SNC] Add app skeleton pages and stub API

- Landing: add html/index.php with basic nav

- Health: add html/health.php (JSON status)

- Reference Checking: add html/reference-checking.php (AJAX UI)

- API: add html/rpc/reference-search.php stub (JSON)

- Docs: expand README; add vhost snippet under config/; init CHANGELOG header

- Scripts: add scripts/add-changelog-entry.sh (uses commit timestamp + timezone)



#### 2025-08-13 16:25 UTC (+00:00) [7326dfe] [main]

##### [SNC] Add changelog and first entry

- Insert latest commit with local timezone/offset



#### 2025-08-13 16:01 UTC (+00:00) [d9e78e7] [main]

##### [NSC] Initialise Prospecta.cc vhost skeleton

- Create html/ root with index page

- Add README with purpose and future integration from felix.prospecta.cc

- Add .gitignore to exclude logs/


All notable changes to this project will be documented here.




