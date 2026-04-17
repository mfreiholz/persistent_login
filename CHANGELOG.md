# Changelog

All notable changes to `persistent_login` should be documented in this file.

## [Unreleased]

- Ongoing development builds use `persistent_login::PLUGIN_VERSION` with a `+dev` suffix until the next release is cut.

## [1.0.2] - 2026-04-11

- Formalized the plugin's self-metadata through `persistent_login::PLUGIN_VERSION` and `persistent_login::info()`.
- Aligned self-versioning with a cleaner release workflow while keeping existing plugin behavior intact.
