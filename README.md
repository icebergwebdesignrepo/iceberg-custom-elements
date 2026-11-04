# Iceberg Custom Elements

Custom Breakdance Element Studio locations are registered from `plugin.php` for
`elements/`, `macros/`, and `presets/`. The repository is the source of truth:
updating the plugin can overwrite site-local Element Studio edits.

## Updating

The `3.0.2` release candidate checks `https://plugin.icebergwebdesign.com` for updates in the
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

## Top bar compatibility in 3.0.3

The legacy `js/topbar-hide.js` is included for headers containing `#iwd-top-bar`.
It adds the `hidden` class after scrolling past 100px and removes it only when
returning to the top. Existing sites still need their own CSS for the `hidden`
class; the script is a no-op without the ID. Sites using the newer `.top-bar`
CSS approach do not need to add the legacy ID. Migrate old headers site by site:
remove the ID (or script) before enabling the height-transition CSS, since
`display: none` from the legacy `hidden` rule would cut off the animation.

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
configuration. Its metadata must match the ZIP (`current_version: 3.0.2`,
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
