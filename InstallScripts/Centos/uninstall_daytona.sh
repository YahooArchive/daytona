#!/bin/bash

source config.sh

echo -e "Stopping and removing  Apache2"
sudo systemctl stop httpd.service

sudo yum remove httpd httpd-tools -y
sudo rm -rf /var/www/html/daytona

echo -e "\n Removing PHP & Requirements "
sudo yum remove php70w php70w-mysql php70w-cli php70w-common php70w-pdo -y

echo -e "\n Stopping and Removing MySQL"
sudo systemctl stop mysqld.service
sudo yum remove mysql-server -y

sudo rm -rf /var/lib/mysql /var/lib/php /var/log/mysqld.log

# Kill scheduler and agent
ps -ef | grep sar | awk '{print $2}' > pidfile
ps -ef | grep agent.py | awk '{print $2}' >> pidfile
ps -ef | grep iostat | awk '{print $2}' >> pidfile
ps -ef | grep scheduler.py | awk '{print $2}' >> pidfile

for i in `cat pidfile`
do
        sudo kill -9 $i
done

sudo rm -rf $daytona_install_dir
sudo rm -rf $daytona_data_dir 
sudo rm -rf /var/www/html/daytona
sudo rm -rf /tmp/daytona_sarmonitor
sudo rm -rf /tmp/ExecScripts
sudo rm -rf /tmp/daytona_root

rm -f pidfile
