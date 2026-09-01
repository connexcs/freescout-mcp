# Release process

## Release gates

From a clean checkout with the supported PHP and Node versions:

```bash
composer install --no-interaction --prefer-dist
composer check
composer interop-inspector
scripts/build-release.sh
```

`composer check` validates Composer metadata, known dependency advisories, PHP syntax, unit/protocol tests, documentation links/version consistency, and client configuration fixtures. Inspector is separate because it downloads the pinned Node package.

The build script installs production dependencies into a clean staging directory, creates `build/McpServer-<version>.zip`, writes its SHA-256 checksum, and validates the archive layout and manifest.

## Release checklist

1. Update `CHANGELOG.md` and the version in `version.txt`, `module.json`, and `Config/config.php`.
2. Review the latest final MCP specification, official PHP SDK, Codex MCP documentation, Claude Code MCP documentation, and supported FreeScout release.
3. Run the PHP 8.1–8.4 CI matrix, Inspector probe, dependency audit, FreeScout runtime/migration/auth/read/OAuth fixtures, and packaged-install test.
4. Verify the archive contains no tests, development scripts, `.git`, plaintext credentials, or development Composer packages.
5. Publish the ZIP and matching `.sha256` file together.
6. Tag the exact commit as `v<version>` and attach release notes derived from the changelog.
7. Install the published ZIP on a staging FreeScout instance before production rollout.

## Supported upgrade path

Version 1.0.0 supports an in-place upgrade from the 0.2–0.6 development builds. Apply every bundled migration and replace the complete module directory so obsolete dependencies cannot survive. See [Installation and upgrade](installation.md).
