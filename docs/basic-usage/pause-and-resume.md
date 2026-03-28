---
title: Pause and resume
weight: 5
---

Sometimes you want to take a quick look at the server halfway through a deploy, or you just want to slow things down and watch each step more carefully. You can press `p` at any point during execution. Scotty finishes the current task and then waits.

![Pause and resume](https://github.com/spatie/scotty/blob/main/docs/images/scotty-pause.jpg?raw=true)

Press `Enter` to continue with the next task, or `Ctrl+C` if you want to stop entirely.

## Cancelling

You can press `Ctrl+C` at any time to cancel. Scotty restores the terminal and exits cleanly. Everything that was already output stays in your scrollback, so you can scroll up to see what happened.
