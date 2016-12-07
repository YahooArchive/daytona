#!/bin/bash

# Sample execution script to run a simple performance within Daytona
#Command line arguments:

echo "--------------------execscript--------------------"
echo $@
echo "--------------------------------------------------"

var_e=${1}
var_qps=`echo $2 | sed "s/\"//g"`
var_duration=`echo $3 | sed "s/\"//g"`


# Store location of daytona job directory
LOCAL_LOC=$(pwd)

rm  *.csv

# Write the start timestamp~
echo `date +%s` > $LOCAL_LOC/log/START_EXECUTION
echo "$LOCAL_LOC/log/START_EXECUTION"

# Insert your benchmark script here
#stress --cpu 2 --timeout 30
dd if=/dev/zero of=/dev/null count=20000000
sleep 10
dd if=/dev/zero of=/dev/null count=20000000
sleep 10
dd if=/dev/zero of=/dev/null count=20000000

# Write end timestamp~
echo `date +%s` > $LOCAL_LOC/log/END_EXECUTION
echo "$LOCAL_LOC/log/END_EXECUTION"

# Create your app_kpi.csv file with all KPI's in name, value format

echo "QPS : " $var_qps
echo "DUR : " $var_duration

echo "arg#1 : " $1
echo "arg#2 : " $2

avglatency=10.5
maxlatency=50
minlatency=6
failurerate=0.1

# Create the csv file for application KPI
echo Key, Value > $LOCAL_LOC/app_kpi.csv
echo QPS, $var_qps >> $LOCAL_LOC/app_kpi.csv
echo AvgLatency-ms, $avglatency >> $LOCAL_LOC/app_kpi.csv
echo MaxLatency-ms, $maxlatency >> $LOCAL_LOC/app_kpi.csv
echo MinLatency-ms, $minlatency >> $LOCAL_LOC/app_kpi.csv
echo FailureRate, $failurerate >> $LOCAL_LOC/app_kpi.csv
echo Duration-secs, $var_duration >> $LOCAL_LOC/app_kpi.csv

# Create a multi-column ( > 2) csv file with multiple rows
echo Col1, Col2, Col3, Col4 > $LOCAL_LOC/multicol.csv
echo 1, 2, 3, 4 >> $LOCAL_LOC/multicol.csv
echo 5, 6, 7, 8 >> $LOCAL_LOC/multicol.csv
echo 9, 10, 11, 12 >> $LOCAL_LOC/multicol.csv
echo 13, 14, 15, 16 >> $LOCAL_LOC/multicol.csv

# Collect /proc/cpuinfo and /pro/meminfo data
cat /proc/cpuinfo > $LOCAL_LOC/cpuinfo.txt
cat /proc/meminfo > $LOCAL_LOC/meminfo.txt

# Copy all .csv and .plt files to the log directory so Daytona scheduler can copy these to the Daytona host at the end of the  test
cp $LOCAL_LOC/app_kpi.csv $LOCAL_LOC/results.csv

echo "--------------------------------------------------"
cat $LOCAL_LOC/app_kpi.csv
echo "--------------------------------------------------"
cat $LOCAL_LOC/results.csv
echo "--------------------------------------------------"

