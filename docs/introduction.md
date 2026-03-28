---
title: Introduction
weight: 1
---

Scotty is a tool for executing common tasks on your remote servers. You define tasks in a `Scotty.sh` file, and Scotty handles connecting to your servers, running the scripts, and showing you what's happening. Scotty is also fully compatible with [Laravel Envoy](https://laravel.com/docs/envoy), so you can use it as a [drop-in replacement](/docs/scotty/v1/advanced-usage/envoy-compatibility).

![Deploy output](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.png?raw=true)

The `Scotty.sh` file format is plain bash with annotation comments. Your editor highlights it correctly and you get full shell support: linting, syntax checking, and autocompletion all work out of the box.

While tasks run, Scotty shows each one with its name, a step counter, elapsed time, and the command currently being executed. When all tasks finish, you get a summary table with timing. You can press `p` at any point to [pause execution](/docs/scotty/v1/advanced-usage/pause-and-resume) after the current task and resume with `Enter`.

There's also a built-in `scotty doctor` command that validates your file, tests SSH connectivity, and checks that required tools are installed on each server.
