# Iceberg Custom Elements 3.0.0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add safe WordPress-discoverable and optionally automatic updates for Iceberg Custom Elements 3.0.0 from the existing Iceberg update server, preserve Breakdance Element Studio, package a verified ZIP, and register only this plugin on the server.

**Architecture:** A self-contained updater class in the plugin repo follows the Iceberg Performance server request contract, but uses the server's signed `package` URL and refreshes it just before download because tokens expire after 900 seconds. Breakdance registration and element files remain unchanged. A deterministic ZIP build precedes a narrowly backed-up registration in the server's private config; no production WordPress plugin is installed or updated.

**Tech Stack:** WordPress PHP 8.0+, WordPress 6.0+, existing custom PHP update API, Breakdance Element Studio, standalone PHP test harness, Python 3 stdlib ZIP builder, GitHub PR, SSH read-only preflight/scoped write.

**Spec:** `docs/superpowers/specs/2026-10-23-custom-elements-updates-design.md`

## Global Constraints

- Installed identity: `wp-content/plugins/iceberg-custom-elements/plugin.php`; WordPress/server version `3.0.0`, future tag `v3.0.0` only after separate approval.
- Preserve namespace `IcebergCustomElements`, `elements/`, `macros/`, `presets/`, element IDs/classes, `registerSaveLocation()`, and `breakdance_loaded` priority `9`. No forced auto-update setting.
- Minimum metadata matches Iceberg Performance: WordPress `6.0`, PHP `8.0`. Server `tested` metadata is WordPress `7.1.3`, the version the owner reports testing. Use only `https://plugin.icebergwebdesign.com` for update and package traffic; never embed credentials.
- Production server currently has no registration or ZIP for this slug. Authorized writes are limited to `/home/iceberg/private/config.php` and `/home/iceberg/private/plugins/iceberg-custom-elements/iceberg-custom-elements.zip`, with a targeted backup outside the public web root. Do not modify installed WordPress sites or merge `main`.
- `php.exe` in WSL runs PHP 8.5.1 and can read this repository via `wslpath -w`; Docker is unavailable. PHP 8.5 tests do not prove compatibility with the declared PHP 8.0 floor or a running Breakdance site. Report this limitation explicitly.
- Delegate bounded plugin/test/build coding to the available local `qwen3-coder:30b` worker when feasible; the cloud orchestrator reviews the diff, runs tests independently, and exclusively handles the production SSH boundary. Do not pass GitHub/SSH credentials to the local worker.

## Review Focus

- A stale 900-second signed URL in a 12-hour WordPress update transient must be refreshed for the target plugin at install time (Task 2 test: old signed URL versus newly downloaded URL).
- Bulk upgrades have `hook_extra['plugin']` but may omit `type`; the target must still refresh and an unrelated plugin must not (Task 2 test).
- Malformed/missing update transient fields and invalid/missing/server-foreign `package` URLs must fail without advertising a broken update (Task 1 test).
- Breakdance inactive or unavailable must not prevent updater hook registration; existing Element Studio save paths/priority must not change (Task 1 test).
- A corrupt/missing ZIP or registration failure must not alter the other two plugin records; restore only this deployment's touched files (Task 5 probe/rollback test).

---

### Task 1: Plugin identity and update discovery

**Files:**
- Create: `includes/UpdateService.php`
- Modify: `plugin.php` (header + independent updater bootstrap only)
- Create: `tests/updater-test.php`

**Interfaces:**
- Produces `IcebergCustomElements\UpdateService::__construct(string $plugin_file, string $current_version, string $server_url)`; `check_for_updates($transient)`; `plugin_info($result, $action, $args)`; `clear_cache($upgrader, $options)`.
- Later Task 2 uses `private fetch_update_data(): array|false` and `private is_trusted_package_url(string $url): bool` from this class. The HTTP response must be an array containing `new_version` and a signed `package` URL. Trust only HTTPS host `plugin.icebergwebdesign.com`, action `download`, and plugin `iceberg-custom-elements`; allow other server query parameters including a token, but never log them.
- Cache key unique to this plugin; cache TTL 43200 seconds; valid update data only. `plugin_basename` response key is `iceberg-custom-elements/plugin.php`.

