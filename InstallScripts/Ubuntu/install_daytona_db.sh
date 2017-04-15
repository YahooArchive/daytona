#!/bin/bash

source config.sh

echo -e "Starting Daytona Installation ...\n"

echo -e "Updating Ubuntu ...\n"
# update & upgrade #
sudo apt-get -y  update
sudo apt-get -y upgrade
sudo apt-get -y dist-upgrade

echo -e "Installing MySQL...\n"
sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password password '${db_root_pass}
sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password '${db_root_pass}
sudo apt-get install mysql-server mysql-client -y

echo -e "Setting Daytona Database Configuration...\n"

sudo service mysql start

mysql -uroot -p${db_root_pass} -e "CREATE USER '${db_user}'@'${db_host}' IDENTIFIED BY '${db_password}';"
mysql -uroot -p${db_root_pass} -e "CREATE DATABASE ${db_name};"
mysql -uroot -p${db_root_pass} -e "GRANT ALL PRIVILEGES ON ${db_name}.* TO '${db_user}'@'${db_host}';"
mysql -uroot -p${db_root_pass} -e "FLUSH PRIVILEGES;"

echo -e "Creating all required daytona tables in Database...\n"
mysql -u${db_user} -p${db_password} ${db_name} < ../../DbSchema/DbSchema.sql
