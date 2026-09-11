#!/bin/bash

# Start Mailpit (if not already running) and open the web UI in the browser

if pgrep -x mailpit > /dev/null; then
    echo "Mailpit is already running."
else
    mailpit &
    MAILPIT_PID=$!
    echo "Mailpit started (PID $MAILPIT_PID)"
    sleep 1
fi

echo "Opening http://127.0.0.1:8025 ..."
open http://127.0.0.1:8025

if [[ -n "$MAILPIT_PID" ]]; then
    wait $MAILPIT_PID
fi
