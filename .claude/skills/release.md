## Release process

When creating a new release:

1. Update the version string in `config/app.php` to match the new version
2. Commit the version bump
3. Build the phar: `php scotty app:build --build-version=X.Y.Z`
4. Commit the built phar in `builds/scotty`
5. Tag with the version number
6. Push commits and tags

The version in `config/app.php` must be updated because composer global installs read from that file directly, not from the built phar.
