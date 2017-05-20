import getopt
import sys
import socket
import json
import client
import envelope
import pickle
from logger import LOG
import os.path

# Script Globals - Do not modify
action = None
file_name = ''
framework_id = 0
framework_name = ''
user = ''
password = ''
host = ''
testid = ''
run = None
arg_count = 0

SERVER_PORT = 52222  # Change this if scheduler on server changes
OPTION_LIST = ("--definetest", "--addtest", "--updatetest", "--runtest", "--h", "--getresult")


def display_usage():
    print "Correct Usage:"
    print "Usage          : daytona_cli.py --h"
    print "Define Test    : daytona_cli.py --host <host ip> --u <username> --p <password> --definetest " \
          "--framework <frameworkname> --file <filename>"
    print "Add Test       : daytona_cli.py --host <host ip> --u <username> --p <password> --addtest <--r,--s> " \
          "--file <filename>"
    print "Update Test    : daytona_cli.py --host <host ip> --u <username> --p <password> --updatest <--r,--s " \
          "--testid <testid>"
    print "Run Test       : daytona_cli.py --host <host ip> --u <username> --p <password> --runtest " \
          "--testid <testid>"
    print "Get Results    : daytona_cli.py --host <host ip> --u <username> --p <password> --getresult " \
          "--testid <testid>"
    sys.exit()


def read_arguments():
    try:
        opts, args = getopt.getopt(sys.argv[1:], "",
                                   ["framework=", "definetest", "addtest", "updatetest", "file=", "h", "p=", "u=",
                                    "host=", "testid=", "r", "s", "runtest", "getresult"])
        if not opts:
            print "Error - No Argument passed"
            display_usage()
    except getopt.GetoptError as err:
        # print help information and exit:
        print err
        display_usage()

    global host
    global user
    global password
    global action
    global file_name
    global testid
    global run
    global framework_id
    global framework_name
    global arg_count

    if len(opts) > 7:
        print "Too many arguments provided, check usage"
        display_usage()

    for i in range(len(opts)):
        opt, arg = opts[i]
        if i == 0:
            if opt == "--host":
                host = arg.strip()
                continue
            elif opt == "--h":
                display_usage()
            else:
                print "Error - Invalid argument passed"
                print "1st argument should be host IP address (--host)"
                sys.exit()

        if i == 1:
            if opt == "--u":
                user = arg.strip()
                continue
            else:
                print "Error - Invalid argument passed"
                print "2nd argument should be username (--u)"
                sys.exit()

        if i == 2:
            if opt == "--p":
                password = arg.strip()
                continue
            else:
                print "Error - Invalid argument passed"
                print "3rd argument should be password (--p)"
                sys.exit()

        if i == 3:
            if opt in OPTION_LIST:
                action = OPTION_LIST.index(opt)
                if action in [0, 1, 2]:
                    arg_count = 6
                elif action == 3:
                    arg_count = 5
                continue
            else:
                print "Error - Invalid argument passed"
                print "4th argument should be one among these actions : --definetest, --addtest, --updatetest, " \
                      "--runtest, getresult"
                sys.exit()

        if i == 4:
            if action == 0:
                if opt == "--framework":
                    try:
                        framework_id = int(arg.strip())
                    except ValueError as err:
                        framework_name = arg.strip()
                    continue
                else:
                    print "Error - Invalid argument passed for " + OPTION_LIST[action]
                    print "5th argument should be framework id/name  (--framework)"
                    sys.exit()

            elif action == 1 or action == 2:
                if opt in ("--r", "--s"):
                    run = ("--r", "--s").index(opt)
                    continue
                else:
                    print "Error - Invalid argument passed for " + OPTION_LIST[action]
                    print "5th argument should be save or save & run test  (--r , --s)"
                    sys.exit()

            elif action == 3 or action == 5:
                if opt == "--testid":
                    testid = arg.strip()
                    continue
                else:
                    print "Error - Invalid argument passed for " + OPTION_LIST[action]
                    print "5th argument should be test id  (--testid)"
                    sys.exit()

            else:
                print "Error - Too many arguments provided for " + OPTION_LIST[action]
                sys.exit()

        if i == 5:
            if action == 0 or action == 1:
                if opt == "--file":
                    file_name = arg.strip()
                    continue
                else:
                    print "Error - Invalid argument passed for " + OPTION_LIST[action]
                    print "6th argument should be file name  (--file)"
                    sys.exit()

            if action == 2:
                if opt == "--testid":
                    testid = arg.strip()
                    continue
                else:
                    print "Error - Invalid argument passed for " + OPTION_LIST[action]
                    print "6th argument should be test id  (--testid)"
                    sys.exit()

            else:
                print "Error - Too many arguments provided for" + OPTION_LIST[action]
                sys.exit()

    if len(opts) < 4:
        print "Less argument passed, Please check usage"
        sys.exit()

    if len(opts) < arg_count:
        print "Less argument passed for action " + OPTION_LIST[action] + ", Please check usage"
        sys.exit()


