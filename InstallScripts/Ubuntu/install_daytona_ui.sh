#!/bin/bash

source config.sh

if [ -z $db_name ] || [ -z $db_user ] || [ -z $db_password ] || [ -z $db_host ] || [ -z $db_root_pass ] || [ -z $daytona_install_dir ] || [ -z $daytona_data_dir ] || [ -z $ui_admin_pass ] || [ -z $email_user ] || [ -z $email_domain ] || [ -z $smtp_server ] || [ -z $smtp_port ]; then
  echo 'one or more variables are undefined in config.sh'
  echo 'Please configure config.sh'
  echo 'For details that are unknown, enter some dummy values'
  exit 1
fi

echo -e "Starting Daytona UI Installation ...\n"

daytona_ui_config=/var/www/html/daytona/daytona_config.ini

echo -e "Updating Ubuntu ...\n"
# update & upgrade #
sudo apt-get -y  update
sudo apt-get -y upgrade
sudo apt-get -y dist-upgrade

echo -e "Installing Apache2...\n"

sudo apt-get install apache2 ssl-cert -y

echo -e "Installing PHP... \n"
sudo apt-get install libapache2-mod-php7.0 php7.0 php7.0-common php7.0-mcrypt php7.0-zip php7.0-mysqlnd -y

echo -e "Enabling Apache & PHP Modules...\n"

sudo a2enmod rewrite
sudo phpenmod mcrypt

echo -e "Restarting Apache...\n"
sudo service apache2 restart

echo -e "Extracting Daytona Front-End Source Code in /var/www/html/daytona...\n"
sudo mkdir -p /var/www/html/daytona
sudo cp -r ../../UI/. /var/www/html/daytona

# Setup DB Credential for UI
sudo rm -rf $daytona_ui_config

echo dbname = '"'${db_name}'"' | sudo tee -a $daytona_ui_config > /dev/null
echo username = '"'${db_user}'"' | sudo tee -a $daytona_ui_config > /dev/null
echo servername = '"'${db_host}'"' | sudo tee -a $daytona_ui_config > /dev/null
echo password = '"'${db_password}'"' | sudo tee -a $daytona_ui_config > /dev/null
echo daytona_data_dir = '"'${daytona_data_dir}'"' | sudo tee -a $daytona_ui_config > /dev/null

# Create daytona data directory
sudo mkdir -p $daytona_data_dir 

# Create link to daytona_root directory
sudo ln -s $daytona_data_dir /var/www/html/daytona/test_data

# Copy TestData diretories
sudo cp -r ../../TestData/* $daytona_data_dir 

echo -e "Permissions for /var/www/html/daytona...\n"
sudo chown -R www-data:www-data /var/www/html/daytona
sudo chmod -R 777 $daytona_data_dir

echo -e "Changing Apache2 configuration files for Daytona Application...\n"
sudo sed -i.bak '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
if ! grep -q daytona /etc/apache2/sites-available/000-default.conf ; then
  sudo sed -i.bak 's/Directory \/var\/www\//Directory \/var\/www\/html\/daytona\//' /etc/apache2/apache2.conf
  sudo sed -i.bak 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/daytona/' /etc/apache2/sites-available/000-default.conf
fi

echo cookie_key = '"'`head -c8 /dev/urandom | sha512sum | base64 | head -c 64`'"' | sudo tee -a $daytona_ui_config > /dev/null

# Setting password for UI admin account in DB

sudo rm -rf pass_generate.php
sudo rm -rf fix_exec.sql

printf "<?php\n\$arg = \$argv[1];\necho password_hash(\$arg, PASSWORD_DEFAULT);\n?>" >> pass_generate.php
admin_pass=`php pass_generate.php $ui_admin_pass`

echo update LoginAuthentication set password="'"`echo $admin_pass`"'" where username="'"admin"';" >> fix_exec.sql

mysql -u ${db_user} -p${db_password} ${db_name} < ./fix_exec.sql

sudo rm -rf pass_generate.php
sudo rm -rf fix_exec.sql

echo -e "Restarting Apache...\n"
sudo service apache2 restart

