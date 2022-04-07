#!/usr/bin/env sh
echo "Starting workers"
supervisorctl start eventbus:*