- [ ] **Step 1: Write the failing `tests/updater-test.php` discovery group.** Stub `add_filter`, `add_action`, `plugin_basename`, `wp_remote_get`, URL helpers, transients, and `WP_Error`. Assert header `Version: 3.0.0`, `Requires at least: 6.0`, `Requires PHP: 8.0`; preserve `breakdance_loaded` priority 9 and the three save paths. Assert updater hooks register without Breakdance and response key/slug/version/package on mocked 2.3.1→3.0.0; equal/older version and malformed transient leave response untouched. Add cases for missing package, HTTP failure, invalid JSON, HTTP package URL, wrong host, and wrong plugin slug. Assert `plugin_info()` handles only its slug/action; `clear_cache()` ignores unrelated upgrades; no auto-update-forcing hook is registered.
  Core assertions (with harness `same()`/`true()` helpers and mocked `$signed_url`):
  ```php
  same('iceberg-custom-elements', $offer->slug);
  same('iceberg-custom-elements/plugin.php', $offer->plugin);
  same('3.0.0', $offer->new_version);
  same($signed_url, $offer->package);
  true(!isset($unchanged->response['iceberg-custom-elements/plugin.php']));
  ```
- [ ] **Step 2: Run the red tests.** `php.exe -f "$(wslpath -w "$PWD/tests/updater-test.php")" -- discovery` (from repo root); expected FAIL due to missing updater/header.
- [ ] **Step 3: Implement the minimal class and bootstrap.** Add `Update URI: https://plugin.icebergwebdesign.com/iceberg-custom-elements` only if source-level WordPress hook behavior still permits the existing transient filter; do not change Breakdance callback or elements. Send `action=version_check`, `plugin=iceberg-custom-elements`, `version=3.0.0`, `site_url`, `wp_version`, `php_version`; use `wp_remote_get()` and fail closed on non-200/invalid data. Use the exact server-returned signed URL in the update response. Implement plugin info only for `plugin_information` and this slug, plus cache invalidation only on this plugin's upgrade. Avoid copying licensing/UI features from Iceberg AddOns.
- [ ] **Step 4: Run green tests and lint.** Same discovery command must PASS; `php.exe -l "$(wslpath -w "$PWD/plugin.php")"` and lint the new class via the same UNC conversion, both PASS.
- [ ] **Step 5: Commit.** `git add plugin.php includes/UpdateService.php tests/updater-test.php && git commit -m "feat: discover custom elements updates from Iceberg server"`.

### Task 2: Refresh expiring signed package at upgrade time

**Files:**
- Modify: `includes/UpdateService.php`
- Modify: `tests/updater-test.php`

**Interfaces:**
- Produces `refresh_package_download($reply, $package, $upgrader, $hook_extra)` for WordPress `upgrader_pre_download` (four args). Return the original `$reply` for non-target plugins or a preexisting short-circuit; return a downloaded temporary ZIP path for the target; return `WP_Error` on refresh/download failure. Register it on `upgrader_pre_download` with priority 10 and four accepted arguments.
- Calls Task 1's `fetch_update_data()` *without the 12-hour cache* to get a fresh signed `package`, validates it with `is_trusted_package_url()`, then calls WordPress `download_url()` (the same package-download function used by `WP_Upgrader`). Guard using `hook_extra['plugin'] === 'iceberg-custom-elements/plugin.php'` and the original package URL belonging to this server/plugin. Both single and bulk upgrade paths include this plugin key in WordPress core; a bulk path need not include `hook_extra['type']`.

