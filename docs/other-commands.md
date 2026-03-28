---
title: Other commands
weight: 5
---

## Listing tasks

See all available tasks and macros:

```bash
scotty tasks
```

![scotty tasks](https://github.com/spatie/scotty/blob/main/docs/images/scotty-tasks.png?raw=true)
<!-- SCREENSHOT NEEDED: `scotty tasks` output showing a table of tasks and macros -->

## SSH into a server

Connect to a server defined in your Scotty file:

```bash
scotty ssh
scotty ssh remote
```

If only one server is defined, Scotty connects to it directly. With multiple servers, you get a selection prompt.

## Creating a new Scotty file

```bash
scotty init
```

This prompts you to choose a format (bash or Blade) and a server host, then creates the file.

## Doctor

Validate your setup before deploying:

```bash
scotty doctor
```

This checks:

- Your Scotty file exists and parses correctly
- Servers and tasks are defined
- Macros reference valid task names
- SSH connectivity to each remote server
- Required tools on remote servers (`php`, `composer`, `node`, `npm`, `git`)

![scotty doctor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-doctor.png?raw=true)
<!-- SCREENSHOT NEEDED: `scotty doctor` output with all checks passing, showing server connectivity and tool versions -->

Run this after setting up a new server or when debugging connection issues.
