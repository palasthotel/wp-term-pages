# Changelog

## [3.0.0](https://github.com/palasthotel/wp-term-pages/compare/v2.0.0...v3.0.0) (2026-10-14)


### ⚠ BREAKING CHANGES

* requires PHP 8.0 or newer

### Features

* drop support for PHP 7.4 ([cf43ed2](https://github.com/palasthotel/wp-term-pages/commit/cf43ed2cbb3f4c7cee4c4743ace2109a8b0c6596))


### Bug Fixes

* page search without jQuery UI, Plugin Check clean ([64b78b9](https://github.com/palasthotel/wp-term-pages/commit/64b78b9ec728101a0f60edd5bbcaeb2bcbda6f33))
* show special characters in page titles correctly in the page search ([b1d087e](https://github.com/palasthotel/wp-term-pages/commit/b1d087eb89c3fc74bafb4f32fed8a609c026b818))

## [2.0.0](https://github.com/palasthotel/term-pages/compare/v1.0.3...v2.0.0) (2026-07-31)


### ⚠ BREAKING CHANGES

* the unauthenticated wp_ajax_nopriv_tp_lookup endpoint has been removed, the page search now requires a logged-in user with the capability to edit terms of the taxonomy. The plugin requires WordPress 6.2 and PHP 7.4, and the bundled remove.js was replaced by admin.js.

### Bug Fixes

* prevent unauthenticated SQL injection in the page lookup (reported by Joao Ramos Maciel, WPScan) ([6c28285](https://github.com/palasthotel/term-pages/commit/6c282857df04a67d5ec3e6b4ced054c9d2c5d12e))
