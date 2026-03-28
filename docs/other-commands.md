---
title: Other commands
weight: 5
---

## Listing tasks

See all available tasks and macros:

```bash
scotty tasks
```

This shows a table of tasks with their target servers, and macros with their task sequences.

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

```
  Scotty Doctor

  ✓ Scotty file found (Scotty.sh)
  ✓ File parsed successfully (5 tasks, 1 macro)
  ✓ 2 server(s) defined
  ✓ 5 task(s) defined
  ✓ All macro tasks exist

  Servers
  ✓ local (127.0.0.1) — skipped (local)
  ✓ remote (forge@your-server.com) — connected in 0.3s

  Remote tools (remote)
  ✓ php 8.4.1
  ✓ composer 2.8.0
  ✓ node 20.11.0
  ✓ npm 10.2.0
  ✓ git 2.43.0
```

Run this after setting up a new server or when debugging connection issues.
