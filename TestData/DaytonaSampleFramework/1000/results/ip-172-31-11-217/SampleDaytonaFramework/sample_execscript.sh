#!/bin/bash

# Sample execution script to run a simple performance test within Daytona
# Demonstrates the steps of gathering arguments from the UI and runs a performance test
# Command line arguments:

echo "--------------------execscript--------------------"
echo $@
echo "--------------------------------------------------"


iterations=`echo $1 | sed "s/\"//g"`
delay=`echo $2 | sed "s/\"//g"`

# Run your performance/benchmark/workload here
x=1
while [ $x -le $iterations ]
do
  dd if=/dev/zero of=/dev/null count=20000000
  sleep $delay
  x=$(( $x + 1 ))
done

echo "Iterations : " $iterations
echo "Delay : " $delay

echo "Benchmark Test Completed"

# These KPI's would be computed by your benchmark 
avglatency=10.5
maxlatency=50
minlatency=6
failurerate=0.1

# Create your results.csv with all KPI's in name, value format
echo Key, Value > results.csv
echo Iterations, $iterations >> results.csv
echo AvgLatency-ms, $avglatency >> results.csv
echo MaxLatency-ms, $maxlatency >> results.csv
echo MinLatency-ms, $minlatency >> results.csv
echo FailureRate, $failurerate >>  results.csv
echo Delay-secs, $delay >> results.csv

# Create a multi-column ( > 2) csv file with multiple rows
echo Col1, Col2, Col3, Col4 > multicol.csv
echo 1, 2, 3, 4 >> multicol.csv
echo 5, 6, 7, 8 >> multicol.csv
echo 9, 10, 11, 12 >> multicol.csv
echo 13, 14, 15, 16 >> multicol.csv

# Collect /proc/cpuinfo and /proc/meminfo data
cat /proc/cpuinfo > cpuinfo.txt
cat /proc/meminfo > meminfo.txt
