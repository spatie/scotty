---
title: Running tasks
weight: 3
---

## Running a task or macro

```bash
scotty run deploy
scotty run cloneRepository
```

Scotty shows each task as it runs, with a step counter, elapsed time, and the command being executed.

![scotty run deploy](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.png?raw=true)
<!-- SCREENSHOT NEEDED: Full `scotty run deploy` output -->

When a task fails, its output is shown and execution stops.

![Task failure](https://github.com/spatie/scotty/blob/main/docs/images/scotty-failure.png?raw=true)
<!-- SCREENSHOT NEEDED: A failed task -->

## Pretend mode

See the SSH commands that would be executed without running them:

```bash
scotty run deploy --pretend
```

## Continue on failure

By default, Scotty stops at the first failed task. To keep going:

```bash
scotty run deploy --continue
```

## Summary mode

Hide task output and only show results:

```bash
scotty run deploy --summary
```

Output is always shown for failed tasks, even in summary mode.

## Dynamic options

Pass custom variables from the command line:

```bash
scotty run deploy --branch=develop
```

In the bash format, `--branch=develop` becomes `$BRANCH`. The key is uppercased and dashes become underscores.

In the Blade format, it becomes available as `$branch`.

## Listing tasks

See all available tasks and macros:

```bash
scotty tasks
```

![scotty tasks](https://github.com/spatie/scotty/blob/main/docs/images/scotty-tasks.png?raw=true)
<!-- SCREENSHOT NEEDED: `scotty tasks` output -->

## SSH into a server

Connect to a server defined in your Scotty file:

```bash
scotty ssh
scotty ssh remote
```

If only one server is defined, Scotty connects to it directly. With multiple servers, you get a selection prompt.

## Creating a new file

```bash
scotty init
```

This prompts you to choose a format (bash or Blade) and a server host, then creates the file.
