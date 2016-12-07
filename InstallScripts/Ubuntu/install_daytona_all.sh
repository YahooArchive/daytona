#!/bin/bash

daytona_install_dir=~ubuntu/Daytona_prod/Daytona-V2
daytona_data_dir=/tmp/daytona_root/test_data_DH
distro=Ubuntu

mkdir -p $daytona_install_dir
cp -r ../../../Daytona-V2/*  $daytona_install_dir

cd $daytona_install_dir/InstallScripts/$distro
echo "****** Installing Daytona DB *********"
echo "**************************************"
./install_daytona_db.sh 

sleep 30

cd $daytona_install_dir/InstallScripts/$distro
echo "****** Installing Daytona UI*********"
echo "**************************************"
./install_daytona_ui.sh $daytona_data_dir

cd $daytona_install_dir/InstallScripts/$distro
echo "****** Installing Daytona Scheduler*****"
echo "****************************************"
./install_daytona_scheduler.sh $daytona_data_dir

cd $daytona_install_dir/InstallScripts/$distro
echo "****** Installing Daytona Agent ******"
echo "**************************************"
./install_daytona_agent.sh

sleep 10

cd $daytona_install_dir/InstallScripts/$distro
echo "****** Updating IP of sample framework ******"
echo "*********************************************"
./fix_sample_framework_ip.sh
