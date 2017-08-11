#!/bin/bash

if [ "$EUID" -ne 0 ]
    then echo "Please run as root/sudo"
    exit
fi

#### Provide backup file names with location
daytona_db_backup=
daytona_data_backup=

if [ -z $daytona_db_backup ] || [ -z $daytona_data_backup ]; then
    echo "Backup files information not provided. Please configure and then run restore"
    exit 1
fi

if [ ! -f $daytona_db_backup ] || [ ! -f $daytona_data_backup ]; then
    echo "Backup files doesn't exists. Please check"
    exit 1
fi

source config.sh

if [ -z $db_name ] || [ -z $db_user ] || [ -z $db_password ] || [ -z $db_host ] || [ -z $db_root_pass ] || [ -z $daytona_install_dir ] || [ -z $daytona_data_dir ] || [ -z $ui_admin_pass ] || [ -z $email_user ] || [ -z $email_domain ] || [ -z $smtp_server ] || [ -z $smtp_port ]; then
  echo 'one or more variables are undefined in config.sh'
  echo 'Please configure config.sh'
  echo 'For details that are unknown, enter some dummy values'
  exit 1
fi

mysql -u ${db_user} -p${db_password} ${db_name} < $daytona_db_backup

cd $daytona_data_dir
tar -xvf $daytona_data_backup

cd $daytona_install_dir/Scheduler+Agent
./restart_scheduler.sh

