# Changelog

## [Unreleased]

- Converted repository into a monorepo that contains both the JavaScript (npm) and PHP (Composer) implementations of funcystr.
- Moved the PHP composer.json to the repository root so the package can be published to Packagist.
- Updated Composer autoload configuration to load PHP code from php/src/ and tests from php/tests/.
- Added .gitattributes rules to exclude JS build and dev files from Composer distribution archives.
- Added npm "files" configuration and updated .npmignore to exclude PHP / Composer files from the npm package.
- Documented monorepo layout, PHP installation instructions, and development commands in README.md.


