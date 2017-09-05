#!/bin/bash

# Daytona execution script for a simple Node.js CPU Micro Benchmark
# Please make sure Node.js and wrk are installed and update the path in node_micro.js file


# Command line arguments:
threads=`echo $1 | sed "s/\"//g"`
connections=`echo $2 | sed "s/\"//g"`
duration=`echo $3 | sed "s/\"//g"`
timeout=`echo $4 | sed "s/\"//g"`
node=`echo $5 | sed "s/\"//g"`
port=`echo $6 | sed "s/\"//g"`
numcpu=`echo $7 | sed "s/\"//g"`
cpuloop=`echo $8 | sed "s/\"//g"`

# Start Node processes
./NodeJs/node_micro.js $numcpu $cpuloop &
sleep 10

# Run Node.js micro benchmark with wrk as the load generator
wrk -t $threads -c $connections -d $duration http://$node:$port --latency --timeout $timeout > wrk.out

# Create your results.cs file with all KPI's in name, value format
rps=`grep "Requests/sec" wrk.out | awk '{print $2}'`
latency50th=`grep "50% " wrk.out | awk '{print $2}'`
latency75th=`grep "75% " wrk.out | awk '{print $2}'`
latency90th=`grep "90% " wrk.out | awk '{print $2}'`
latency99th=`grep "99% " wrk.out | awk '{print $2}'`

# Create the results.csv file for application KPI
echo Threads, $threads > results.csv
echo Connections, $connections >> results.csv
echo NodeProcesses, $numcpu >> results.csv
echo CPULoop, $cpuloop >> results.csv
echo RPS, $rps >> results.csv
echo Latency50th, $latency50th >> results.csv
echo Latency75th, $latency75th >> results.csv
echo Latency90th, $latency90th >> results.csv
echo Latency99th, $latency99th >> results.csv

# Stop Node processes
pkill -P $$
