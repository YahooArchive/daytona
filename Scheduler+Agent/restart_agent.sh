#!/bin/bash

ps -ef | grep agent.py | grep -v grep | awk '{print $2}' | sudo xargs kill

sudo kill `sudo lsof -t -i:52223` &>/dev/null
echo "Restarting agent in some time.."
sleep 10

sudo nohup python ./agent.py > agent_nohup.out 2>&1 &
