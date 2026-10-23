# Iceberg Custom Elements

Custom Breakdance Element Studio locations are registered from `plugin.php` for
`elements/`, `macros/`, and `presets/`. The repository is the source of truth:
updating the plugin can overwrite site-local Element Studio edits.

## Updating

Version `3.0.0` checks `https://plugin.icebergwebdesign.com` for updates in the
WordPress Plugins dashboard. Auto-updates remain a per-site WordPress choice;
the plugin does not force them. The server supplies a signed package URL that
expires after 15 minutes, so the updater obtains a fresh signature immediately
before downloading. A failed refresh stops the update rather than retrying an
expired URL. WordPress 6.0 and PHP 8.0 are minimum declared versions.

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
configuration. Its metadata must match the ZIP (`current_version: 3.0.0`,
`requires: 6.0`, `requires_php: 8.0`, `requires_license: false`); `tested: 7.1.3`
is owner-reported, not an end-to-end verification by this project. Back up the
specific private config and target artifact outside the public web root before
server changes; verify their hashes and preserve other plugin registrations.
If deployment validation fails, restore only touched targets from that backup.
Never commit private config, secrets, signed URLs, or server archives to Git.
A staging WordPress + Breakdance smoke test is still needed before rolling out
installed sites.
