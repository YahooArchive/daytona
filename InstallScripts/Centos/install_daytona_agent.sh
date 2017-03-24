#!/bin/bash

# Install MySQL Python Connector Package
sudo rpm -Uvh https://dev.mysql.com/get/mysql-connector-python-2.1.5-1.el7.x86_64.rpm

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
