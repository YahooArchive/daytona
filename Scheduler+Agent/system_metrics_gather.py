#!/usr/bin/env python

import os
import common
import subprocess
import re
import action
import logging
import time

# Global
sys_logger = None
iostat_file_ext = "_iostat_block_devices.plt"
network_io_file_ext = "_network_devices.plt"
system_metrics_interval = '5'
docker_stat_header = "NAME                CONTAINER           CPU %               MEM %"

# Bash Commands
date_cmd = ['date', '-u', '+%Y-%m-%dT%H:%M:%SZ']
top_cmd = ['top', '-b', '-i', '-d', system_metrics_interval]
top_get_header = ['top', '-b', '-n', '1', '-i']
iostat_cmd = ['iostat', '-dtx', system_metrics_interval]
iostat_get_header = ['iostat', '-dtx']
sar_get_header = {'cpu': ['sar', '-u', '1', '1'],
                  'task': ['sar', '-w', '1', '1'],
                  'nfs': ['sar', '-n', 'NFS', '1', '1'],
                  'mem': ['sar', '-r', '1', '1'],
                  'network_io': ['sar', '-n', 'DEV', '1', '1']
                  }
docker_version = ['docker', '-v']
docker_command = "( date -u +'%Y-%m-%dT%H:%M:%SZ' && docker stats -a --format " \
                 "'table {{.Name}}\t{{.Container}}\t{{.CPUPerc}}\t{{.MemPerc}}\t' --no-stream )"
sar_cmd = ['sar', '-n', 'DEV', '-n', 'NFS', '-u', '-r', '-w', system_metrics_interval]

get_pid = ["ps", "-eo", "pid,cmd,%cpu", "--sort=-%cpu"]
grep2 = ["grep", "-v", "grep"]
awk = ["awk", "FNR == 1 {print $1}"]


def loggersetup(filename):
    if os.path.isfile(filename):
        os.remove(filename)

    logger = logging.getLogger("system_metrics")
    logger.setLevel(logging.DEBUG)
    fh = logging.FileHandler(filename)
    fh.setLevel(logging.DEBUG)
    ch = logging.StreamHandler()
    ch.setLevel(logging.ERROR)
    formatter = logging.Formatter('%(asctime)s %(levelname)-6s {%(filename)s %(lineno)d} %(message)-100s',
                                  '%Y-%m-%d %H:%M:%S')
    fh.setFormatter(formatter)
    ch.setFormatter(formatter)
    logger.addHandler(fh)
    logger.addHandler(ch)
    logger.propagate = False
    return logger


