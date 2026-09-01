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
| Open offsite links in a new tab | 23 | Front-end JS: opens links to other domains in a new tab with `rel="noopener noreferrer"`. |
| Limit Editor uploads to 1 MB | 28 | Caps non-admin uploads at 1 MB and shows a note in the media uploader. |
| Site Admin Oxygen Builder access | — | Grants the "Site Admin" role "Edit Content Interface Only" access in the Oxygen Builder (edit page text/links/images, rearrange/duplicate elements; templates & global settings stay locked to admins). Writes the `oxygen_settings_permissions` option directly (v1.1.2+); revisit when O6 ships its official client-control feature. |

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
│   ├── media-handling.php          # 7 + 28
│   └── post-utilities.php          # 20 (transient-cached)
└── vendor/plugin-update-checker/   # YahnisElsts/plugin-update-checker v5.7
```

## How toggles work

Each include registers itself into a central registry at load time via
`curly_site_tools_register_toggle( $id, $label, $description, $default )` and
gates its hooks behind `curly_site_tools_is_enabled( $id )`. Enabled state is a
single autoloaded option (`curly_site_tools_enabled`) so it's one DB read.

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