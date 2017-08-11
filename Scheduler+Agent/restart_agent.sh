#!/bin/bash

if [ "$EUID" -ne 0 ]
  then echo "Please run as root/sudo"
  exit
fi

ps -ef | grep agent.py | grep -v grep | awk '{print $2}' | sudo xargs kill

sudo kill `sudo lsof -t -i:52223` &>/dev/null
echo "Restarting agent in some time.."
sleep 10

nohup python ./agent.py > agent_nohup.out 2>&1 &
