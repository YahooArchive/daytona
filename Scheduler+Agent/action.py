#!/usr/bin/env python
# -*- coding:cp949 -*-
import subprocess
import threading
import common
import os
import time
import shutil
from shutil import copyfile
import sys
import testobj
import client
import config
import signal
import envelope
import system_metrics_gather
from logger import LOG

lctx = None
cfg = config.CFG("DaytonaHost", lctx)
cfg.readCFG("config.ini")
EXEC_SCRIPT_DIR = cfg.execscript_location

running_tests = {}
action_lock = threading.Lock()
exec_script_pid = {}
exec_script_lock = threading.Lock()

class activeTest:
    def __init__(self, testid, actionID, exec_thread, testobj):
        self.testid = testid
        self.actionID = actionID
        self.exec_thread = exec_thread
        self.tobj = testobj
        self.stream = None
        self.status = ""
        self.serverip = ""
        self.stathostip = ""
        self.serverport = 0
        self.stathostport = 0
        self.execdir = ""
        self.logdir = ""
        self.resultsdir = ""
        self.statsdir = ""
        self.archivedir = ""
        self.execscriptfile = ""
        self.hostname = ""

    def clear(self):
        lctx.info("Clearing object contents")
        self.cleanup()

    def cleanup(self):
        lctx.info("Clearing FS, processes")


class commandThread(threading.Thread):
    def __init__(self, cmdstr, dcmdstr, streamfile, cdir, testid):
        self.cmd = cmdstr
        self.dcmd = dcmdstr
        self.sfile = streamfile
        self.cwd = cdir
        self.paused = False
        self._stop = threading.Event()
        self.stdout = None
        self.stderr = None
	self.testid = testid
        threading.Thread.__init__(self)

    def resume(self):
        with self.state:
            self.paused = False
            self.state.notify()  # unblock self if waiting

    def pause(self):
        with self.state:
            self.paused = True  # make self block and wait

    def check(self):
        with self.state:
            if self.paused:
                self.state.wait()  # block until notified
            if self._stop.isSet():
                return False

    def stop(self):
        self._stop.set()

    def __del__(self):
        self._stop.set()

    def stopped(self):
        return self._stop.isSet()

    def run(self):
        lctx.debug(self.cmd)
        ca = self.cmd.split(" ")
        lctx.debug(ca)
        p = subprocess.Popen(ca, shell=False, stdout=subprocess.PIPE, stderr=subprocess.PIPE, cwd=self.cwd, preexec_fn=os.setsid)
	exec_script_lock.acquire()
        exec_script_pid[self.testid] = p
        exec_script_lock.release()
        while True:
            out = p.stdout.read(1)
            if out == '' and p.poll() is not None:
                break
            if out != '':
                sys.stdout.write(out)
                sys.stdout.flush()
                if self.sfile is not None:
                    self.sfile.flush()

        self.sfile.flush()

def get_test(testid):
    found = False
    current_test = None
    action_lock.acquire()
    if testid in running_tests:
        current_test = running_tests[testid]
        found = True
    action_lock.release()
    if found:
        return current_test
    else:
        return


def save_test(testid, test):
    found = False
    action_lock.acquire()
    if testid in running_tests:
        running_tests[testid] = test
        found = True
    action_lock.release()
    return found


def delete_test(testid):
    action_lock.acquire()
    if testid in running_tests:
        del running_tests[testid]
    action_lock.release()


