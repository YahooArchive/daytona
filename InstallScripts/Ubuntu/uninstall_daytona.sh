#!/bin/bash

daytona_install_dir=~ubuntu/Daytona_prod/Daytona-V2
daytona_data_dir=/tmp/daytona_root/test_data_DH

sudo apt-get remove --purge apache2 ssl-cert -y
sudo apt-get remove apache2-common -y
sudo rm -rf /var/www/html/daytona

sudo apt-get purge libapache2-mod-php5 php5 php5-common php5-mcrypt php5-mysqlnd -y

sudo apt-get remove --purge mysql-server mysql-client mysql-common mysql-server-5.5 mysql-client-5.5 mysql-server-core-5.5 mysql-client-core-5.5 libapache2-mod-auth-mysql -y


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
        kill -9 $i
done


sudo rm -rf /var/www/html/daytona
sudo rm -rf $daytona_install_dir
sudo rm -rf $daytona_data_dir 
sudo rm -rf /tmp/daytona_sarmonitor
sudo rm -rf /tmp/ExecScripts

rm -f pidfile
