#!/bin/sh

grep --text "class Version {" -A3 updater.phar | grep -v dirty
