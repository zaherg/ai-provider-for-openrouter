# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
## [Unreleased]

## [0.1.0] - 2026-03-02

### Added

- Added `DISCLAIMER.md` documenting AI-assisted modifications and the derivative-package status.
- Added support for overriding the OpenRouter API base URL via the `OPENROUTER_BASE_URL` constant (for example in `wp-config.php`).

### Changed

- Renamed the provider namespace prefix from `WordPress\OpenRouterAiProvider` to `Zaherg\OpenRouterAiProvider`.
- Updated documentation examples to use `wp-config.php` constants (`OPENROUTER_API_KEY` and optional `OPENROUTER_BASE_URL`) instead of `putenv(...)`.
- Removed repository packaging assets that are not needed for this fork (`.github/` workflows and `.wordpress-org/` assets).

### Fixed

- Added `.DS_Store` to `.gitignore`.
- Clarified README/readme attribution to state this package is based on the WordPress OpenAI provider package.

### Added

- Initial OpenRouter provider package for the WordPress PHP AI Client SDK, based on the WordPress AI Provider for OpenAI package.
- OpenRouter text generation support using the OpenAI-compatible `/responses` endpoint.
- OpenRouter model discovery and metadata parsing using the `/models` endpoint.
- WordPress plugin bootstrap and Composer package support for distribution.

[Unreleased]: https://github.com/zaherg/ai-provider-for-openrouter/compare/8b8d7da...HEAD
[0.1.0]: https://github.com/zaherg/ai-provider-for-openrouter/commit/8b8d7da
