#!/bin/bash

source config.sh

if [ -z $db_name ] || [ -z $db_user ] || [ -z $db_password ] || [ -z $db_host ] || [ -z $db_root_pass ] || [ -z $daytona_install_dir ] || [ -z $daytona_data_dir ] || [ -z $ui_admin_pass ] || [ -z $email_user ] || [ -z $email_domain ] || [ -z $smtp_server ] || [ -z $smtp_port ]; then
  echo 'one or more variables are undefined in config.sh'
  echo 'Please configure config.sh'
  echo 'For details that are unknown, enter some dummy values'
  exit 1
fi

daytona_db_backup=$HOME/daytona_db_backup.sql
daytona_data_backup=$HOME/daytona_data_backup.tar.gz

sudo rm -rf $daytona_db_backup $daytona_data_backup

mysqldump -u ${db_user} -p${db_password} ${db_name} > $daytona_db_backup

cd $daytona_data_dir
sudo tar -cvf $daytona_data_backup .

cd -
