---
title: Running tasks
weight: 4
---

## Running a task or macro

```bash
scotty run deploy
scotty run cloneRepository
```

Scotty shows each task as it runs, with a step counter, elapsed time, and the command that's currently executing:

![scotty run deploy](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.jpg?raw=true)

If a task fails, Scotty shows the output and stops. You'll immediately see what went wrong:

![Task failure](https://github.com/spatie/scotty/blob/main/docs/images/scotty-failure.jpg?raw=true)

## Pretend mode

Want to see what would happen without actually running anything? Use `--pretend`:

```bash
scotty run deploy --pretend
```

This shows the SSH commands Scotty would execute, without connecting to any server.

## Continue on failure

By default, Scotty stops at the first failed task. If you want it to keep going regardless:

```bash
scotty run deploy --continue
```

## Summary mode

If you don't need to see all the output and just want to know whether things passed:

```bash
scotty run deploy --summary
```

This hides task output and only shows results. Failed tasks always show their output, even in summary mode.

## Dynamic options

You can pass custom variables from the command line:

```bash
scotty run deploy --branch=develop
```

In the Scotty.sh format, `--branch=develop` becomes `$BRANCH`. The key is uppercased and dashes become underscores.

In the Blade format, it becomes available as `$branch`.

## Listing tasks

To see all available tasks and macros in your file:

```bash
scotty tasks
```

![scotty tasks](https://github.com/spatie/scotty/blob/main/docs/images/scotty-tasks.jpg?raw=true)

## SSH into a server

You can quickly SSH into any server defined in your Scotty file:

```bash
scotty ssh
scotty ssh remote
```

If only one server is defined, Scotty connects to it directly. With multiple servers, you'll get a selection prompt.

## Creating a new file

```bash
scotty init
```

This prompts you to choose a format (bash or Blade) and a server host, then creates the file for you.
