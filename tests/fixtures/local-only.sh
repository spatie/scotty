#!/usr/bin/env scotty

# @servers local=127.0.0.1
# @macro deploy greet done

# @task on:local
greet() {
    echo "hello from scotty"
}

# @task on:local
done() {
    echo "finished"
}
