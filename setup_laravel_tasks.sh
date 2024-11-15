#!/bin/bash

echo "installing timer service"

sudo cp laravel-schedule-run.service /lib/systemd/system/
sudo cp laravel-schedule-run.timer /lib/systemd/system/
sudo systemctl daemon-reload
# IMPORTANT: without .timer for the commands enable
# and start, this will not work. Do not start the .service
# or the timer will no make recurring calls properly
sudo systemctl enable laravel-schedule-run.timer
sudo systemctl start laravel-schedule-run.timer

echo "timer service installed!"
