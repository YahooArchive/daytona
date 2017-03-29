#!/bin/bash

# Set up the ExecScripts directory
cp -r ../../ExecScripts /tmp

# Setup Daytona sarmonitor
cp -r ../../Scheduler+Agent/daytona_sarmonitor /tmp

# Install sysstat for sar and iostat
sudo yum install sysstat -y

# Install Strace
sudo yum install strace -y

cd ../../Scheduler+Agent

# Start Agent
sudo nohup python ./agent.py > agent_nohup.out 2>&1 &