def check_host_hearbeat(cl, env):
    try:
        retsend = cl.send(host, SERVER_PORT, env.construct("DAYTONA_HEARTBEAT", ""))
        st = retsend.split(",")
        if len(st) > 1:
            if retsend.split(",")[1] != "ALIVE":
                print "Error - Host not responding, Scheduler is not running or check port"
                sys.exit()
    except socket.error as err:
        print "Error - Heartbeat failed, host not available"
        sys.exit()

    print "Success -  Host heartbeat successful"


def get_framework_arg(cl, env):
    global framework_id
    global framework_name
    arglist = {}
    cli_param = dict()
    cli_param['user'] = user
    cli_param['password'] = password

    if framework_id:
        cli_param['param'] = 'get_frameworkid_arg|' + str(framework_id)
    else:
        cli_param['param'] = 'get_framework_arg|' + framework_name

    serialize_cli_param = pickle.dumps(cli_param)
    retsend = cl.send(host, SERVER_PORT, env.construct("DAYTONA_CLI", serialize_cli_param))

    if retsend:
        retsend_arr = retsend.split("%")
        if len(retsend_arr) > 1:
            response = retsend_arr[1].split("|")
            response_type = response[0]
            if response_type == "Error":
                response_value = response[1]
                print "Error occured on host"
                print response_value
                sys.exit()
            elif response_type == "SUCCESS":
                if framework_id:
                    framework_name = response[1]
                else:
                    framework_id = response[1]
                arglist = response[2]
            else:
                print "Unknown response received from host"
                print response
                sys.exit()
        else:
            print "Error - Unknow response received from host"
            print retsend
            sys.exit()
    else:
        print "Error - No response received from host"
        sys.exit()

    print "Success - Argument list received from host"
    return arglist


def check_required_input(str_input):
    if not str_input:
        return False
    else:
        return True


def define_test(cl, env):
    test_def = dict()
    test_arg = dict()

    arg_list = get_framework_arg(cl, env)
    arg_list = pickle.loads(arg_list)
    while 1:
        test_def['title'] = raw_input("Enter test title (required) : ")
        if check_required_input(test_def['title']):
            break
        else:
            print "Field is required"
            continue
    test_def['purpose'] = raw_input("Enter test purpose : ")
    while 1:
        test_def['exec_host'] = raw_input("Enter exec host IP (required) : ")
        exec_host_arr = test_def['exec_host'].split(',')
	if check_required_input(test_def['exec_host']):
	    if len(exec_host_arr) > 1:
                print "Error - Exec host only accepts single IP address"
                continue
            else:
                break
        else:
            print "Field is required"
            continue

    while 1:
        test_def['stat_hosts'] = raw_input("Enter stat hosts : ")
        if test_def['stat_hosts'] == '':
            break

        stat_hosts_arr = test_def['stat_hosts'].split(',')
        error = 0
        for ip in stat_hosts_arr:
            if ip.strip() == test_def['exec_host']:
                print "Error - Exec host and stat host IP cannot be same, remove exec host IP from stat host list"
                error = 1
                break

        if error:
            continue
        else:
            break
    while 1:
        test_def['priority'] = raw_input("Enter test priority (default - 1) : ")
        if test_def['priority'] == '':
            test_def['priority'] = "1"
            break
        try:
            if 1 <= int(test_def['priority']) <= 5:
                break
            else:
                raise Exception()

        except Exception:
            print "Test Priority should be number between 1 to 5"

    while 1:
        test_def['timeout'] = raw_input("Enter Timeout(minutes, default - 0) : ")
        if test_def['timeout'] == '':
            test_def['timeout'] = "0"
            break
        try:
            int(test_def['timeout'])
            break
        except Exception:
            print "Timeout should be number"

    test_def['cc'] = raw_input("Enter Email List : ")

    if arg_list:
        print "Test Arguments"
        for arg in arg_list:
            test_arg[arg[0]] = raw_input("Enter " + arg[0] + " (default - " + arg[1] + ") : ")
            if test_arg[arg[0]] == '':
                test_arg[arg[0]] = arg[1]

        test_def['test_arg'] = test_arg

    test_def['frameworkid'] = str(framework_id)
    test_def['frameworkname'] = framework_name

    with open(file_name, 'w') as fp:
        json.dump(test_def, fp)

    print "Success - Test configuration saved in a file " + file_name

