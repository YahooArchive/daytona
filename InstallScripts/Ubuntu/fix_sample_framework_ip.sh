#!/bin/bash

cd ../../Scheduler+Agent

ip=`grep "Server started" Agent_logging_rotatingfile.log | awk '{print $12}' | sed 's/:/ /g' | awk '{print $1}'`

echo "Updating location of execution script, default exechost and default statstics host"
echo ""
ip=`grep "Server started" Agent_logging_rotatingfile.log | awk '{print $12}' | sed 's/:/ /g' | awk '{print $1}'`
script_location=$ip:/tmp/ExecScripts/sample_execscript.sh
echo update ApplicationFrameworkMetadata set execution_script_location="'"`echo $script_location`"'" where frameworkid=50";" > fix_exec.sql
echo update HostAssociationType set default_value="'"`echo $ip`"'" where frameworkid=50 and name="'"execution"';" >> fix_exec.sql
echo update HostAssociationType set default_value="'"`echo $ip`"'" where frameworkid=50 and name="'"statistics"';" >> fix_exec.sql

mysql -u daytona -panotyadPassword daytona < ./fix_exec.sql
