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

Declare every option you want to accept at the top of your Scotty.sh file with `# @option`. There are three forms:

```bash
# @option staging          # boolean flag: $STAGING='1' when --staging is passed, unset otherwise
# @option branch=main      # optional value with a default: $BRANCH='main' unless overridden
# @option tag=             # required value: scotty errors if --tag=... isn't passed
```

```bash
scotty run deploy --branch=develop --tag=v1.2 --staging
```

### Naming

An option named `branch` is exposed to your tasks as `$BRANCH`. The same rule applies to the CLI flag, the environment variable Scotty looks up, and the assignment written into the script preamble: each option has exactly **one** canonical bash name.

The transformation is:

- Dashes become underscores (`release-name` → `release_name`)
- The result is uppercased (`release_name` → `RELEASE_NAME`)

So `# @option release-name=latest` declares a CLI flag `--release-name=...`, an env var `$RELEASE_NAME`, and a script variable `$RELEASE_NAME` — all one and the same. Flags that aren't declared are rejected with `The "--foo" option does not exist.`

### Precedence

For value options, Scotty resolves each variable in this order:

1. CLI flag (`--branch=develop` or `--release-name=v42`)
2. Environment variable of the canonical bash name (`BRANCH=develop scotty run deploy`, `RELEASE_NAME=v42 scotty run deploy`)
3. Declared default (`# @option branch=main`)

Boolean flags only read from the CLI — `$STAGING` is unset unless `--staging` was passed on this invocation.

> Note: `@option` declarations are currently supported in the Scotty.sh (bash) format. Blade-format files ignore `@option` and continue to forward any passed CLI flag as a Blade variable.

## Pause and resume

Sometimes you want to take a quick look at the server halfway through a deploy, or you just want to slow things down and watch each step more carefully. Press `p` at any point during execution. Scotty finishes the current task and then waits.

![Pause and resume](https://github.com/spatie/scotty/blob/main/docs/images/scotty-pause.jpg?raw=true)

Press `Enter` to continue with the next task, or `Ctrl+C` if you want to stop entirely.

## Cancelling

You can press `Ctrl+C` at any time to cancel. Scotty restores the terminal and exits cleanly. Everything that was already output stays in your scrollback, so you can scroll up to see what happened.

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
