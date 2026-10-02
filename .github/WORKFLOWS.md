# CI/CD Workflows

The four workflows in `.github/workflows/` call the shared ones in
[palasthotel/github-workflows](https://github.com/palasthotel/github-workflows). How
they work, every input and what to do when a deploy fails is described there, in
[docs/wp-plugin.md](https://github.com/palasthotel/github-workflows/blob/main/docs/wp-plugin.md).

What is specific to this plugin:

| | |
|---|---|
| wordpress.org slug | `term-pages` |
| version file | `version.txt` (`release-type: simple`) - there is no `package.json` |
| build step | none - plain PHP and one script, `public/admin.js` |
| development wrapper | `plugin.php`, `Plugin Name: Term Pages - DEV` - the PR check fails if it ends up in the payload |
| SVN `assets/` | mirrored from `assets/` here with `--delete`, so this repository is the source of truth for the plugin page's icon and screenshots |
| SVN `trunk/` | up to 2.0.0 it still held `screenshot-1.png`; the screenshot now lives in `assets/`, and the next deploy removes it from `trunk/` |

### Required repository configuration

Set for the organization and released to this repository: the variable
`RELEASE_BOT_APP_ID` and the secrets `RELEASE_BOT_PRIVATE_KEY`, `SVN_USERNAME` and
`SVN_PASSWORD`. The variable `SVN_REPO_URL` is no longer read and can be deleted.

release-please never pushes to `main` - it opens a pull request - so a branch
ruleset on `main` needs no exception for the app. Add the app as a bypass actor only if
a **tag** ruleset restricts creating `v*` tags, a ruleset also covers the
`release-please--*` branches and forbids direct pushes, or signed commits are required.
