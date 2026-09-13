---
name: caname-feature-testing
description: Run browser-based local end-to-end tests for the plain-PHP Caname estimate and execution-budget application.
---

# Local runtime
- Repo: `/home/ubuntu/caname_dev`. Plain PHP and MySQL; no Composer or Node build is required.
- Reuse an existing local PHP server when available, otherwise run `php -S 127.0.0.1:8088 -t src dev_router.php` from the repo root. The router is necessary for extensionless routes.
- Use the existing `src/config/config.local.php`; never print or commit DB credentials.
- Local URL: `http://127.0.0.1:8088/login`. Obtain current login credentials from the task or the lead; do not assume seeded passwords remain unchanged.
- If Japanese glyphs render as boxes, install `fonts-noto-cjk`. This is an environment font issue, not necessarily an application problem.
- For optional Python browser automation check `/usr/bin/python -c 'import playwright'` before installing anything; this environment already has Playwright for that interpreter. Interactive shells can resolve `python` to a different pyenv version. Connect to the existing browser via its configured CDP port rather than extracting session cookies.

# Feature testing
- Prefer a new or UI-copied project for destructive/boundary tests. The local seeded sample may be project2, with five sections and subtotal57,162,800; verify that baseline before relying on it.
- Estimate rows span a wide horizontally scrollable table. `.code` followed by Tab triggers master fetch; `.pick` opens the picker, `#pk-q` searches, `#pk-body tr` selects.
- Observe `/items/search` requests from real browser interactions. Do not replace them with mocked responses.
- Mark the row's `is_quote` checkbox when testing quote unit/amount; a master selection alone does not make a quote row.
- Compare immediate browser values to values after `明細を保存`; also exercise blank rows before a referenced merge target, as displayed row numbers can shift during persistence.
- Other works1–3 contribute to sales-budget cost; other works4 is explicitly excluded.
- A newly created user must change their initial password. For viewer tests use a disposable account and verify both omitted edit controls and denied POSTs with valid CSRF, sent inside that browser session.
- User UI has deactivation rather than deletion. Deactivate a test account before optional exact-match local DB cleanup. Never delete production/existing users.
- Keep sample/master business values unchanged; operation timestamps/audit entries may legitimately advance after save/revert.
- Inspect `src/storage/logs/php_error.log` before and after, plus browser uncaught errors and failed network responses.
- If user requests text-only evidence, do not start recording or generate screenshot deliverables; distinguish DOM/value checks from visual-layout or physical-print validation.

## Devin Secrets Needed
- None for the preconfigured local DB environment; current application login credentials must be supplied by the task/lead. External staging access requires separately authorized secrets.
