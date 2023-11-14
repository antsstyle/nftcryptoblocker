#!/bin/bash

cd "$(dirname "$0")"

cd "../"

fileName=$(basename -- "$0")
tmpDir="$(dirname "$PWD")/tmp/"

mkdir -p ${tmpDir}

lockFile="${tmpDir}${fileName}"
nohup flock -en ${lockFile} php cronjobs/ArchiveBlockRecords.php &
