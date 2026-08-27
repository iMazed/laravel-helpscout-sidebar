# Changelog

All notable changes to `laravel-helpscout-sidebar` are documented here. The
format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] - 2026-08-27

Initial release.

- Signed-callback verification, fail-closed, with a trusted/untrusted
  parameter split that keeps unsigned input out of customer resolution.
- Server-side customer resolution through the Help Scout Mailbox API, with
  per-customer caching and a locked, cached token lifecycle.
- Config-driven sections (rows, metrics, badges, notes, links, lists) plus
  a `BuildsSidebar` contract for anything richer.
- Configurable header links with placeholder filling and logged drops.
- A pinned, inlined build of the Help Scout JavaScript SDK for iframe
  sizing: no third-party request at render time.
- A diagnostics screen explaining why nothing matched, gated behind
  `HELPSCOUT_SIDEBAR_DEBUG`.
