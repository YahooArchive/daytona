#!/bin/bash

source config.sh

sudo apt-get remove --purge apache2 ssl-cert -y
sudo apt-get remove apache2-common -y
sudo rm -rf /var/www/html/daytona

sudo apt-get remove --purge libapache2-mod-php7.0 php7.0 php7.0-common php7.0-mcrypt php7.0-zip php7.0-mysqlnd php-common -y

sudo apt-get remove --purge mysql-server mysql-client mysql-common -y

sudo apt-get autoremove -y
sudo apt-get autoclean -y
sudo rm -rf /var/lib/mysql /etc/mysql

# Kill scheduler and agent
ps -ef | grep sar | awk '{print $2}' > pidfile
ps -ef | grep agent.py | awk '{print $2}' >> pidfile
ps -ef | grep iostat | awk '{print $2}' >> pidfile
ps -ef | grep scheduler.py | awk '{print $2}' >> pidfile

for i in `cat pidfile`
do
        sudo kill -9 $i
done


sudo rm -rf /var/www/html/daytona
sudo rm -rf $daytona_install_dir
sudo rm -rf $daytona_data_dir 
sudo rm -rf /tmp/daytona_sarmonitor
sudo rm -rf /tmp/ExecScripts
sudo rm -rf /tmp/daytona_root

rm -f pidfile
