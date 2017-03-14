#!/bin/bash

# Set up the daytona data directory
daytona_data_dir=$1

echo -e "Starting Daytona UI Installation ...\n"
sudo rm -rf /tmp/daytona_install.log

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
sudo cp -r ../../UI/. /var/www/html/daytona >> /tmp/daytona_install.log
# Change the default DB password
sudo sed -i.bak -e 's/anotyadPassword/2wsxXSW@/' /var/www/html/daytona/daytona_config.ini 

# Create Daytona data direcrtory
sudo mkdir -p $daytona_data_dir 

sudo chmod -R 777 /var/www/html/daytona

# Create link to daytona_root directory
sudo ln -s $daytona_data_dir /var/www/html/daytona/test_data

# Copy TestData diretories
cp -r ../../TestData/* $daytona_data_dir 

echo -e "Permissions for /var/www/html/daytona...\n"
sudo chown -R apache:apache /var/www/html/daytona >> /tmp/daytona_install.log

sudo chmod -R 777 /var/www/html/daytona

echo -e "Changing Apache2 configuration files for Daytona Application...\n"
sudo sed -i.bak '/<Directory \"\/var\/www\/html\">/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/httpd/conf/httpd.conf

if ! grep -q daytona /etc/httpd/conf/httpd.conf ; then
  sudo sed -i.bak 's/\/var\/www\/html/\/var\/www\/html\/daytona/' /etc/httpd/conf/httpd.conf
fi

echo -e "Restarting Apache...\n"
sudo systemctl restart httpd.service
