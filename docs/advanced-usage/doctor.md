---
title: Doctor
weight: 2
---

Before you run a deploy for the first time, it's a good idea to check that everything is set up correctly. That's what `scotty doctor` does:

```bash
scotty doctor
```

It runs through a series of checks:

1. **Scotty file exists** and can be found
2. **File parses successfully** without syntax errors, and shows how many tasks and macros were found
3. **Servers are defined** in the file
4. **Tasks are defined** in the file
5. **Macros reference valid tasks** so you don't get surprised mid-deploy by a typo
6. **SSH connectivity** to each remote server, including connection timing
7. **Remote tools** checks whether `php`, `composer`, `node`, `npm`, and `git` are available on each reachable server

![scotty doctor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-doctor.jpg?raw=true)

This is especially useful after setting up a new server, when debugging connection issues, or before your first deploy to a new environment. If something is off, doctor will tell you exactly what.
