# Changelog
All notable changes to this project will be documented in this file.

## 1.2.1 - 2024-02-01
### Fix
* fix: Remove `fully_qualified_strict_types` again by @nickvergessen in https://github.com/nextcloud/coding-standard/pull/16

## 1.2.0 - 2024-02-01
### Added
- `array_syntax`: Force short syntax for array
- `list_syntax`: Same for list
- ~~`fully_qualified_strict_types`: Remove namespace from classname when there is a `use` statement, and add missing backslash for global namespace~~ - Removed in 1.2.1 due to issues
- `no_leading_import_slash`: Remove leading slash from `use` statement
- `nullable_type_declaration_for_default_null_value`: Add missing `?` on type declaration for parameters defaulting to `null`. This will most likely be needed to avoid warnings in PHP 8.4.
- `yoda_style`: forbid yoda style comparision. This replaces `null === $a` by `$a === null`.

## 1.1.1 - 2023-06-23
### Changed
* feat: use php-cs-fixer/shim by @kesselb in https://github.com/nextcloud/coding-standard/pull/13

## 1.1.0 - 2023-04-13
### Changed
* Order imports alphabetically by @come-nc in https://github.com/nextcloud/coding-standard/pull/10
* fix(rules): Replace deprecated braces rules by @nickvergessen in https://github.com/nextcloud/coding-standard/pull/12

## 1.0.0 – 2021-11-10
### Breaking change
* Update php-cs-fixer to 3.x
* See https://github.com/nextcloud/coding-standard#upgrade-from-v0x-to-v10 for instructions.

## 0.5.0 – 2021-01-11
### Added
- New rule: short list syntax
- php7.2 support (back, for apps that support Nextclod 20 - 21)

## 0.4.0 – 2020-12-14
### Added
- php8 support
- New rule: binary operators should be surrounded by a single space
### Changed
- php-cs-fixer updated to the latest version