def add_test(cl, env):
    cli_param = dict()
    cli_param['user'] = user
    cli_param['password'] = password

    if os.path.isfile(file_name):
        with open(file_name) as fp:
            test_details = json.load(fp)

        if all(k in test_details for k in ("frameworkid", "frameworkname", "title", "exec_host", "cc", "priority",
                                           "purpose", "timeout", "stat_hosts", "test_arg")):
            serialize_test_details = pickle.dumps(test_details)
            if run == 1:
                cli_param['param'] = 'add_test|' + serialize_test_details
            elif run == 0:
                cli_param['param'] = 'add_run_test|' + serialize_test_details
            else:
                print "Unknown save and run option received, check command usage"
                sys.exit()

            serialize_cli_param = pickle.dumps(cli_param)
            retsend = cl.send(host, SERVER_PORT, env.construct("DAYTONA_CLI", serialize_cli_param))

            if retsend:
                retsend_arr = retsend.split("%")
                if len(retsend_arr) > 1:
                    response = retsend_arr[1].split("|")
                    response_type = response[0]
                    if response_type == "Error":
                        response_value = response[1]
                        print "Error occured on host"
                        print response_value
                        sys.exit()
                    elif response_type == "SUCCESS":
                        testid = response[1]
                    else:
                        print "Unknown response received from host"
                        print response
                        sys.exit()
                else:
                    print "Error - Unknown response received from host"
                    print retsend
                    sys.exit()
            else:
                print "Error - No response received from host"
                sys.exit()

            print "Success - Test Added Successfully on host"
            print "Test ID : " + testid

        else:
            print "Error - Some required fields are missing from test definition, check file"
            sys.exit()
    else:
        print "Error - File " + file_name + " doesn't exist, Please check"
        sys.exit()


def run_test(cl, env):
    cli_param = dict()
    cli_param['user'] = user
    cli_param['password'] = password
    cli_param['param'] = 'run_test|' + testid
    serialize_cli_param = pickle.dumps(cli_param)

    retsend = cl.send(host, SERVER_PORT, env.construct("DAYTONA_CLI", serialize_cli_param))
    if retsend:
        retsend_arr = retsend.split("%")
        if len(retsend_arr) > 1:
            response = retsend_arr[1].split("|")
            response_type = response[0]
            if response_type == "Error":
                response_value = response[1]
                print "Error occured on host"
                print response_value
                sys.exit()
            elif response_type == "SUCCESS":
                print response[1]
            else:
                print "Unknown response received from host"
                print response
                sys.exit()
        else:
            print "Error - Unknown response received from host"
            print retsend
            sys.exit()
    else:
        print "Error - No response received from host"
        sys.exit()