- [ ] **Step 1: Write the failing refresh group.** Assert an advertised URL whose token has expired is replaced by a fresh `version_check` response before `download_url()`; test target single and bulk contexts, unrelated plugins, existing `$reply`, malformed hook args, refresh HTTP failure, foreign/invalid fresh package, and download failure. Failed refresh must not attempt the expired original URL or leak its query string to logs.
  Core assertions (with mocked `$fresh_url` and `$temp_zip`):
  ```php
  same($temp_zip, $service->refresh_package_download(false, $expired_url, $upgrader, ['plugin' => 'iceberg-custom-elements/plugin.php']));
  same($fresh_url, $download_url_called_with);
  same(false, $service->refresh_package_download(false, $other_url, $upgrader, ['plugin' => 'other/plugin.php']));
  true($failed_refresh instanceof WP_Error);
  ```
- [ ] **Step 2: Run the red tests.** `php.exe -f "$(wslpath -w "$PWD/tests/updater-test.php")" -- refresh`; expected FAIL because the hook/handler is absent.
- [ ] **Step 3: Implement `refresh_package_download()` and register its hook.** Pass through non-target/short-circuited calls; fetch fresh data without cached transients; only return the temporary file path after a successful, validated `download_url()`. Abort with a specific `WP_Error` if no safe package can be downloaded. Do not change any global auto-update option.
- [ ] **Step 4: Run all harness tests and PHP lint.** `php.exe -f "$(wslpath -w "$PWD/tests/updater-test.php")" -- all`; expected PASS, including discovery. Lint `plugin.php` and `includes/UpdateService.php`.
- [ ] **Step 5: Commit.** `git add includes/UpdateService.php tests/updater-test.php && git commit -m "fix: refresh signed update URL before plugin download"`.

### Task 3: Reproducible release ZIP and deployment instructions

**Files:**
- Create: `scripts/build_release.py`
- Create: `tests/test_release_package.py`
- Modify: `README.md` (short release/server registration guide)
- Modify: `CHANGELOG.md` (3.0.0 entry)

**Interfaces:**
- Produces `python scripts/build_release.py --output <absolute-zip-path>`, with one root `iceberg-custom-elements/` and a stable sorted manifest from `plugin.php`, `elements/`, runtime `includes/`, `assets/`, `LICENSE`, `README.md`, `CHANGELOG.md`. No `.git`, `docs/`, `tests/`, `scripts/`, or secrets in the ZIP. Use deterministic ZIP metadata so identical inputs yield identical SHA-256.

- [ ] **Step 1: Write failing Python archive tests.** Run the builder against a temporary path; assert ZIP root/name, `plugin.php` header version `3.0.0`, original two element directories and required assets, exclusion list, and byte-identical repeat build. Assert an unrelated/empty output path does not overwrite source files.
  Core assertions:
  ```python
  self.assertIn('iceberg-custom-elements/plugin.php', archive.namelist())
  self.assertTrue(all(p.startswith('iceberg-custom-elements/') for p in archive.namelist()))
  self.assertEqual(hashlib.sha256(first.read_bytes()).digest(), hashlib.sha256(second.read_bytes()).digest())
  ```
- [ ] **Step 2: Run the red tests.** `python -m unittest discover -s tests -p 'test_release_package.py' -v`; expected FAIL due to missing builder.
- [ ] **Step 3: Implement the allowlisted ZIP builder and concise docs.** README lists server registration slug/ZIP path, signed URL refresh rationale, commands, and production-write/rollback boundary. Changelog records this feature without claiming deployment/Element Studio smoke verification.
- [ ] **Step 4: Run the archive and PHP tests, then build a real scratch artifact.** Both unit test commands PASS; build outside the repo under `$HERMES_HOME/cache/scratch/iceberg-custom-elements-v3.0.0.zip`. Inspect ZIP manifest and SHA-256 with tools; verify no installed-site change.
- [ ] **Step 5: Commit.** `git add scripts/build_release.py tests/test_release_package.py README.md CHANGELOG.md && git commit -m "build: package custom elements 3.0.0 for update server"`.

