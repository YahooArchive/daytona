#!/bin/bash

# Sample execution script to run a simple performance test within Daytona
# Demonstrates the steps of gathering arguments from the UI and runs a performance test
# Command line arguments:

echo "--------------------execscript--------------------"
echo $@
echo "--------------------------------------------------"


# The first argument is ignored to get aroud an issue in processing of the arguments string in our Python code 
var_nop=${1}
var_qps=`echo $2 | sed "s/\"//g"`
var_duration=`echo $3 | sed "s/\"//g"`


# Insert your benchmark script here
dd if=/dev/zero of=/dev/null count=20000000
sleep 10
dd if=/dev/zero of=/dev/null count=20000000
sleep 10
dd if=/dev/zero of=/dev/null count=20000000

echo "QPS : " $var_qps
echo "Duration : " $var_duration

echo "Benchmark Test Completed"

# These KPI's would be computed by your benchmark 
avglatency=10.5
maxlatency=50
minlatency=6
failurerate=0.1

# Create your results.csv with all KPI's in name, value format
echo Key, Value > results.csv
echo QPS, $var_qps >> results.csv
echo AvgLatency-ms, $avglatency >> results.csv
echo MaxLatency-ms, $maxlatency >> results.csv
echo MinLatency-ms, $minlatency >> results.csv
echo FailureRate, $failurerate >>  results.csv
echo Duration-secs, $var_duration >> results.csv

# Create a multi-column ( > 2) csv file with multiple rows
echo Col1, Col2, Col3, Col4 > multicol.csv
echo 1, 2, 3, 4 >> multicol.csv
echo 5, 6, 7, 8 >> multicol.csv
echo 9, 10, 11, 12 >> multicol.csv
echo 13, 14, 15, 16 >> multicol.csv

# Collect /proc/cpuinfo and /proc/meminfo data
cat /proc/cpuinfo > cpuinfo.txt
cat /proc/meminfo > meminfo.txt
