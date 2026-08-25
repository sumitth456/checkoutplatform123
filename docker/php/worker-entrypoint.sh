#!/bin/sh
set -e

export WORKER_PROCESSES="${WORKER_PROCESSES:-2}"

if [ "${SUPERVISOR_PROFILE:-worker}" = "scheduler" ]; then
    rm -f /etc/supervisor/conf.d/laravel-worker.conf
elif [ "${SUPERVISOR_PROFILE:-worker}" = "worker" ]; then
    rm -f /etc/supervisor/conf.d/laravel-scheduler.conf
fi

export APP_ENTRYPOINT_ROLE=worker
/usr/local/bin/app-entrypoint.sh "$@"
