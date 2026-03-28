---
title: Running tasks
weight: 4
---

## Running a task or macro

```bash
scotty run deploy
scotty run cloneRepository
```

Scotty shows each task as it runs, with a spinner, elapsed time, and the command being executed:

![scotty run deploy](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.png?raw=true)
<!-- SCREENSHOT NEEDED: Full `scotty run deploy` output showing a real deploy with multiple tasks completing successfully -->

When a task fails, its output is shown and execution stops:

![Task failure](https://github.com/spatie/scotty/blob/main/docs/images/scotty-failure.png?raw=true)
<!-- SCREENSHOT NEEDED: A failed task showing the error output and "✗ Failed at taskName" message -->

After all tasks complete, you get a summary table with timing.

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

In the Blade format, it becomes `$branch` (camelCase) and `$branch` (snake_case).

## Pause and resume

Press `p` any time during execution. Scotty immediately acknowledges the pause request and pauses between the current and next task.

![Pause and resume](https://github.com/spatie/scotty/blob/main/docs/images/scotty-pause.png?raw=true)
<!-- SCREENSHOT NEEDED: Deploy paused between tasks, showing the "⏸ Paused" message and "press Enter to continue" -->

Press `Enter` to resume, or `Ctrl+C` to cancel.

## Cancelling

Press `Ctrl+C` to cancel. Scotty restores the terminal and exits. All output stays in your scrollback.
