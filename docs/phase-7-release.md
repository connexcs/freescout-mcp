# Phase 7 release qualification

Date: 2026-09-01

## Outcome

Version `1.0.0` is the first stable release. The original Phase 8 OAuth deliverables were completed earlier, so completing this documentation and packaging phase also satisfies the first-release definition of done.

## Deliverables

- Installation, upgrade, rollback, uninstall, ownership, migration, and verification instructions
- Complete environment and administrator-policy reference
- Personal-token walkthroughs and environment-backed Codex and Claude Code configurations
- OAuth configuration for Codex and scope-pinned Claude Code clients
- Nginx and Apache reverse-proxy notes, including Host, Authorization, MCP headers, body limits, caching, and CORS
- Consolidated tool, scope, mutation-gate, and optional Knowledge Base reference
- HTTP, client, OAuth, package, and safe-diagnostics troubleshooting
- Changelog, release checklist, production archive validation, SHA-256 generation, and CI artifact packaging

## Qualification

- `composer check`: PHP syntax, 73 tests with 193 assertions, dependency audit, documentation/version validation, client fixtures, and stateless protocol smoke passed.
- `composer interop-inspector`: the pinned Inspector 2.4.0 limitation remains explicitly detected; no legacy lifecycle was added.
- `scripts/build-release.sh`: production-only Composer install, archive layout, version, autoload, and checksum generation passed.
- `scripts/freescout-package-smoke.sh`: the extracted archive passed the HTTP adapter and stateless protocol checks inside fresh FreeScout commit `4080415870a979984b33bdce6a1b7848f17cadd9`.
- The CI matrix retains PHP 8.1, 8.2, 8.3, and 8.4, with separate Inspector and packaged-release jobs.

The client examples were checked against the current [OpenAI Codex MCP documentation](https://developers.openai.com/codex/mcp/), [Claude Code MCP documentation](https://docs.anthropic.com/en/docs/claude-code/mcp), and [FreeScout custom module instructions](https://github.com/freescout-help-desk/freescout/wiki/FreeScout-Modules#3-installing-custom-modules).

