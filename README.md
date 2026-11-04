# Iceberg Custom Elements

Custom Breakdance Element Studio locations are registered from `plugin.php` for
`elements/`, `macros/`, and `presets/`. The repository is the source of truth:
updating the plugin can overwrite site-local Element Studio edits.

## Updating

The `3.0.4` release checks `https://plugin.icebergwebdesign.com` for updates in the
WordPress Plugins dashboard. Auto-updates remain a per-site WordPress choice;
the plugin does not force them. The server supplies a signed package URL that
expires after 15 minutes, so the updater obtains a fresh signature immediately
before downloading. A failed refresh stops the update rather than retrying an
expired URL. WordPress 6.0 and PHP 8.0 are minimum declared versions.

### Breakdance CSS cache after upgrading from 3.0.0 or 3.0.1

Breakdance keeps generated per-page and per-template stylesheets. On sites that
saved Wrapper Div or Inner Section while 3.0.0 was installed, old broad CSS
selectors remain in those files **after the plugin update**. Back up the site,
then use **Breakdance → Settings → Tools → Regenerate CSS Cache** to rebuild the
generated stylesheets. Otherwise the original header/layout regression can
remain visible even though the installed plugin reports a newer version. The
same cache rebuild is needed for 3.0.2 to remove old Inner Section default
padding that would override the saved per-instance padding.

## Top bar migration in 3.0.4

The `topbar-hide.js` asset and its global enqueue from 3.0.3 have been removed.
Sites with `#iwd-top-bar` no longer receive the script or its `.hidden` toggle
from this plugin. Migrate each affected header to site-authored CSS (for example,
the Breakdance sticky-state `.top-bar` transition) **before** upgrading. Verify
the new top bar behavior at desktop/mobile widths and both scroll directions.
The old ID may remain for site-specific purposes, but it no longer triggers
plugin behavior. Site-authored copies of the script are not removed by this
plugin update. Sites with Auto-updates enabled can receive 3.0.4 before their
header is migrated; disable Auto-updates for this plugin on affected sites
until that migration is complete.

## First rollout from 2.3.1

Existing 2.3.1 installations do not contain this updater or an `Update URI`
header. Registering the 3.0.0 ZIP on the server **cannot** make those sites
discover it automatically. Each existing site needs a separately approved,
one-time manual install/update of the reviewed 3.0.0 ZIP (with a backup of
site-local Element Studio edits first). After that bootstrap, WordPress can
check this server and each site may enable per-plugin Auto-updates. No installed
WordPress site is changed by this repository or server registration.

## Release packaging

From the repository root (a Git checkout; only tracked runtime files enter the ZIP):

```sh
php -l plugin.php
php -l includes/UpdateService.php
php tests/updater-test.php all
python -m unittest discover -s tests -p 'test_release_package.py' -v
python scripts/build_release.py --output /absolute/private/path/iceberg-custom-elements.zip
```

Place the tested ZIP at
`/home/iceberg/private/plugins/iceberg-custom-elements/iceberg-custom-elements.zip`
and register the slug `iceberg-custom-elements` in the update server's **private**
configuration. Its metadata must match the ZIP (`current_version: 3.0.4`,
`requires: 6.0`, `requires_php: 8.0`, `requires_license: false`); `tested: 7.1.3`
is owner-reported, not an end-to-end verification by this project. Back up the
specific private config and target artifact outside the public web root before
server changes; verify their hashes and preserve other plugin registrations.
If deployment validation fails, restore only touched targets from that backup.
Never commit private config, secrets, signed URLs, or server archives to Git.
The CSS fix and a post-save custom-element smoke test were verified on
`layout17.icebergweb.dev` staging with Breakdance 3.0.1 on 2026-11-03.
Production installations and the private update-server package are separate
rollouts and are not changed by merging this repository.
