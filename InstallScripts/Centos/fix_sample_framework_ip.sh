#!/bin/bash

source config.sh

if [ -z $db_name ] || [ -z $db_user ] || [ -z $db_password ] || [ -z $db_host ] || [ -z $db_root_pass ] || [ -z $daytona_install_dir ] || [ -z $daytona_data_dir ] || [ -z $ui_admin_pass ] || [ -z $email_user ] || [ -z $email_domain ] || [ -z $smtp_server ] || [ -z $smtp_port ]; then
  echo 'one or more variables are undefined in config.sh'
  echo 'Please configure config.sh'
  echo 'For details that are unknown, enter some dummy values'
  exit 1
fi

ip=`hostname`

echo "Updating default exechost"
echo ""
echo update HostAssociationType set default_value="'"`echo $ip`"'" where frameworkid=51 and name="'"execution"';" >> fix_exec.sql

mysql -u ${db_user} -p${db_password} ${db_name} < ./fix_exec.sql

sudo rm -rf fix_exec.sql
