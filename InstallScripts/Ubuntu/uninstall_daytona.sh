#!/bin/bash

if [ "$EUID" -ne 0 ]
  then echo "Please run as root/sudo"
  exit
fi

source config.sh

apt-get remove --purge apache2 ssl-cert -y
apt-get remove apache2-common -y
rm -rf /var/www/html/daytona

apt-get remove --purge libapache2-mod-php7.0 php7.0 php7.0-common php7.0-mcrypt php7.0-zip php7.0-mysqlnd php-common -y

apt-get remove --purge mysql-server mysql-client mysql-common -y

apt-get autoremove -y
apt-get autoclean -y
rm -rf /var/lib/mysql /etc/mysql

# Kill scheduler and agent
ps -ef | grep sar | awk '{print $2}' > pidfile
ps -ef | grep agent.py | awk '{print $2}' >> pidfile
ps -ef | grep iostat | awk '{print $2}' >> pidfile
ps -ef | grep scheduler.py | awk '{print $2}' >> pidfile

for i in `cat pidfile`
do
        kill -9 $i
done

rm -rf ${daytona_install_dir}/Scheduler+Agent
rm -rf $daytona_data_dir
rm -rf /var/www/html/daytona
rm -rf ${daytona_install_dir}/ExecScripts
rm -rf ${daytona_install_dir}/daytona_agent_root
rm -f pidfile
rm -rf ${daytona_install_dir}/config.sh
rm -rf ${daytona_install_dir}/daytona_backup.sh
rm -rf ${daytona_install_dir}/daytona_restore.sh
rm -rf ${daytona_install_dir}/cron-backup.sh
