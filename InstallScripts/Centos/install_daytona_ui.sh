#!/bin/bash

source config.sh

if [ -z $db_name ] || [ -z $db_user ] || [ -z $db_password ] || [ -z $db_host ] || [ -z $db_root_pass ] || [ -z $daytona_install_dir ] || [ -z $daytona_data_dir ] || [ -z $ui_admin_pass ] || [ -z $email_user ] || [ -z $email_domain ] || [ -z $smtp_server ] || [ -z $smtp_port ]; then
  echo 'one or more variables are undefined in config.sh'
  echo 'Please configure config.sh'
  echo 'For details that are unknown, enter some dummy values'
  exit 1
fi

daytona_ui_config=/var/www/html/daytona/daytona_config.ini

echo -e "Updating Linux...\n"
# update & upgrade #
sudo yum update -y 

echo -e "Installing Httpd...\n"
sudo yum install httpd -y
sudo systemctl start httpd.service

echo -e "Installing PHP... \n"
sudo rpm -Uvh https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm
sudo rpm -Uvh https://mirror.webtatic.com/yum/el7/webtatic-release.rpm
sudo yum install php70w php70w-opcache -y
sudo yum install php70w-mysqlnd -y

echo -e "Extracting Daytona Front-End Source Code in /var/www/html/daytona...\n"
sudo mkdir /var/www/html/daytona
sudo cp -r ../../UI/. /var/www/html/daytona

# Setup DB Credential for UI
sudo rm -rf $daytona_ui_config

echo dbname = '"'${db_name}'"' | sudo tee -a $daytona_ui_config > /dev/null
echo username = '"'${db_user}'"' | sudo tee -a $daytona_ui_config > /dev/null
echo servername = '"'${db_host}'"' | sudo tee -a $daytona_ui_config > /dev/null
echo password = '"'${db_password}'"' | sudo tee -a $daytona_ui_config > /dev/null

# Create Daytona data direcrtory
sudo mkdir -p $daytona_data_dir 

# Create link to daytona_root directory
sudo ln -s $daytona_data_dir /var/www/html/daytona/test_data

# Copy TestData diretories
sudo cp -r ../../TestData/* $daytona_data_dir 

echo -e "Permissions for /var/www/html/daytona...\n"
sudo chown -R apache:apache /var/www/html/daytona 
sudo chmod -R 777 /var/www/html/daytona

echo -e "Changing Apache2 configuration files for Daytona Application...\n"
sudo sed -i.bak '/<Directory \"\/var\/www\/html\">/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/httpd/conf/httpd.conf

if ! grep -q daytona /etc/httpd/conf/httpd.conf ; then
  sudo sed -i.bak 's/\/var\/www\/html/\/var\/www\/html\/daytona/' /etc/httpd/conf/httpd.conf
fi

echo cookie_key = '"'`date +%s | sha512sum | base64 | head -c 64`'"' | sudo tee -a $daytona_ui_config > /dev/null

# Setting password for UI admin account in DB

sudo rm -rf pass_generate.php
sudo rm -rf fix_exec.sql

printf "<?php\n\$arg = \$argv[1];\necho password_hash(\$arg, PASSWORD_DEFAULT);\n?>" >> pass_generate.php
admin_pass=`php pass_generate.php $ui_admin_pass`

echo update LoginAuthentication set password="'"`echo $admin_pass`"'" where username="'"admin"';" >> fix_exec.sql

mysql -u ${db_name} -p${db_password} daytona < ./fix_exec.sql

sudo rm -rf pass_generate.php
sudo rm -rf fix_exec.sql

echo -e "Restarting Apache...\n"
sudo systemctl restart httpd.service