def update_test(cl, env):
    global testid
    cli_param = dict()
    cli_param['user'] = user
    cli_param['password'] = password
    cli_param['param'] = 'get_test_by_id|' + testid

    serialize_cli_param = pickle.dumps(cli_param)

    retsend = cl.send(host, SERVER_PORT, env.construct("DAYTONA_CLI", serialize_cli_param))

    if retsend:
        retsend_arr = retsend.split("%")
        if len(retsend_arr) > 1:
            response = retsend_arr[1].split("|")
            response_type = response[0]
            if response_type == "Error":
                response_value = response[1]
                print "Error occured on host"
                print response_value
                sys.exit()
            elif response_type == "SUCCESS":
                test_details = response[1]
                test_details_map = pickle.loads(test_details)
            else:
                print "Unknown response received from host"
                print response
                sys.exit()
        else:
            print "Error - Unknown response received from host"
            print retsend
            sys.exit()
    else:
        print "Error - No response received from host"
        sys.exit()

    print "*************************************************************************************************"
    print "Test Information"
    print "Test ID        : " + str(test_details_map['testid'])
    print "Test Owner     : " + str(test_details_map['user'])
    print "Framework ID   : " + str(test_details_map['frameworkid'])
    print "Framework Name : " + test_details_map['frameworkname']
    print "Title          : " + test_details_map['title']
    print "Purpose        : " + test_details_map['purpose']
    print "Exec Host IP   : " + test_details_map['exec_host']
    print "Stat Hosts     : " + test_details_map['stat_host']
    print "Priority       : " + str(test_details_map['priority'])
    print "Timeout        : " + str(test_details_map['timeout'])
    print "Email List     : " + test_details_map['cc']
    print ""
    print "Run Status"
    print "Creation Time  : " + str(test_details_map['creation'])
    print "Last Modified  : " + str(test_details_map['modified'])
    print "Start Time     : " + str(test_details_map['start'])
    print "End Time       : " + str(test_details_map['end'])
    print "Current Status : " + str(test_details_map['status'])
    print "Status Detail  : " + str(test_details_map['status_detail'])
    print ""

    if 'test_arg' in test_details_map:
        print "Test Arguments"
        for arg, val in test_details_map['test_arg'].iteritems():
            print arg + " : " + val
    print "*************************************************************************************************"

    res = raw_input("Do you want to modify above test ? [Y/N] : ")

    if res.lower() == "y":
        new_details_map = dict()
        new_details_map['frameworkid'] = test_details_map['frameworkid']
        new_details_map['testid'] = test_details_map['testid']

        while 1:
            title = raw_input("Update test title : ")
            if check_required_input(title):
                new_details_map['title'] = title
                break
            else:
                print "Field is required"
                continue

        new_details_map['purpose'] = raw_input("Update test purpose : ")

        while 1:
            exec_host = raw_input("Update exec host IP : ")
            exec_host_arr = exec_host.split(',')
	    if check_required_input(exec_host):
		if len(exec_host_arr) > 1:
		    print "Error - Exec host only accepts single IP address"
		    continue
		else:
		    new_details_map['exec_host'] = exec_host
		    break
	    else:
		print "Field is required"
		continue

        while 1:
            new_details_map['stat_host'] = raw_input("Update stat hosts : ")
            if new_details_map['stat_host'] == '':
                break

            stat_hosts_arr = new_details_map['stat_host'].split(',')
            error = 0
            for ip in stat_hosts_arr:
                if ip.strip() == test_details_map['exec_host']:
                    print "Error - Exec host and stat host IP cannot be same, remove exec host IP from stat host list"
                    error = 1
                    break

            if error:
                continue
            else:
                break

        while 1:
            new_details_map['priority'] = raw_input("Update test priority (default - 1) : ")
            if new_details_map['priority'] == '':
                new_details_map['priority'] = "1"
                break
            try:
                if 1 <= int(new_details_map['priority']) <= 5:
                    break
                else:
                    raise Exception()

            except Exception:
                print "Test Priority should be number between 1 to 5"

        while 1:
            new_details_map['timeout'] = raw_input("Update Timeout(minutes, default - 0) : ")
            if new_details_map['timeout'] == '':
                new_details_map['timeout'] = "0"
                break
            try:
                int(new_details_map['timeout'])
                break
            except Exception:
                print "Timeout should be number"

        new_details_map['cc'] = raw_input("Update Email List : ")

        print ""
        if 'test_arg' in test_details_map:
            print "Update Test Arguments"
            new_details_map['test_arg'] = dict()
            for arg, val in test_details_map['test_arg'].iteritems():
                arg_input = raw_input("Update " + arg + " (current value - " + val  + ") : ")
                if check_required_input(arg_input):
                    new_details_map['test_arg'][arg] = arg_input
		else:
		    new_details_map['test_arg'][arg] = val

        print "*************************************************************************************************"
        print "Updated Test Information"
        print "Title          : " + new_details_map['title']
        print "Purpose        : " + new_details_map['purpose']
        print "Exec Host IP   : " + new_details_map['exec_host']
        print "Stat Hosts     : " + new_details_map['stat_host']
        print "Priority       : " + str(new_details_map['priority'])
        print "Timeout        : " + str(new_details_map['timeout'])
        print "Email List             : " + new_details_map['cc']
        print ""
        if 'test_arg' in new_details_map:
            print "Test Arguments"
            for arg, val in new_details_map['test_arg'].iteritems():
                print arg + " : " + val
        print "*************************************************************************************************"

        res = raw_input("Do you want to submit above test information ? [Y/N] : ")

        if res.lower() == "y":
            check_host_hearbeat(cl, env)

            cli_param['user'] = user
            cli_param['password'] = password

            serialize_test_details = pickle.dumps(new_details_map)

            if run == 1:
                cli_param['param'] = 'update_test|' + serialize_test_details
            elif run == 0:
                cli_param['param'] = 'update_run_test|' + serialize_test_details
            else:
                print "Unknown save and run option received, check command usage"
                sys.exit()

            serialize_cli_param = pickle.dumps(cli_param)
            retsend = cl.send(host, SERVER_PORT, env.construct("DAYTONA_CLI", serialize_cli_param))

            if retsend:
                retsend_arr = retsend.split("%")
                if len(retsend_arr) > 1:
                    response = retsend_arr[1].split("|")
                    response_type = response[0]
                    if response_type == "Error":
                        response_value = response[1]
                        print "Error occured on host"
                        print response_value
                        sys.exit()
                    elif response_type == "SUCCESS":
                        testid = response[1]
                    else:
                        print "Unknown response received from host"
                        print response
                        sys.exit()
                else:
                    print "Error - Unknown response received from host"
                    print retsend
                    sys.exit()
            else:
                print "Error - No response received from host"
                sys.exit()

            print "Success - Test updated successfully on host"
        else:
            print "Test update abort"
            sys.exit()

    elif res == "N":
        print "Test update abort"
        sys.exit()
    else:
        print "Invalid response, Test update abort"
        sys.exit()


