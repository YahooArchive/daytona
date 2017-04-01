#!/bin/bash

    data_file=$1
    header_string=$2
    time_interval=$3

    if [[ -z "$1"  || -z "$2" ||  -z "$3" ]]; then
        echo -e "argument missing"
        exit
    fi

    IFS=',' read -ra header_arr <<< "$header_string"
    header_len=${#header_arr[@]}
    if [ $header_len  -lt 1 ]; then
        echo -e "No header passed"
        exit
    fi

    if [[ -f "$data_file" ]]; then
        date=`date +%FT`
        RUNTIME=0
        header=true
        while read line; do
            if [ $header = true ]; then
                header_string="Time,$header_string"
                echo -e $header_string
                header=false
            fi
            IFS=' ' read -ra data_arr <<< "$line"
            data_len=${#data_arr[@]}
            if [[ $data_len -ne $header_len ]]; then
                echo -e "data mismatch"
                exit
            fi
	    RUNTIME=$((RUNTIME + $time_interval))
            HOURS=`expr $RUNTIME / 3600`
            REMAINDER=`expr $RUNTIME % 3600`
            MINS=`expr $REMAINDER / 60`
            SECS=`expr $REMAINDER % 60`
            value=`printf "%02d:%02d:%02d\n" "$HOURS" "$MINS" "$SECS"`
            new_line=$date$value"Z"
            for i in "${data_arr[@]}"; do
                new_line="$new_line,$i"
            done
            new_line="$new_line"
            echo -e "$new_line"
            unset new_line
            unset new_date
        done < "$data_file"
    else
        echo -e "File not found"
        exit
    fi
