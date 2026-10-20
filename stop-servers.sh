#!/bin/bash

RUN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.run"

for pid_file in "$RUN_DIR"/*.pid; do
    [ -f "$pid_file" ] || continue
    kill "$(cat "$pid_file")" 2>/dev/null && echo "Stopped $(basename "$pid_file" .pid)"
    rm -f "$pid_file"
done
