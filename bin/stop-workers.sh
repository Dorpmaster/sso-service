#!/usr/bin/env sh
echo "Stopping workers"
supervisorctl stop eventbus:*