### Task 4: Review plugin branch and open a draft PR

**Files:**
- None beyond Tasks 1–3.

**Interfaces:**
- Produces a draft PR from `feat/custom-elements-updates-v3.0.0` to `main`; no merge or GitHub Release.

- [ ] **Step 1: Inspect the complete diff** against `origin/main`; assert no changes in `elements/` or the Element Studio registration callback, and no credentials/ZIP binaries in Git. Review PHP handling of server URL and stale signed token.
- [ ] **Step 2: Run the exact full test suite and `git diff --check`.** If a test fails, fix before pushing and rerun. Document lack of a real Breakdance/WordPress staging smoke test and absence of PHP 8.0 runtime if not provided.
- [ ] **Step 3: Push the isolated branch and create a draft PR.** Set base/head/title/body explicitly, state tests and known limitations; read back the exact remote head SHA, draft state, changed files, and checks. Do not merge.

### Task 5: Scoped, backed-up registration on the production update server

**Files:**
- Server write: `/home/iceberg/private/config.php` (add only `iceberg-custom-elements` registry entry)
- Server create: `/home/iceberg/private/plugins/iceberg-custom-elements/iceberg-custom-elements.zip`
- Server backup: timestamped copies/manifest under a private, non-web-readable backup directory, with recorded hashes and permissions; never in the public web root.

**Interfaces:**
- Uses Task 3's verified ZIP SHA-256 and Task 4's reviewed plugin commit. Metadata: slug `iceberg-custom-elements`, current_version `3.0.0`, requires `6.0`, requires_php `8.0`, tested `7.1.3` (owner-reported), requires_license `false`; use existing server schema for name/author/info fields. ZIP path is dictated by `PluginRepository::zip_path()` as `<PLUGINS_DIR>/<slug>/<slug>.zip`.
- Produces a server-side registered slug and verifiable `version_check`, `plugin_info`, and signed `download` responses; no site-level auto-update claim.

- [ ] **Step 1: Re-verify SSH identity and current server state read-only.** Pinned ED25519 host fingerprint must match `SHA256:ycrYO378QOQFNwJFmUz/sL45T4Eh1q+stb0Ag6LXbpo`, key account `iceberg`; confirm target entry and ZIP are absent, server version/config unchanged, enough space, and the other two slugs still registered. If any precondition differs, STOP and re-plan.
- [ ] **Step 2: Create a targeted backup and validate it.** Save original private config, record hash/mode/owner and target ZIP absence in a private manifest, and verify saved config hashes match. Do not copy secrets into Git, tool output, or chat. Confirm backup restore path before editing.
- [ ] **Step 3: Stage and validate only the two target files.** Insert a single `iceberg-custom-elements` entry with the agreed metadata; preserve the other entries exactly. Transfer the tested ZIP into the new private slug directory using a temporary file, compare SHA-256 to local artifact, check archive paths/permissions, and lint staged config with the server PHP binary. Atomically move validated files into place; do not touch public `index.php`, libraries, or installed WordPress sites.
- [ ] **Step 4: Read back the exact targets and exercise server actions.** Confirm new slug registration, config hash/expected diff, preserved old entries, ZIP hash/manifest, and actual `version_check?plugin=iceberg-custom-elements&version=2.3.1`, `plugin_info`, and a signed ZIP download in memory; check HTTP status/returned fields and ZIP hash without printing token URLs, secrets, or raw config. If any check fails, restore config backup and remove only the new slug ZIP/directory (after confirming it did not preexist), then recheck prior state.
- [ ] **Step 5: Report exact verification boundary.** Registered server update is not proof that a site automatically upgraded. Do not install on a production WordPress site or merge the draft PR; separately request a staging smoke test to establish end-to-end Breakdance compatibility.
