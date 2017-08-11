import os
import datetime
import csv
import re


class ProcessOutputFiles:
    def __init__(self, logctx):
        self.lctx = logctx

    def process_output_files(self, path):
        # Process top output files
        self.process_top_output(path)
        self.process_docker_output(path)
        return "Files processing completed"

    def process_top_output(self, path):
        output_file = path + "top_output.txt"
        self.lctx.debug("Top output processing started for file : " + output_file)

        cpu_map = {}
        mem_map = {}
        res_mem_map = {}
        cpu_array = []
        mem_array = []
        res_mem_array = []
        cpu_array.append(["Time"])
        mem_array.append(["Time"])
        res_mem_array.append(["Time"])
        timestamp = 0

        if not os.path.exists(output_file):
            return "File Not Found Exception"

        cpu_file = open(path + "cpu_usage.plt", 'w+')
        if not cpu_file:
            return "Not able to create cpu_usage.plt"

        mem_file = open(path + "memory_usage.plt", 'w+')
        if not mem_file:
            return "Not able to create memory_usage.plt"

        res_mem_file = open(path + "res_memory_usage.plt", 'w+')
        if not res_mem_file:
            return "Not able to create res_memory_usage.plt"

        process_data = True
        with open(output_file) as f:
            for line in f:
                line = line.strip('\n')
                line = line.strip()
                line = re.sub(' +', ' ', line)
                line = line.replace(' ', ',')

                if line.startswith("top"):
                    process_data = False
                    continue

                if line.startswith("PID"):
                    process_data = True
                    continue

                if process_data:
                    if len(line) > 0:
                        try:
                            datetime.datetime.strptime(line, "%Y-%m-%dT%H:%M:%S%fz")
                            cpu_array = self.update_array_from_map(timestamp, cpu_array, cpu_map)
                            mem_array = self.update_array_from_map(timestamp, mem_array, mem_map)
                            res_mem_array = self.update_array_from_map(timestamp, res_mem_array, res_mem_map)
                            timestamp = line
                            cpu_map.clear()
                            mem_map.clear()
                            res_mem_map.clear()
                        except:
                            line_array = line.split(",")
                            cpu_map[line_array[11] + " - " + line_array[0]] = line_array[8]
                            mem_map[line_array[11] + " - " + line_array[0]] = line_array[9]
                            res_mem_map[line_array[11] + " - " + line_array[0]] = line_array[5]

        cpu_array = self.update_array_from_map(timestamp, cpu_array, cpu_map)
        mem_array = self.update_array_from_map(timestamp, mem_array, mem_map)
        res_mem_array = self.update_array_from_map(timestamp, res_mem_array, res_mem_map)

        self.create_plt_from_array(cpu_file, cpu_array)
        self.create_plt_from_array(mem_file, mem_array)
        self.create_plt_from_array(res_mem_file, res_mem_array)

    def process_docker_output(self, path):
        output_file = path + "docker_stat.txt"
        self.lctx.debug("Docker stat output processing started for file : " + output_file)

        cpu_map = {}
        mem_map = {}
        
        cpu_array = []
        mem_array = []
        
        cpu_array.append(["Time"])
        mem_array.append(["Time"])
        
        timestamp = 0

        if not os.path.exists(output_file):
            return "File Not Found Exception"

        cpu_file = open(path + "docker_cpu.plt", 'w+')
        if not cpu_file:
            return "Not able to create docker_cpu.plt"

        mem_file = open(path + "docker_memory.plt", 'w+')
        if not mem_file:
            return "Not able to create docker_memory.plt"

        with open(output_file) as f:
            for line in f:
                line = line.strip('\n')
                line = line.strip()
                line = re.sub(' +', ' ', line)
                line = line.replace(' ', ',')

                if line.startswith("NAME"):
                    continue

                if len(line) > 0:
                    try:
                        datetime.datetime.strptime(line, "%Y-%m-%dT%H:%M:%S%fz")
                        cpu_array = self.update_array_from_map(timestamp, cpu_array, cpu_map)
                        mem_array = self.update_array_from_map(timestamp, mem_array, mem_map)
                        
                        timestamp = line
                        cpu_map.clear()
                        mem_map.clear()
                    except:
                        line_array = line.split(",")
                        cpu_map[line_array[0] + " - " + line_array[1]] = line_array[2]
                        mem_map[line_array[0] + " - " + line_array[1]] = line_array[3]

        cpu_array = self.update_array_from_map(timestamp, cpu_array, cpu_map)
        mem_array = self.update_array_from_map(timestamp, mem_array, mem_map)

        self.create_plt_from_array(cpu_file, cpu_array)
        self.create_plt_from_array(mem_file, mem_array)

    def create_plt_from_array(self, fh, array):
        with fh as f:
            writer = csv.writer(f)
            writer.writerows(array)
        fh.close()

    def update_array_from_map(self, ts, input_array, input_map):
        row_count = len(input_array)
        col_count = len(input_array[0])
        if len(input_map) == 0 and ts != 0:
            temp_list = list()
            temp_list.append(ts)
            for i in range(1, col_count):
                temp_list.append(0.0)

            input_array.append(temp_list)

        elif len(input_map) > 0 and ts != 0:
            temp_list = list()
            temp_list.append(ts)
            for i in range(1, col_count):
                if input_array[0][i] in input_map:
                    temp_list.append(input_map.get(input_array[0][i]))
                    input_map.pop(input_array[0][i], None)
                else:
                    temp_list.append(0.0)

            input_array.append(temp_list)
            row_count += 1

            if len(input_map) > 0:
                for x in input_map:
                    input_array[0].append(x)
                    col_count += 1
                    for i in range(1, row_count):
                        if input_array[i][0] == ts:
                            input_array[i].append(input_map[x])
                        else:
                            input_array[i].append(0.0)

        return input_array

