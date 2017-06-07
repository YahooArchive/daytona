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

if [ ! -f $daytona_db_backup ] || [ ! -f $daytona_data_backup ]; then
    echo "Backup files not found at location : $HOME"
    exit 1
fi

mysql -u ${db_user} -p${db_password} ${db_name} < $daytona_db_backup

cd $daytona_data_dir
sudo tar -xvf $daytona_data_backup

cd -

cd $daytona_install_dir/Scheduler+Agent
./restart_scheduler.sh

cd -

