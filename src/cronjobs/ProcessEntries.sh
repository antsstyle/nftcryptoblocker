#!/bin/bash

threadCount=4
waitTime=900

while getopts t:w: flag
do
    case "${flag}" in
        t) threadCount=${OPTARG};;
        w) waitTime=${OPTARG};;
    esac
done

cd "$(dirname "$0")"

cd "../"

fileName=$(basename -- "$0")

tmpDir="$(dirname "$PWD")/tmp/"

mkdir -p ${tmpDir}

for (( i = 1; i <= ${threadCount}; i++ ))
do
    lockFile="${tmpDir}${fileName}-p$i"
    if [[ $waitTime -gt 0 ]]
    then
        nohup flock -e -w ${waitTime} ${lockFile} php cronjobs/ProcessEntries.php "-p$i" &
    else
        nohup flock -en ${lockFile} php cronjobs/ProcessEntries.php "-p$i" &
    fi
done