def top_gather(self):
    running_queue = {}
    p1 = subprocess.Popen(top_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    while True:
        output = p1.stdout.readline()
        if output == '' and p1.poll() is not None:
            break
        if output:
            output = output.rstrip()
            if output.startswith('top'):
                p2 = subprocess.Popen(date_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                timestamp = p2.communicate()[0].strip()
                action.action_lock.acquire()
                running_queue = action.running_tests
                action.action_lock.release()
                for testid, test in running_queue.iteritems():
                    if test.status == "RUNNING":
                        top_file = test.statsdir + "top_output.txt"
                        if os.path.isfile(top_file):
                            with open(top_file, 'a') as fh:
                                fh.write("\n" + timestamp + "\n")
                                fh.write(output + "\n")
                                sys_logger.debug("Generating top output for test : " + str(testid))
                        else:
                            with open(top_file, 'w') as fh:
                                fh.write(timestamp + "\n")
                                fh.write(output + "\n")
                                sys_logger.debug("Starting top output for test : " + str(testid))
                continue

            for testid, test in running_queue.iteritems():
                if test.status == "RUNNING":
                    top_file = test.statsdir + "top_output.txt"
                    if os.path.isfile(top_file):
                        with open(top_file, 'a') as fh:
                            fh.write(output + "\n")


def iostat_gather(self):
    iostat_header = None
    device_header = 0
    device_list = []
    p1 = subprocess.Popen(iostat_get_header, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    output = p1.communicate()[0].strip()
    output = output.split("\n")
    for header in output:
        header = header.strip()
        if header.startswith("Device"):
            header = re.sub(' +', ' ', header)
            header = header.replace(' ', ',')
            header = header.replace("Device:", "Time")
            iostat_header = header
            device_header = 1
            continue

        if device_header:
            header = re.sub(' +', ' ', header)
            header = header.split(' ')
            device_list.append(header[0])

    p2 = subprocess.Popen(iostat_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    running_queue = {}
    timestamp = 0
    try:
        while True:
            output = p2.stdout.readline()
            if output == '' and p2.poll() is not None:
                break
            if output:
                output = output.strip()
                output = re.sub(' +', ' ', output)
                output = output.replace(' ', ',')
                if output.startswith("Device"):
                    p3 = subprocess.Popen(date_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                    timestamp = p3.communicate()[0].strip()
                    action.action_lock.acquire()
                    running_queue = action.running_tests
                    action.action_lock.release()
                    continue

                output = output.split(",")
                output_device = output[0]
                output[0] = str(timestamp)
                output = ",".join(output)
                if output_device in device_list:
                    for testid, test in running_queue.iteritems():
                        if test.status == "RUNNING":
                            iostat_file_name = output_device + iostat_file_ext
                            iostat_file = test.statsdir + iostat_file_name
                            if os.path.isfile(iostat_file):
                                sys_logger.debug("Generating iostat output in " + iostat_file_name + " for test : "
                                                 + str(testid))
                                with open(iostat_file, 'a') as fh:
                                    fh.write(output + "\n")
                            else:
                                with open(iostat_file, 'w') as fh:
                                    sys_logger.debug("Starting " + iostat_file_name + " for test : " + str(testid))
                                    fh.write(iostat_header + "\n")
                                    fh.write(output + "\n")

    except Exception as e:
        sys_logger.error(e)


def sar_gather(self):
    header_row = 2  # In SAR output header is in 2nd row, modify accordingly

    # getting cpu.plt header
    p = subprocess.Popen(sar_get_header['cpu'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    p.wait()
    output = p.communicate()[0].strip()
    output = output.split("\n")[header_row]
    output = re.sub(' +', ' ', output)
    output = output.split(" ")
    del output[:3]
    cpu_plt_header = ",".join(output)

    # getting task.plt header
    p = subprocess.Popen(sar_get_header['task'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    p.wait()
    output = p.communicate()[0].strip()
    output = output.split("\n")[header_row]
    output = re.sub(' +', ' ', output)
    output = output.split(" ")
    del output[:2]
    task_plt_header = ",".join(output)

    # getting mem.plt header
    p = subprocess.Popen(sar_get_header['mem'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    p.wait()
    output = p.communicate()[0].strip()
    output = output.split("\n")[header_row]
    output = re.sub(' +', ' ', output)
    output = output.split(" ")
    del output[:2]
    mem_plt_header = ",".join(output)

    # getting nfs.plt header
    p = subprocess.Popen(sar_get_header['nfs'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    p.wait()
    output = p.communicate()[0].strip()
    output = output.split("\n")[header_row]
    output = re.sub(' +', ' ', output)
    output = output.split(" ")
    del output[:2]
    nfs_plt_header = ",".join(output)

    # getting network_io.plt header
    p = subprocess.Popen(sar_get_header['network_io'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    p.wait()
    output = p.communicate()[0].strip()
    header = output.split("\n")[header_row]
    header = re.sub(' +', ' ', header)
    header = header.split(" ")
    del header[:3]
    net_io_plt_header = ",".join(header)

    # starting SAR gather
    p = subprocess.Popen(sar_cmd, stdout=subprocess.PIPE,
                         stderr=subprocess.PIPE)

    print_cpu_plt = 0
    print_mem_plt = 0
    print_task_plt = 0
    print_net_io_plt = 0
    print_nfs_plt = 0

    while True:
        output = p.stdout.readline()
        if output == '' and p.poll() is not None:
            break
        if output:
            output = output.strip()
            output = re.sub(' +', ' ', output)
            output = output.replace(' ', ',')
            if cpu_plt_header in output:
                print_cpu_plt = 1
		p3 = subprocess.Popen(date_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                timestamp = p3.communicate()[0].strip()
                continue
            elif task_plt_header in output:
                print_task_plt = 1
                continue
            elif nfs_plt_header in output:
                print_nfs_plt = 1
                continue
            elif mem_plt_header in output:
                print_mem_plt = 1
                continue
            elif net_io_plt_header in output:
                print_net_io_plt = 1
                continue
            elif output == "":
                print_cpu_plt = 0
                print_mem_plt = 0
                print_task_plt = 0
                print_net_io_plt = 0
                print_nfs_plt = 0
                continue

            action.action_lock.acquire()
            running_queue = action.running_tests
	    action.action_lock.release()
	    if print_cpu_plt:
                output = output.split(",")
                del output[:3]
                for testid, test in running_queue.iteritems():
                    if test.status == "RUNNING":
                        cpu_plt_file = test.statsdir + "cpu.plt"
                        if os.path.isfile(cpu_plt_file):
                            sys_logger.debug("Generating cpu.plt for test : " + str(testid))
                            with open(cpu_plt_file, 'a') as fh:
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")
                        else:
                            sys_logger.debug("Starting cpu.plt for test : " + str(testid))
                            with open(cpu_plt_file, 'w') as fh:
                                header = "Time," + cpu_plt_header
                                fh.write(header + "\n")
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")

            if print_task_plt:
                output = output.split(",")
                del output[:2]
                for testid, test in running_queue.iteritems():
                    if test.status == "RUNNING":
                        task_plt_file = test.statsdir + "task.plt"
                        if os.path.isfile(task_plt_file):
                            sys_logger.debug("Generating task.plt for test : " + str(testid))
                            with open(task_plt_file, 'a') as fh:
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")
                        else:
                            sys_logger.debug("Starting task.plt for test : " + str(testid))
                            with open(task_plt_file, 'w') as fh:
                                header = "Time," + task_plt_header
                                fh.write(header + "\n")
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")

            if print_mem_plt:
                output = output.split(",")
                del output[:2]
                for testid, test in running_queue.iteritems():
                    if test.status == "RUNNING":
                        mem_plt_file = test.statsdir + "mem.plt"
                        if os.path.isfile(mem_plt_file):
                            sys_logger.debug("Generating mem.plt for test : " + str(testid))
                            with open(mem_plt_file, 'a') as fh:
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")
                        else:
                            sys_logger.debug("Starting mem.plt for test : " + str(testid))
                            with open(mem_plt_file, 'w') as fh:
                                header = "Time," + mem_plt_header
                                fh.write(header + "\n")
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")

            if print_nfs_plt:
                output = output.split(",")
                del output[:2]
                for testid, test in running_queue.iteritems():
                    if test.status == "RUNNING":
                        nfs_plt_file = test.statsdir + "nfs.plt"
                        if os.path.isfile(nfs_plt_file):
                            sys_logger.debug("Generating nfs.plt for test : " + str(testid))
                            with open(nfs_plt_file, 'a') as fh:
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")
                        else:
                            sys_logger.debug("Starting nfs.plt for test : " + str(testid))
                            with open(nfs_plt_file, 'w') as fh:
                                header = "Time," + nfs_plt_header
                                fh.write(header + "\n")
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")

            if print_net_io_plt:
                output = output.split(",")
                del output[:2]
                device = output[0]
                del output[:1]
                for testid, test in running_queue.iteritems():
                    if test.status == "RUNNING":
                        net_io_plt_file_name = device + network_io_file_ext
                        net_io_plt_file = test.statsdir + net_io_plt_file_name
                        if os.path.isfile(net_io_plt_file):
                            sys_logger.debug("Generating " + net_io_plt_file_name + " for test : " + str(testid))
                            with open(net_io_plt_file, 'a') as fh:
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")
                        else:
                            sys_logger.debug("Starting " + net_io_plt_file_name + " for test : " + str(testid))
                            with open(net_io_plt_file, 'w') as fh:
                                header = "Time," + net_io_plt_header
                                fh.write(header + "\n")
                                plt_row = [timestamp] + output
                                plt_row = ",".join(plt_row)
                                fh.write(plt_row + "\n")


def docker_stat_gather(self):

    # Checking docker version
    try:
        p1 = subprocess.Popen(docker_version, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        version = p1.communicate()[0].strip()
        version = re.findall("\d+\.\d+", version)[0]
        version = float(version)
        if version < 10.0:
            sys_logger.error("Docker version less than 10, not supported !! ")
            sys_logger.error("Aborting docker stat gather thread !! ")
            quit()
    except Exception:
        sys_logger.error("Docker not installed !! ")
        sys_logger.error("Aborting docker stat gather thread !! ")
        quit()

    # Starting docker stats
    # Spawning different thread for collecting docker stat as it takes some time collect the stats
    while True:
        thread = common.FuncThread(collect_docker_stats, True)
        thread.start()
        time.sleep(float(system_metrics_interval))


def collect_docker_stats(self):
    p1 = subprocess.Popen(docker_command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, shell=True)
    (output, err) = p1.communicate()

    action.action_lock.acquire()
    running_queue = action.running_tests
    action.action_lock.release()

    if err:
        sys_logger.error("Not able to collect docker stats")
        sys_logger.error(str(err.strip()))
        quit()

    output = output.strip()
    output = output.split("\n")
    for testid, test in running_queue.iteritems():
        if test.status == "RUNNING":
            docker_stat_file = test.statsdir + "docker_stat.txt"
            if os.path.isfile(docker_stat_file):
                sys_logger.debug("Generating docker_stat.txt for test : " + str(testid))
                with open(docker_stat_file, 'a') as fh:
                    for line in output:
                        if line.startswith("NAME"):
                            continue
                        line = line.strip()
                        # line = re.sub(' +', ' ', line)
                        # line = line.replace(' ', ',')
                        fh.write(line + "\n")
                    fh.write("\n")
            else:
                sys_logger.debug("Starting docker_stat.txt for test : " + str(testid))
                with open(docker_stat_file, 'w') as fh:
                    fh.write(docker_stat_header + "\n")
                    for line in output:
                        if line.startswith("NAME"):
                            continue
                        line = line.strip()
                        # line = re.sub(' +', ' ', line)
                        # line = line.replace(' ', ',')
                        fh.write(line + "\n")
                    fh.write("\n")


def strace_gather(self, testid, strace_config):
    delay = float(strace_config['delay'])
    duration = strace_config['duration']
    process = strace_config['process']
    sys_logger.debug("Starting STRACE for Test " + str(testid) + " in " + str(delay) + " secs")
    time.sleep(delay)
    test = action.get_test(testid)
    strace_output_file = test.statsdir + "strace_output.txt"
    sys_logger.debug("Setting up STRACE for process : " + process)
    grep1 = ["grep", process]
    p1 = subprocess.Popen(get_pid, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    p2 = subprocess.Popen(grep1, stdin=p1.stdout, stdout=subprocess.PIPE)
    p3 = subprocess.Popen(grep2, stdin=p2.stdout, stdout=subprocess.PIPE)
    p4 = subprocess.Popen(awk, stdin=p3.stdout, stdout=subprocess.PIPE)
    pid = p4.communicate()[0].strip()
    if not pid:
        msg = "No active PID found for given process : " + process
        sys_logger.debug(msg)
        if test.status == "RUNNING":
            with open(strace_output_file, 'w') as fh:
                fh.write(msg + "\n")
    else:
        sys_logger.debug("PID selected for process " + process + " : " + pid)
        strace_cmd = ["timeout", duration, "strace", "-p", pid, "-c", "-S", "time", "-o", strace_output_file]
        sys_logger.debug("Executing Strace for test " + str(testid))
        sys_logger.debug("Strace command : " + str(strace_cmd))
        p5 = subprocess.Popen(strace_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        p5.wait()
        sys_logger.debug("Appending PID information in output file")
        perl_cmd = ['perl', '-pi', '-e',
                    'print "Strace Process : ' + process + ' | PID : ' + pid + ' \\n\\n" if $. == 1',
                    strace_output_file]
        subprocess.Popen(perl_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)

    sys_logger.debug("Strace complete for test : " + str(testid))


def perf_gather(self, testid, perf_config):
    delay = float(perf_config['delay'])
    duration = perf_config['duration']
    sys_logger.debug("Starting PERF for Test " + str(testid) + " in " + str(delay) + " secs")
    time.sleep(delay)
    test = action.get_test(testid)
    perf_output_file = test.statsdir + "perf_output.txt"
    perf_system_wide_cmd = ['perf', 'stat', '-e',
                            'cycles,instructions,LLC-load-misses,LLC-prefetch-misses,LLC-store-misses', '-a', '-o',
                            perf_output_file, "sleep", duration]
    if test.status == "RUNNING":
        sys_logger.debug("Executing system-wide PERF")
        sys_logger.debug("PERF command : " + str(perf_system_wide_cmd))
        p = subprocess.Popen(perf_system_wide_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        p.wait()
        sys_logger.debug("Finished system-wide PERF")
        error = p.communicate()[1].strip()
        if error:
            sys_logger.debug(error)
            with open(perf_output_file, 'w') as fh:
                fh.write(error + "\n")
            return
        if "process" in perf_config:
            process = perf_config['process']
            sys_logger.debug("Setting up PERF for process : " + process)
            grep1 = ["grep", process]
            p1 = subprocess.Popen(get_pid, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            p2 = subprocess.Popen(grep1, stdin=p1.stdout, stdout=subprocess.PIPE)
            p3 = subprocess.Popen(grep2, stdin=p2.stdout, stdout=subprocess.PIPE)
            p4 = subprocess.Popen(awk, stdin=p3.stdout, stdout=subprocess.PIPE)
            pid = p4.communicate()[0].strip()
            if not pid:
                msg = "No active PID found for given process : " + process
                sys_logger.debug(msg)
                if os.path.isfile(perf_output_file):
                    with open(perf_output_file, 'a') as fh:
                        fh.write(msg + "\n")
            else:
                msg = "PID selected for process " + process + " : " + pid
                sys_logger.debug(msg)
                perf_process_cmd = ['perf', 'stat', '-e', 'cycles:u,instructions:u', '-a', '-p', pid, '-o',
                                    perf_output_file, '--append', 'sleep', duration]
                sys_logger.debug("Executing PERF for process " + process)
                sys_logger.debug("PERF command : " + str(perf_process_cmd))
                p5 = subprocess.Popen(perf_process_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                p5.wait()
                error = p5.communicate()[1].strip()
                if error:
                    sys_logger.debug(error)
                sys_logger.debug("Finished PERF on process")

        sys_logger.debug("PERF complete for test : " + str(testid))


def init_sar_iostat_top():
    global sys_logger
    logger_file = os.getcwd() + "/system_metrics_gather_debug.out"
    sys_logger = loggersetup(logger_file)
    sys_logger.debug("Starting system metrics gather threads")
    sys_logger.debug("Starting top gather")
    t1 = common.FuncThread(top_gather, True)
    t1.start()
    sys_logger.debug("Starting iostat gather")
    t2 = common.FuncThread(iostat_gather, True)
    t2.start()
    sys_logger.debug("Starting SAR gather")
    t3 = common.FuncThread(sar_gather, True)
    t3.start()
    sys_logger.debug("Starting docker stat gather")
    t4 = common.FuncThread(docker_stat_gather, True)
    t4.start()


def perf_strace_gather(testid, perf_config=None, strace_config=None):
    sys_logger.debug("Starting Profilers setup for test ID : " + str(testid))
    sys_logger.debug("Perf configuration details")
    if "process" in perf_config:
        sys_logger.debug(
            "Delay - " + perf_config['delay'] + " Duration - " + perf_config['duration'] + " Process - " + perf_config[
                'process'])
    else:
        sys_logger.debug("Delay - " + perf_config['delay'] + " Duration - " + perf_config['duration'])

    t1 = common.FuncThread(perf_gather, True, testid, perf_config)
    t1.start()

    if strace_config is not None:
        sys_logger.debug("Strace configuration details")
        sys_logger.debug(
            "Delay - " + strace_config['delay'] + " Duration - " + strace_config['duration'] + " Process - " +
            strace_config['process'])
        t2 = common.FuncThread(strace_gather, True, testid, strace_config)
        t2.start()
    else:
        sys_logger.debug("Strace not configured ")
