#!/bin/bash

# Install MySQL Python Connector Package
sudo rpm -Uvh https://dev.mysql.com/get/mysql-connector-python-2.1.5-1.el7.x86_64.rpm

# Install sendmail package
sudo yum install sendmail -y

cd ../../Scheduler+Agent

dh_root_value=$1

sed -i.bak -e 's/anotyadPassword/2wsxXSW@/' config.ini 
sed "s|dh_root_value|$dh_root_value|g" config.ini > config.ini.tmp
mv config.ini.tmp config.ini 

# Start Scheduler
nohup python ./scheduler.py &
