#!/bin/bash

if [ "$EUID" -ne 0 ]
  then echo "Please run as root/sudo"
  exit
fi

source config.sh

echo -e "Stopping and removing  Apache2"
systemctl stop httpd.service

yum remove httpd httpd-tools -y
rm -rf /var/www/html/daytona

echo -e "\n Removing PHP & Requirements "
yum remove php70w php70w-mysql php70w-cli php70w-common php70w-pdo -y

echo -e "\n Stopping and Removing MySQL"
systemctl stop mysqld.service
yum remove mysql-server -y

rm -rf /var/lib/mysql /var/lib/php /var/log/mysqld.log

# Kill scheduler and agent
ps -ef | grep sar | awk '{print $2}' > pidfile
ps -ef | grep agent.py | awk '{print $2}' >> pidfile
ps -ef | grep iostat | awk '{print $2}' >> pidfile
ps -ef | grep scheduler.py | awk '{print $2}' >> pidfile

for i in `cat pidfile`
do
        sudo kill -9 $i
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