def get_result(cl, env):
    cli_param = dict()
    cli_param['user'] = user
    cli_param['password'] = password
    cli_param['param'] = 'get_result|' + testid
    serialize_cli_param = pickle.dumps(cli_param)

    retsend = cl.send(host, SERVER_PORT, env.construct("DAYTONA_CLI", serialize_cli_param))

    if retsend:
        retsend_arr = retsend.split("%")
        if len(retsend_arr) > 1:
            response = retsend_arr[1].split("|")
            response_type = response[0]
            if response_type == "Error":
                response_value = response[1]
                print "Error occured on host"
                print response_value
                sys.exit()
            elif response_type == "SUCCESS":
                file_data = response[1]
                with open(testid + '_result.csv', 'wb') as handle:
                    handle.write(file_data)
                print "result.csv downloaded successfully"
                sys.exit()
            else:
                print "Unknown response received from host"
                print response
                sys.exit()
        else:
            print "Error - Unknown response received from host"
            print retsend
            sys.exit()
    else:
        print "Error - No response received from host"
        sys.exit()

if __name__ == "__main__":
    read_arguments()
    tcp_client = client.TCPClient(LOG.getLogger("tcpclientlog", "daytona-cli"))
    env = envelope.DaytonaEnvelope()
    check_host_hearbeat(tcp_client, env)
    if action == 0:
        define_test(tcp_client, env)
    elif action == 1:
        add_test(tcp_client, env)
    elif action == 2:
        update_test(tcp_client, env)
    elif action == 3:
        run_test(tcp_client, env)
    elif action == 5:
        get_result(tcp_client, env)
    else:
        print "Error - Invalid action"
        display_usage()
