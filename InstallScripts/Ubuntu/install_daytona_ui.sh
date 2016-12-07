#!/bin/bash

# Set up the daytona data directory
daytona_data_dir=$1

echo -e "Starting Daytona UI Installation ...\n"

echo -e "Installing Apache2...\n"

sudo apt-get install apache2 ssl-cert -y >> /tmp/daytona_install.log

echo -e "Installing PHP... \n"
sudo apt-get install libapache2-mod-php5 php5 php5-common php5-mcrypt php5-mysqlnd -y >> /tmp/daytona_install.log

echo -e "Verifying installs...\n"
sudo apt-get install apache2 libapache2-mod-php5 php5 mysql-server php5-mysqlnd mysql-client -y >> /tmp/daytona_install.log

echo -e "Enabling Apache & PHP Modules...\n"

sudo a2enmod rewrite >> /tmp/daytona_install.log
sudo php5enmod mcrypt >> /tmp/daytona_install.log

echo -e "Restarting Apache...\n"
sudo service apache2 restart >> /tmp/daytona_install.log

echo -e "Extracting Daytona Front-End Source Code in /var/www/html/daytona...\n"
sudo mkdir -p /var/www/html/daytona
sudo cp -r ../../UI/. /var/www/html/daytona >> /tmp/daytona_install.log

# Create daytona data directory
mkdir -p $daytona_data_dir 

# Create link to daytona_root directory
sudo ln -s $daytona_data_dir /var/www/html/daytona/test_data

# Copy TestData diretories
cp -r ../../TestData/* $daytona_data_dir 

echo -e "Permissions for /var/www/html/daytona...\n"
sudo chown -R www-data:www-data /var/www/html/daytona >> /tmp/daytona_install.log

echo -e "Changing Apache2 configuration files for Daytona Application...\n"
sudo sed -i.bak '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf >> /tmp/daytona_install.log
sudo sed -i.bak 's/Directory \/var\/www\//Directory \/var\/www\/html\/daytona\//' /etc/apache2/apache2.conf >> /tmp/daytona_install.log
sudo sed -i.bak 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/daytona/' /etc/apache2/sites-available/000-default.conf >> /tmp/daytona_install.log

echo -e "Restarting Apache...\n"
sudo service apache2 restart >> /tmp/daytona_install.log
