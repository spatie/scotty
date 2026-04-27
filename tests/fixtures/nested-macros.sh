#!/usr/bin/env scotty

# @servers local=127.0.0.1
# @macro story_1 task_1
# @macro story_2 task_2 story_1

# @task on:local
task_1() {
    echo "task 1"
}

# @task on:local
task_2() {
    echo "task 2"
}
