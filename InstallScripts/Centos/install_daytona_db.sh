#!/bin/bash

source config.sh

echo -e "Updating Linux...\n"
# update & upgrade #
sudo yum update -y 

echo -e "Installing MySQL...\n"
sudo rpm -Uvh https://dev.mysql.com/get/mysql57-community-release-el7-9.noarch.rpm
sudo yum install mysql-server -y
sudo systemctl start mysqld.service
sudo grep 'temporary password' /var/log/mysqld.log
root_pass=$(sudo grep 'temporary password' /var/log/mysqld.log)
root_pass_temp=${root_pass##* }

echo -e "Setting Daytona Database Configuration...\n"

mysql --connect-expired-password --user=root --password=${root_pass_temp} -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${db_root_pass}';"
mysql --connect-expired-password --user=root --password=${db_root_pass} -e "CREATE USER '${db_user}'@'${db_host}' IDENTIFIED BY '${db_password}';"
mysql --connect-expired-password --user=root --password=${db_root_pass} -e "CREATE DATABASE ${db_name};"
mysql --connect-expired-password --user=root --password=${db_root_pass} -e "GRANT ALL PRIVILEGES ON ${db_name}.* TO '${db_user}'@'${db_host}';"
mysql --connect-expired-password --user=root --password=${db_root_pass} -e "FLUSH PRIVILEGES;"

echo -e "Creating all required daytona tables in Database...\n"
mysql --connect-expired-password --user=${db_user} --password=${db_password} ${db_name} < ../../DbSchema/DbSchema.sql 