def exec_cmd(cmd, daytona_cmd, sync, obj, actionid, current_test):
    lctx.debug("Execute cmd : " + cmd)
    sfile = None
    cl = None

    ########
    if daytona_cmd == "DAYTONA_START_TEST":
        cl = client.TCPClient(LOG.getLogger("clientlog", "Agent"))
        (current_test.stream, sfile) = cl.stream_start(current_test.serverip, current_test.serverport,
                                                       str(current_test.tobj.testobj.TestInputData.exec_log_path))
    ########

    if sfile is not None:
        sfile.flush()
    cthread = commandThread(cmd, daytona_cmd, sfile, current_test.execdir, current_test.testid)
    current_test.exec_thread = cthread
    cthread.start()

    (t, aid, tst, ts) = (None, None, None, None)
    if sync == "T":
        lctx.debug("Execute cmd in Sync ctx")
        cthread.join()
        if sfile is not None:
            sfile.flush()
    else:
        # async action entry in the server table (need this to check self alive below)
        for tp in obj.async_actions:
            if tp[1] == actionid:
                (t, aid, tst, ts) = tp

        lctx.debug("Execute cmd in asSync ctx : " + str(actionid))

        timer_expire = False
        while True:
            lctx.debug("waiting for async action to complete : " + str(actionid))
            if cthread.stdout is not None:
                lctx.debug("printting output of stream ")

            if sfile is not None:
                sfile.flush()

            if tst.testobj.TestInputData.timeout > 0:
                if time.time() - ts > tst.testobj.TestInputData.timeout:
                    lctx.error("Timer expired for this test, need to end this async action")
                    timer_expire = True

            # todo : breakout of while after a timer event and exit after terminating thread
            # timeout is monitored outside in the scheduler server , for all async ops , the thread is paused from
            # there via a CMD (ENDTEST)
            if t.check() == False or cthread.is_alive() == False or timer_expire:
                if daytona_cmd == "DAYTONA_START_TEST":
		    if cthread.is_alive():
                        exec_script_lock.acquire()
                        if current_test.testid in exec_script_pid:
                            p = exec_script_pid[current_test.testid]
                            del exec_script_pid[current_test.testid]
                        exec_script_lock.release()
                        if p:
                            os.killpg(p.pid, signal.SIGTERM)

                    lctx.debug("end stream")
                    cl.stream_end(current_test.serverip, current_test.serverport,
                                  str(current_test.tobj.testobj.TestInputData.exec_log_path), current_test.stream,
                                  sfile)

                # callback
                # removeactionid
                lctx.debug("Callback here removing item")
                obj.removeActionItem(actionid)
                break
            time.sleep(3)

    if daytona_cmd == "DAYTONA_START_TEST":
        # todo : verify the TEST END on filesystem OR Failure
        if timer_expire:
            current_test.status = "TIMEOUT"
        else:
            lctx.debug("Setting current test status to TESTEND")
            current_test.status = "TESTEND"

    lctx.debug(daytona_cmd + " END [" + str(actionid) + "]")

    if save_test(current_test.testid, current_test):
        return "SUCCESS"
    else:
        return "ERROR"


