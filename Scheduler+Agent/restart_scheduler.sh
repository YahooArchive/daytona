#!/bin/bash

if [ "$EUID" -ne 0 ]
  then echo "Please run as root/sudo"
  exit
fi

ps -ef | grep scheduler.py | grep -v grep | awk '{print $2}' | sudo xargs kill
sudo kill `sudo lsof -t -i:52222` &>/dev/null
echo "Restarting scheduler in some time.."
sleep 10
nohup python ./scheduler.py > scheduler_nohup.out 2>&1 &
