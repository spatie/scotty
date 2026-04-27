## Release process

The phar is built and attached to the GitHub release automatically by the `build-phar.yml` workflow (triggered on release publish). You no longer need to build or commit the phar by hand.

When creating a new release:

1. Update the version string in `config/app.php` to match the new version.
2. Commit the version bump and push to `main`.
3. Create the release: `gh release create X.Y.Z --target main --generate-notes`.
4. Wait for the `build-phar` workflow to finish. It runs `php scotty app:build --build-version=X.Y.Z` and uploads `builds/scotty` as a release asset.

Watch the workflow: `gh run watch --repo spatie/scotty $(gh run list --repo spatie/scotty --workflow build-phar.yml --limit 1 --json databaseId --jq '.[0].databaseId')`.

The version in `config/app.php` matters for users who run Scotty from source (composer global, dev clones). Released phars always report the tag name because the workflow passes `--build-version=<tag>`.

### Versioning

- New features: minor bump (1.3.0 → 1.4.0).
- Bug fixes only: patch bump (1.3.0 → 1.3.1).
- Breaking changes (Scotty.sh syntax, public command behavior): major bump, only with explicit user approval.

A behavior change that affects how scripts evaluate (without changing syntax) is a judgement call. When in doubt, ask the user.