def log(self, *args):
    (obj, command, test_serialized, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
    lctx.debug(args)


def scheduler_handshake(current_test):
    cl = client.TCPClient(LOG.getLogger("clientlog", "Agent"))
    env = envelope.DaytonaEnvelope()
    ret = cl.send(current_test.serverip, current_test.serverport, env.construct("DAYTONA_HANDSHAKE", "handshake2"))
    if ret == "SUCCESS":
        return True
    else:
        return False


def setupTest(self, *args):
    (obj, command, params, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
    test_serialized = params.split(",")[0]
    host_type = params.split(",")[1]

    t2 = testobj.testDefn()
    t2.deserialize(test_serialized)
    current_test = get_test(t2.testobj.TestInputData.testid)
    test_logger = None

    try:
        if current_test:
	    test_logger = LOG.gettestlogger(current_test, "STAT")
            lctx.debug("TEST SETUP | " + str(current_test.testid) + " | START")
	    test_logger.info("Test setup started")
            current_test.tobj = testobj.testDefn()
            current_test.tobj = t2
            current_test.testid = current_test.tobj.testobj.TestInputData.testid

            cfg = config.CFG("DaytonaHost", lctx)
            cfg.readCFG("config.ini")
	    dir = cfg.daytona_agent_root + "/" + current_test.tobj.testobj.TestInputData.frameworkname + "/" + str(
                current_test.tobj.testobj.TestInputData.testid)
	    shutil.rmtree(dir, ignore_errors=True)
            prefix = cfg.daytona_agent_root + "/" + current_test.tobj.testobj.TestInputData.frameworkname + "/" + str(
                current_test.tobj.testobj.TestInputData.testid) + "/results/"

            if host_type == "EXEC":
                current_test.execdir = prefix + current_test.tobj.testobj.TestInputData.exechostname
                current_test.logdir = prefix + current_test.tobj.testobj.TestInputData.exechostname + "/application"

            current_test.statsdir = prefix + current_test.stathostip + "/sar/"
            current_test.resultsdir = cfg.daytona_agent_root + "/" + \
                                      current_test.tobj.testobj.TestInputData.frameworkname + "/" + \
                                      str(current_test.tobj.testobj.TestInputData.testid) + "/results"
            current_test.archivedir = cfg.daytona_agent_root + "/" + \
                                      current_test.tobj.testobj.TestInputData.frameworkname + "/" + \
                                      str(current_test.tobj.testobj.TestInputData.testid) + "/"

            if host_type == "EXEC":
                common.createdir(current_test.execdir, self.lctx)
                common.createdir(current_test.logdir, self.lctx)

            common.createdir(current_test.resultsdir, self.lctx)
            common.createdir(current_test.statsdir, self.lctx)

	    test_logger.info("Test directory created")

            # todo : check and validate if exec script is provided in expected format and
            # the file exists in that location

            if host_type == "EXEC":
                execscript = current_test.tobj.testobj.TestInputData.execution_script_location
                lctx.debug("TEST SETUP : " + str(execscript))
                current_test.execscriptfile = current_test.execdir + "/" + execscript
                lctx.debug(current_test.execscriptfile)

                # check if execution script is present in EXEC_SCRIPT_DIR - execute script only if it present at
                # this location

                execscript_location = EXEC_SCRIPT_DIR + execscript
                execscript_location = os.path.realpath(execscript_location)
                valid_path = os.path.commonprefix([execscript_location, EXEC_SCRIPT_DIR]) == EXEC_SCRIPT_DIR

                if valid_path:
                    if os.path.isfile(execscript_location):
			ret = shutil.copytree(os.path.dirname(execscript_location), os.path.dirname(current_test.execscriptfile))
                    else:
                        raise Exception(
                            "Execution script not found at Daytona Execution Script Location : " + EXEC_SCRIPT_DIR)
                else:
                    raise Exception(
                        "Access Denied : Use Daytona Execution Script Location '" + EXEC_SCRIPT_DIR + "' for executing "
                                                                                                      "exec scripts")
                os.chmod(current_test.execscriptfile, 0744)
		test_logger.info("Execution script copied successfully")

            save_test(current_test.testid, current_test)
	    test_logger.info("Test setup complete")
            lctx.debug("TEST SETUP | " + str(current_test.testid) + " | COMPLETE")
            return "SUCCESS"
        else:
            raise Exception("Invalid Test ID")

    except Exception as e:
        lctx.error(e)
	if test_logger:
            test_logger.error(e)
        return "ERROR"

    # create dirs
    # get exec script name
    # cp the exec script
    # set exec perm
    # update cur test obj with exec script
    # exec any custom setup script


def startTest(self, *args):
    (obj, command, params, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
    testid = int(params.split(",")[0])
    host_type = params.split(",")[1]
    current_test = get_test(testid)
    strace_config = None
    perf_config = dict()
    test_logger = None

    try:
        if current_test:
	    test_logger = LOG.gettestlogger(current_test, "STAT")
            lctx.debug("TESTSTART | " + str(current_test.testid) + " | START")
	    test_logger.info("Starting test")
            current_test.status = "RUNNING"
            current_test.actionID = actionID
            save_test(current_test.testid, current_test)

            if current_test.tobj.testobj.TestInputData.strace:
                strace_config = dict()
                strace_config["delay"] = str(current_test.tobj.testobj.TestInputData.strace_delay)
                strace_config["duration"] = str(current_test.tobj.testobj.TestInputData.strace_duration)
                strace_config["process"] = current_test.tobj.testobj.TestInputData.strace_process

            perf_config["delay"] = str(current_test.tobj.testobj.TestInputData.perf_delay)
            perf_config["duration"] = str(current_test.tobj.testobj.TestInputData.perf_duration)
            if current_test.tobj.testobj.TestInputData.perf_process:
                perf_config["process"] = current_test.tobj.testobj.TestInputData.perf_process

	    test_logger.info("Configuring perf profiler - " + str(perf_config))
	    if strace_config is not None:
	        test_logger.info("Configuring strace profiler - " + str(strace_config))

            system_metrics_gather.perf_strace_gather(current_test.testid, perf_config, strace_config)
	    test_logger.info("Profiler started")

            if host_type == "EXEC":
                # Copied execscript
                execscript = current_test.execscriptfile
                args = ""
                for a in current_test.tobj.testobj.TestInputData.execScriptArgs:
                    args = args + " \"" + a[3] + "\""
                execline = execscript + args
                lctx.debug("Execution line:" + execline)
		test_logger.info("Execution script started")

                # execute the exec script here
                exec_cmd(execline, command, sync, obj, actionID, current_test)

            lctx.debug("TESTSTART | " + str(current_test.testid) + " | COMPLETE")
            return "SUCCESS"
        else:
            raise Exception("Invalid Test ID")

    except Exception as e:
        lctx.error(e)
	if test_logger:
            test_logger.error(e)
        return "ERROR"


def stopTest(self, *args):
    (obj, command, params, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
    testid = int(params)
    current_test = get_test(testid)
    test_logger = None

    try:
        if current_test:
	    test_logger = LOG.gettestlogger(current_test, "STAT")
            current_test.status = "TESTEND"
            save_test(current_test.testid, current_test)
	    test_logger.info("Test stop")
            return "SUCCESS"
        else:
            raise Exception("Test not running : " + str(current_test.testid))

    except Exception as e:
        lctx.error(e)
	if test_logger:
            test_logger.error(e)
        return "ERROR"


def cleanup(self, *args):
    (obj, command, params, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
    testid = int(params)
    current_test = get_test(testid)
    test_logger = None
    try:
        if current_test:
	    test_logger = LOG.gettestlogger(current_test, "STAT")
            lctx.debug("CLEANUP | " + str(current_test.testid) + " | START")
	    test_logger.info("Test cleanup")
	    downloadTestLogs(testid)
	    LOG.removeLogger(current_test.tobj)
            shutil.rmtree(current_test.resultsdir, ignore_errors=True)
	    delete_test(testid)
            lctx.debug("CLEANUP | " + str(current_test.testid) + " | COMPLETE")
            return "SUCCESS"
        else:
            raise Exception("Invalid Test ID")

    except Exception as e:
        lctx.error(e)
	if test_logger:
            test_logger.error(e)
        return "ERROR"


def abortTest(self, *args):
    (obj, command, params, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
    testid = int(params)
    current_test = get_test(testid)
    t2 = current_test.tobj

    (t, aid, tst, ts) = (None, None, None, None)

    lctx.debug(args)

    abort_action = False

    for tp in obj.async_actions:
        (t, aid, tst, ts) = tp
        if tst.testobj.TestInputData.testid == t2.testobj.TestInputData.testid:
            lctx.debug("Found ASYNC action pending for this test, Aborting it")
            abort_action = True
            break

    if abort_action:
        t.stop()
        t.join()
        lctx.debug("Stopped ASYNC action pending for this test : " + str(tst.testobj.TestInputData.testid))
    else:
        lctx.debug("No ASYNC action pending for this test : " + str(t2.testobj.TestInputData.testid))

    cleanup(self, self, command, params, actionID, sync)
    lctx.debug(command + "[" + str(actionID) + "]")
    return "SUCCESS"


def heartbeat(self, *args):
    return "ALIVE"


def setFinish(self, *args):
    (obj, command, params, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
    testid = int(params)
    cleanup(self, self, command, params, actionID, sync)
    return "SUCCESS"


def getStatus(self, *args):
    (obj, command, params, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
    testid = int(params)
    current_test = get_test(testid)
    if current_test:
	test_logger = LOG.gettestlogger(current_test, "STAT")
        lctx.debug(str(current_test.testid) + ":" + current_test.status)
	test_logger.info("Test Status : " + current_test.status)
        return current_test.status
    else:
        return "TESTNA"


def fileDownload(self, *args):
    cl = client.TCPClient(LOG.getLogger("clientlog", "Agent"))
    testid = int(args[2])
    current_test = get_test(testid)
    test_logger = None
    try:
        if current_test:
	    test_logger = LOG.gettestlogger(current_test, "STAT")
            lctx.debug("FILE DOWNLOAD | " + str(current_test.testid) + " | START")
	    lctx.debug("Preparing TAR file of system metric folder")
	    test_logger.info("Preparing TAR file of system metric folder")
            common.make_tarfile(current_test.archivedir + "results.tgz", current_test.resultsdir + "/")
            dest = current_test.tobj.testobj.TestInputData.stats_results_path[current_test.stathostip]
            download_file = current_test.archivedir + "results.tgz"
	    test_logger.info("Sending TAR file to daytona host")
            cl.sendFile(current_test.serverip, current_test.serverport, download_file, dest.strip())
            lctx.debug("FILE DOWNLOAD | " + str(current_test.testid) + " | COMPLETE")
            return "SUCCESS"
        else:
            raise Exception("Invalid Test ID")

    except Exception as e:
        lctx.error(e)
	if test_logger:
	    test_logger.error(e)
        return "ERROR"


def downloadTestLogs(testid):
    cl = client.TCPClient(LOG.getLogger("clientlog", "Agent"))
    current_test = get_test(testid)
    test_logger = None
    try:
        if current_test:
	    test_logger = LOG.gettestlogger(current_test, "STAT")
	    test_logger.info("Sending test log to daytona host")
            dest = current_test.tobj.testobj.TestInputData.stats_results_path[current_test.stathostip]
            download_file = current_test.agent_log_file
            cl.sendFile(current_test.serverip, current_test.serverport, download_file, dest.strip())
	    test_logger.info("Test log file transfer complete")
            return "SUCCESS"
        else:
            raise Exception("Invalid Test ID")

    except Exception as e:
        lctx.error(e)
	if test_logger:
            test_logger.error(e)
        return "ERROR"

