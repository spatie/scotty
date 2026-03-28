---
title: Doctor
weight: 2
---

Validate your setup before deploying:

```bash
scotty doctor
```

Doctor runs through a series of checks:

1. **Scotty file exists** and can be found
2. **File parses successfully** without errors, showing how many tasks and macros were found
3. **Servers are defined** in the file
4. **Tasks are defined** in the file
5. **Macros reference valid tasks** so you don't get surprised mid-deploy
6. **SSH connectivity** to each remote server, with connection timing
7. **Remote tools** checks whether `php`, `composer`, `node`, `npm`, and `git` are available on each reachable server

![scotty doctor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-doctor.png?raw=true)
<!-- SCREENSHOT NEEDED: `scotty doctor` output with all checks passing -->

Run this after setting up a new server, when debugging connection issues, or before your first deploy to a new environment.
