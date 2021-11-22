#!/bin/bash

[ "${FLOCKER}" != "$0" ] && exec env FLOCKER="$0" flock -en "$0" "$0" "$@" || :
    
cd "$(dirname "$0")"

cd "../"

php cronjobs/Test.php
