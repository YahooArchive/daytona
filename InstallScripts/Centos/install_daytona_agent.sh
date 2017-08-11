#!/bin/bash

if [ "$EUID" -ne 0 ]
  then echo "Please run as root/sudo"
  exit
fi

source config.sh

if [ -z $daytona_install_dir ]; then
  echo 'Daytona install directory not provided'
  echo 'Please configure config.sh'
  exit 1
fi

# echo -e "Updating Linux...\n"
# update & upgrade #
# sudo yum update -y

if ! [ -d "$daytona_install_dir" ]; then
    mkdir -p $daytona_install_dir
fi

# Set up the ExecScripts directory
cp -r ../../ExecScripts ${daytona_install_dir}

cp config.sh $daytona_install_dir
cp uninstall_daytona.sh $daytona_install_dir
echo 'rm -- "$0"' | sudo tee -a ${daytona_install_dir}/uninstall_daytona.sh > /dev/null

# Install sysstat for sar and iostat
yum install sysstat -y

# Install Strace
yum install strace -y

if ! [ -d "$daytona_install_dir/Scheduler+Agent" ]; then
    mkdir -p $daytona_install_dir
    cp -r ../../Scheduler+Agent $daytona_install_dir
fi

cd $daytona_install_dir/Scheduler+Agent

# Setup config file
if ! [ -f config.ini ]; then
    printf "[DH]\ndh_root:"$daytona_data_dir"\nport:52222\nmysql-user:"$db_user"\nmysql-db:"$db_name"\nmysql-host:"$db_host"\nmysql-password:"$db_password"\n" >> config.ini
    printf "email-user:"$email_user"\nemail-server:"$email_domain"\nsmtp-server:"$smtp_server"\nsmtp-port:"$smtp_port"\n\n" >> config.ini
    printf "[AGENT]\nagent-root:${daytona_install_dir}/daytona_agent_root/test_data_AGENT/\nexecscript_location:${daytona_install_dir}/ExecScripts/\nagent_test_logs_location:${daytona_install_dir}/daytona_agent_root/test_data_AGENT/test_logs/\nport:52223" >> config.ini
fi

# Start Agent
nohup python ./agent.py > agent_nohup.out 2>&1 &

