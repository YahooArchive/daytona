#!/bin/bash

daytona_install_dir=$HOME/Daytona_prod/Daytona
daytona_data_dir=/var/www/html/daytona/daytona_root/test_data_DH

echo -e "Stopping and removing  Apache2"
sudo systemctl stop httpd.service

sudo yum remove httpd httpd-tools -y
sudo rm -rf /var/www/html/daytona

echo -e "\n Removing PHP & Requirements "
sudo yum remove php70 php70-mysql php70-cli php70-common php70-pdo -y

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
        kill -9 $i
done

sudo rm -rf $daytona_install_dir
sudo rm -rf $daytona_data_dir 
sudo rm -rf /var/www/html/daytona
sudo rm -rf /tmp/daytona_sarmonitor
sudo rm -rf /tmp/ExecScripts

rm -f pidfile
