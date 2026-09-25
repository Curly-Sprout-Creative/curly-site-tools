# Curly Site Tools

A set of small site-level hardening and behavior toggles for Curly Sprout sites.
Each change is controlled by an Admin under **Tools > Curly Site Tools**. All
toggles default to **enabled**, so migrating a site that previously ran these as
Fluent Snippets preserves its current behavior; uncheck any you don't want.

This is the migration of the old Fluent Snippets set into a standalone plugin so
updates can be distributed via GitHub Releases (see "Updates" below).

## Toggles

| Toggle | Maps to (old snippet) | What it does |
|---|---|---|
| Site Admin role & enforcement | 1 + 19 | Creates a limited "Site Admin" role (Editor + non-admin user management + AI1WM export + Menus access) and blocks Site Admins from editing Administrators. The role is created **once on plugin activation**, not on every page load. |
| Disable Gutenberg | 5 | Forces the Classic editor instead of the block editor. |
| Disable automatic update emails | 6 | Stops core/plugin/theme auto-update notification emails. |
| Redirect attachment pages | 7 | 301-redirects bare attachment URLs to their parent post (or home when no parent). |
| Completely disable comments | 9 | Removes comments/trackbacks everywhere (admin, menus, post-type support, front end). |
| iOS background-attachment fix | 16 | On iOS, swaps `.fixed-bg` → `.scroll-bg` (parallax fix). |
| Count posts in the past 3 months | 20 | `get_3month_post_count()` — posts in the last 3 months OR sticky posts. **Result is cached in a transient for 6 hours** so the `posts_per_page=-1` query doesn't run on every page load. |
| Open offsite links in a new tab | 23 | Front-end JS: opens links to other domains in a new tab with `rel="noopener noreferrer"`. Overrides an explicit `target="_self"` (as Oxygen/Breakdance render it) and watches for links added after load. |
| Limit Editor uploads | 28 | Caps non-admin uploads at a configurable size (default 1 MB, adjustable in 1 MB increments) and shows a note in the media uploader. |
| Site Admin Oxygen Builder access | — | Grants the "Site Admin" role "Edit Content Interface Only" access in the Oxygen Builder (edit page text/links/images, rearrange/duplicate elements; templates & global settings stay locked to admins). Writes the `oxygen_settings_permissions` option directly (v1.1.2+); revisit when O6 ships its official client-control feature. |
| Cloudflare Turnstile on forms | — | Replaces Breakdance's Google reCAPTCHA with Cloudflare Turnstile. Prints the widget inside every Breakdance/Oxygen form, verifies the token server-side on submit, and stops Breakdance loading the reCAPTCHA script. Requires a Site Key + Secret Key below. No-op when the keys are blank or on non-Oxygen sites. |
| Host Google Fonts locally | — | Downloads the Google Fonts CSS and its woff2 files into `uploads/curly-site-tools/fonts/` and serves them from this site, so visitors never contact `fonts.googleapis.com`. Builds automatically when the settings page is opened; re-run from **Tools > Curly Site Tools → Fetch / refresh fonts**. |

### Not a toggle

The **Site Admin role creation** runs once during plugin activation and is not
toggleable — it must exist before the enforcement logic can apply. Use the
enforcement toggle to control whether the role's restrictions are active.

## Structure

```
curly-site-tools/
├── curly-site-tools.php        # Headers + PUC bootstrap + main class loader
├── uninstall.php               # Removes role + options on delete
├── assets/js/                  # Front-end scripts (16, 23), enqueued when enabled
├── includes/                   # One file per concern
│   ├── class-curly-site-tools.php  # Toggle registry + Tools admin page
│   ├── admin-roles.php             # 1 + 19
│   ├── disable-comments.php        # 9
│   ├── disable-gutenberg.php       # 5
│   ├── disable-update-emails.php   # 6
│   ├── local-google-fonts.php      # Self-host Google Fonts + refresh action
│   ├── media-handling.php          # 7 + 28
│   ├── post-utilities.php          # 20 (transient-cached)
│   └── turnstile.php               # Cloudflare Turnstile for Breakdance forms
└── vendor/plugin-update-checker/   # YahnisElsts/plugin-update-checker v5.7
```

## How toggles work

Each include registers itself into a central registry at load time via
`curly_site_tools_register_toggle( $id, $label, $description, $default, $args )`
and gates its hooks behind `curly_site_tools_is_enabled( $id )`. Enabled state is
a single autoloaded option (`curly_site_tools_enabled`) so it's one DB read.

A toggle may pass `$args['fields']` — an array of companion inputs — each with
`type` (`number` | `text` | `password`), `label`, `option`, `placeholder`,
`description`, `min`, `step`, `unit`, and `default`. Each field is stored in its
own option. The legacy single `$args['field']` (numeric, used by the upload
limit) is still supported and treated as one number field.

## Configuring Turnstile + local fonts (WP-CLI)

```bash
# Cloudflare Turnstile keys (create a widget at the Cloudflare dashboard → Turnstile)
wp option update curly_site_tools_turnstile_site_key '0x4AAAAAAA...'
wp option update curly_site_tools_turnstile_secret_key '0x4AAAAAAA...'

# Enable the toggles (merge into the existing enabled array)
wp option patch update curly_site_tools_enabled turnstile 1
wp option patch update curly_site_tools_enabled local_google_fonts 1

# Build/refresh the local fonts without opening the admin page
wp eval 'curly_site_tools_localize_google_fonts();'
```

### How the build finds the Google Fonts stylesheet URL

The build needs the site's real `fonts.googleapis.com/css2?...` URL (it encodes
the exact family/weight set the theme requests). It is discovered in three ways,
in order:

1. **Already stored** — any front-end request with the toggle on stores the URL
   via the `breakdance_google_fonts_url` filter, and the build reads it.
2. **Loopback probe** — if nothing is stored, the build requests the site's home
   page once (with a `curly_fonts_probe` query string) and picks up the URL the
   filter stores during that request.
3. **HTML parse fallback** — if that loopback is answered from a full-page cache
   (LiteSpeed/Cloudflare) and the filter therefore never runs, the URL is parsed
   out of the returned HTML's Google Fonts `<link>`.

Because of step 3 the auto-build succeeds on the first settings-page open even
on a fully cached site. If it ever still comes up empty (e.g. the cached HTML
predates the toggle), the fallback is manual: visit any front-end page once
(with the toggle enabled) and click **Fetch / refresh fonts**.

## Installation

1. Upload the plugin to `wp-content/plugins/` (or install the ZIP from the
   GitHub release).
2. Activate — this creates the Site Admin role once and seeds the toggle
   defaults.
3. Go to **Tools > Curly Site Tools** and enable the changes you want.
4. Click **Save Changes**.

## Updates

Updates are distributed through **GitHub Releases** using
[plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker).
The repository is **public**, so no tokens are required.

To ship a new version:

1. Bump the `Version:` header in `curly-site-tools.php`.
2. Tag the release: `git tag v1.0.1 && git push origin v1.0.1`.
3. The GitHub Action builds `curly-site-tools.zip` and attaches it to a release.
4. Each installed site shows the update in **wp-admin > Updates** and installs it
   through the standard WordPress flow.

## Replacing Fluent Snippets

On sites that previously ran these as Fluent Snippets, deactivate/delete the
matching snippets in **Fluent Snippets** when enabling the plugin so the hooks
don't double-fire (e.g. duplicate redirects, role churn).

## Development

- PHP: all files must pass `php -l`.
- JS: assets must pass `node --check`.
- The vendored `plugin-update-checker/` is committed (standard PUC practice);
  do not run `composer` on it.