#!/bin/bash

# DB Root credentails

db_root_pass="daytona_admin"

# Daytona DB Details
db_name="daytona"
db_user="daytona"
db_password="anotyadPassword"
db_host="localhost"

# Daytona Test Admin Account Details
username="admin"
password="admin"

echo -e "Starting Daytona Installation ...\n"
sudo rm -rf /tmp/daytona_install.log


echo -e "Updating Ubuntu ...\n"
# update & upgrade #
sudo apt-get -y  update > /tmp/daytona_install.log
sudo apt-get -y upgrade >> /tmp/daytona_install.log
sudo apt-get -y dist-upgrade >> /tmp/daytona_install.log

echo -e "Installing MySQL...\n"
sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password password '${db_root_pass}
sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password '${db_root_pass}
sudo apt-get install mysql-server mysql-client -y >> /tmp/daytona_install.log

echo -e "Setting Daytona Database Configuration...\n"

sudo service mysql start

mysql -uroot -p${db_root_pass} -e "CREATE USER '${db_name}'@'${db_host}' IDENTIFIED BY '${db_password}';" >> /tmp/daytona_install.log
mysql -uroot -p${db_root_pass} -e "CREATE DATABASE ${db_name};" >> /tmp/daytona_install.log
mysql -uroot -p${db_root_pass} -e "GRANT ALL PRIVILEGES ON ${db_name}.* TO '${db_name}'@'${db_host}';" >> /tmp/daytona_install.log
mysql -uroot -p${db_root_pass} -e "FLUSH PRIVILEGES;" >> /tmp/daytona_install.log

echo -e "Creating all required daytona tables in Database...\n"
mysql -u${db_user} -p${db_password} ${db_name} < ../../DbSchema/DbSchema.sql
