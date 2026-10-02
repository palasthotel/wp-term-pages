# WordPress Term Pages

WordPress Plugin for overwriting the first page of a term archive with a single page.

If you want to customize your taxonomy archive pages, create a page and connect
it with the term you want to overwrite. Visitors hitting the term archive get a
301 redirect to that page — paginated archive pages (`/page/2`) are untouched.

- **WordPress.org:** https://wordpress.org/plugins/term-pages/
- **User documentation:** [public/readme.txt](public/readme.txt) (the text shown on WordPress.org)
- **Changelog:** [CHANGELOG.md](CHANGELOG.md) — release-please owns that file, so do
  not add notes to it by hand. Entries before 2.0.0 are in the `== Changelog ==`
  section of [public/readme.txt](public/readme.txt).

## Installation

Install *Term Pages* from the WordPress plugin directory, or download
`term-pages.zip` from the [latest release](https://github.com/palasthotel/wp-term-pages/releases/latest)
and extract it into `wp-content/plugins/`.

## Usage

1. Create and publish the page that should replace the archive.
2. Edit the term (category, tag or any custom taxonomy term).
3. Type the page title into the **overriding page** field and pick it from the
   autocomplete suggestions.
4. Save the term.

## Repository layout

`public/` is exactly what ships to WordPress.org. Everything outside it is
repository-only.

| Path | Description |
|---|---|
| `public/term-pages.php` | the plugin |
| `public/admin.js` | page autocomplete on the term screens |
| `public/languages/` | translations (`de_DE` + `.pot`) |
| `public/readme.txt` | WordPress.org plugin page |
| `public/LICENSE` | GPL-3.0 text, shipped with the plugin |
| `assets/` | media for the WordPress.org plugin page — not part of the download |
| `plugin.php` | loads `public/term-pages.php` when the repository itself is checked out into `wp-content/plugins/` |
| `LICENSE` | copy of the license text so GitHub detects it |
| `.github/workflows/` | CI/CD — see [.github/WORKFLOWS.md](.github/WORKFLOWS.md) |

### `assets/`

The release mirrors this directory into the `assets/` directory of the
WordPress.org SVN repository, which sits next to `trunk/` and is served on the
plugin page only — nothing in here is downloaded by users. WordPress.org
recognises the files by name:

| File | Purpose |
|---|---|
| `screenshot-1.png`, `screenshot-2.png`, … | screenshots; the number picks the caption from `== Screenshots ==` in `readme.txt` |
| `banner-772x250.png`, `banner-1544x500.png` | header image on the plugin page (the second one for retina) |
| `icon-128x128.png`, `icon-256x256.png` or `icon.svg` | icon in the plugin search and installer |

Localised variants use a locale suffix (`screenshot-1-de_DE.png`), a right-to-left
banner uses `-rtl`. The repository is the source of truth: files removed here are
removed from SVN on the next release. Since the mirror only runs on a release,
changing a banner or icon needs a release to go live.

## Releasing

Releases are automated with [release-please](https://github.com/googleapis/release-please)
and deployed to the WordPress.org SVN repository. There is nothing to bump by
hand — commit with [conventional commits](https://www.conventionalcommits.org/)
and merge the release PR:

```
fix: …   → patch    feat: …  → minor    feat!: … → major
```

```
merge PR to main → release-please opens "chore(main): release x.y.z"
                 → merge it → tag vx.y.z → deploy to WordPress.org
```

The full pipeline, including the required secrets, is documented in
[.github/WORKFLOWS.md](.github/WORKFLOWS.md). See [CONTRIBUTING.md](CONTRIBUTING.md)
for the commit conventions.

## Local development

There is nothing to build. The release packs `public/` with the shared script from
[palasthotel/github-workflows](https://github.com/palasthotel/github-workflows); with
that repository checked out next to this one:

```sh
SLUG=term-pages bash ../github-workflows/wp-plugin/bin/pack.sh    # → term-pages.zip + build/term-pages/
```

For a local WordPress, wp-env runs without a configuration file and mounts the
repository as the plugin (via `plugin.php`):

```sh
npx @wordpress/env start      # http://localhost:8888, admin / password
```

## License

GNU General Public License v3.0 or later — see [LICENSE](LICENSE).
