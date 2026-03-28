---
title: Introduction
weight: 1
---

Scotty is a tool that runs tasks on your remote servers over SSH. You write your tasks in a `Scotty.sh` file, and Scotty takes care of connecting to your servers, running each script, and showing you exactly what's happening. If you're already using [Laravel Envoy](https://laravel.com/docs/envoy), Scotty can read your existing files out of the box, so you can use it as a [drop-in replacement](/docs/scotty/v1/advanced-usage/envoy-compatibility).

![Deploy output](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.jpg?raw=true)

The `Scotty.sh` format is plain bash with annotation comments. That means your editor highlights it correctly and all your shell tooling (linting, syntax checking, autocompletion) just works. No more Blade templates for shell scripts.

While your tasks run, Scotty shows each one with its name, a step counter, elapsed time, and the command that's currently executing. When everything finishes, you get a summary table so you can see at a glance how long each step took. If you need to interrupt a deploy, you can press `p` to [pause](/docs/scotty/v1/basic-usage/pause-and-resume) after the current task and resume with `Enter`.

There's also a `scotty doctor` command that checks your entire setup: it validates your file, tests SSH connectivity to each server, and verifies that tools like PHP, Composer, and Git are installed on the remote machines.

![scotty doctor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-doctor.jpg?raw=true)
