#!/usr/bin/env python
# -*- coding:cp949 -*-

import threading
import socket
import time
from collections import defaultdict
import traceback
from dbcli_action import dbCliHandle
import pickle
import dbaccess
import server
import config
import client
import envelope
import common
import testobj
from logger import LOG
import csv
import process_top


class Scheduler:
    scheduler_thread = None
    testmon_thread = None
    lock = threading.Lock()
    dispatchQ__lock = threading.Lock()
    running_tests = defaultdict()
    dispatch_queue = defaultdict()

    def __init__(self, db, cfg, lctx):
        self.dbinstance = db
        self.cfg = cfg
        self.testmap = db.tests_to_run
        self.cl = client.TCPClient(LOG.getLogger("clientlog", "DH"))
        self.ev = envelope.DaytonaEnvelope()
        self.HOST = common.get_local_ip()
        self.PORT = cfg.DHPORT
        self.CPORT = cfg.CPORT

        self.scheduler_thread = common.FuncThread(self.dispatch, True)
        self.testmon_thread = common.FuncThread(self.testmon, True)
        self.lctx = lctx


    def process_results(self, *args):
        # set test status completed
        # call stop monitors
        # send prepare results command to exec
        # set test status collating
        # copy results files from exec
        # copy files from each mon
        # set test status finished
        # remove test from running Q
        t = args[1]
        status = args[2]

        serialize_str = t.serialize();
        t2 = testobj.testDefn()
        t2.deserialize(serialize_str)

        try:
            if t.testobj.TestInputData.testid != t2.testobj.TestInputData.testid:
                lctx.error("testobj not same")
                raise Exception("Test objects do not match : ", t2.testobj.TestInputData.testid)

            if t.testobj.TestInputData.timeout_flag:
                t.updateStatus("timeout", "collating")
            else:
                t.updateStatus("completed", "collating")

            ip = t.testobj.TestInputData.exechostname
            lctx.debug(status)
            if status in ["completed", "timeout"]:
                retsend = self.cl.send(ip, self.CPORT,
                                       self.ev.construct("DAYTONA_STOP_MONITOR", str(t2.testobj.TestInputData.testid)))
                lctx.debug(retsend)
                if retsend.split(",")[1] != "SUCCESS":
                    lctx.error(retsend)

                ptop = process_top.ProcessTop(LOG.getLogger("processTop", "DH"))
                # todo : avoid send client its own ip
                lctx.info("SENDING results.tgz download to : " + ip + ":" + str(self.CPORT))
                retsend = self.cl.send(ip, self.CPORT,
                                       self.ev.construct("DAYTONA_FILE_DOWNLOAD", str(t2.testobj.TestInputData.testid)))
                lctx.debug(retsend)
                if retsend.split(",")[1] != "SUCCESS":
                    lctx.error(retsend)
                try:
                    lctx.debug(
                        "Untar file : " + t2.testobj.TestInputData.exec_results_path + "results.tgz to location : " + t2.testobj.TestInputData.exec_results_path + "/../")
                    common.untarfile(t2.testobj.TestInputData.exec_results_path + "/results.tgz",
                                     t2.testobj.TestInputData.exec_results_path + "/../")
                except Exception as e:
                    lctx.error("Error in untar EXEC host results")
                    lctx.error(e)

                ptop_ret = ptop.process_top_output(t2.testobj.TestInputData.stats_results_path[ip] + "sar/")
                lctx.debug(ptop_ret + " : " + t2.testobj.TestInputData.stats_results_path[ip])

                retsend = self.cl.send(ip, self.CPORT,
                                       self.ev.construct("DAYTONA_FINISH_TEST", str(t2.testobj.TestInputData.testid)))
                lctx.debug(retsend)

                for s in t.testobj.TestInputData.stathostname.split(','):
                    if len(s.strip()) == 0:
                        break
                    
                    # stop stats monitors on req hosts
                    # any host that blocks stop monitor blocks the scheduling for the FW

                    p = self.CPORT
                    try:
                        lctx.info("Stop Monitor on stat host : " + s)
                        retsend = self.cl.send(s.strip(), p, self.ev.construct("DAYTONA_STOP_MONITOR",
                                                                               str(t2.testobj.TestInputData.testid)))
                        lctx.debug(retsend)
                        if retsend.split(",")[1] != "SUCCESS":
                            lctx.error(retsend)
                        
                        lctx.info("Sending results.tgz download to :" + s.strip() + ":" + str(p))
                        retsend = self.cl.send(s.strip(), p, self.ev.construct("DAYTONA_FILE_DOWNLOAD",
                                                                               str(t2.testobj.TestInputData.testid)))
                        lctx.debug(retsend)
                        if retsend.split(",")[1] != "SUCCESS":
                            lctx.error("Error downloading STATS from " + s.strip() + ":" + retsend)
                     
                        lctx.debug("Untar file : " + t2.testobj.TestInputData.stats_results_path[
                            s] + "results.tgz to location : " + t2.testobj.TestInputData.stats_results_path[s] + "/../")
                        common.untarfile(t2.testobj.TestInputData.stats_results_path[s] + "/results.tgz",
                                         t2.testobj.TestInputData.stats_results_path[s] + "/../")
                
                        ptop_ret = ptop.process_top_output(t2.testobj.TestInputData.stats_results_path[s] + "sar/")
                        lctx.debug(ptop_ret + " : " + t2.testobj.TestInputData.stats_results_path[s])
                
                        retsend = self.cl.send(s.strip(), p, self.ev.construct("DAYTONA_FINISH_TEST",
                                                                           str(t2.testobj.TestInputData.testid)))
                        lctx.debug(retsend)
                    except Exception as e:
                        lctx.error(e)
                        continue

        except Exception as e:
            lctx.error("Error in processing results")
            lctx.error(e)
            t.updateStatus("collating", "failed")

	if t.testobj.TestInputData.timeout_flag:
	    t.updateStatus("collating", "timeout clean")
	else:
            t.updateStatus("collating", "finished clean")

        now = time.time()
        tstr = str(time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime(now)))
        t.updateEndTime(tstr)
	f = None
	try:
            f = open(t2.testobj.TestInputData.exec_results_path + "/results.csv")
	except IOError as e:
	    lctx.debug("File results.csv not found")
	    pass

        to = t.testobj.TestInputData.email
	htmlfile = '<table>'
	if f:
            reader = csv.reader(f)
            rownum = 0
            for row in reader:
                if rownum == 0:
                    htmlfile += '<tr>'
                    for column in row:
                        htmlfile += '<th style="text-align: left;" width="70%">' + column + '</th>'
                    htmlfile += '</tr>'
                else:
                    htmlfile += '<tr>'
                    for column in row:
                        htmlfile += '<td style="text-align: left;" width="70%">' + column + '</td>'
                    htmlfile += '</tr>'
                rownum += 1
	    f.close()

	htmlfile += '</table>'
        host_ip = "http://" + common.get_local_ip() + "/test_info.php?testid=" + str(t.testobj.TestInputData.testid)

        subject = "Test {} completed successfully".format(t.testobj.TestInputData.testid)

        mail_content = "<BR> Test id : {} \
                   <BR> Framework : {} \
                   <BR> Title : {} <BR>".format(t.testobj.TestInputData.testid,
                                                t.testobj.TestInputData.frameworkname, t.testobj.TestInputData.title)

        mail_content = mail_content + "<BR>==========================================================<BR>"
        mail_content = mail_content + "<BR>Purpose : {} <BR> \
                                  <BR> Creation time : {} \
                                  <BR>Start time : {} \
                                  <BR>End time : {} <BR>".format(t.testobj.TestInputData.purpose,
                                                                 t.testobj.TestInputData.creation_time,
                                                                 t.testobj.TestInputData.start_time,
                                                                 t.testobj.TestInputData.end_time)
        mail_content = mail_content + "<BR>Your test executed successfully. \
                                   <BR>Results (Contents of results.csv)<BR>"
        mail_content = mail_content + "<BR>==========================================================<BR>"
        mail_content = mail_content + "<BR>" + htmlfile + "<BR>"
        mail_content = mail_content + "<BR>==========================================================<BR>"
        mail_content = mail_content + "Link:"
        mail_content = mail_content + '<BR><a href="' + host_ip + '">' + host_ip +'</a>'

        try:
            common.send_email(subject, to, mail_content, "", lctx, cfg.email_user, cfg.email_server, cfg.smtp_server,
                              cfg.smtp_port)
        except:
            lctx.error("Mail send error")

        return "SUCCESS"


    def trigger(self, *args):
        # trigger starts in a thread, keep track of all triggers and they should complete in a specified time,
        # otherwise signal a close
        t = args[1]

        serialize_str = t.serialize();
        t2 = testobj.testDefn()
        t2.deserialize(serialize_str)
        time.sleep(6)

        track_monitor = []

        try:
            if t.testobj.TestInputData.testid != t2.testobj.TestInputData.testid:
                lctx.error("testobj not same")
                raise Exception("test trigger error", t2.testobj.TestInputData.testid)

            ip = t.testobj.TestInputData.exechostname
            retsend = self.cl.send(ip, self.CPORT, self.ev.construct("DAYTONA_SETUP_TEST", serialize_str + ",EXEC"))
            lctx.debug(retsend)

            if retsend and len(retsend.split(",")) > 1:
                if retsend.split(",")[1] != "SUCCESS":
                    lctx.error(retsend)
                    raise Exception("test trigger error", t2.testobj.TestInputData.testid)
            else:
                raise Exception("Test Setup Failure : Test -  ", t2.testobj.TestInputData.testid)

            retsend = self.cl.send(ip, self.CPORT,
                                   self.ev.construct("DAYTONA_START_MONITOR", str(t2.testobj.TestInputData.testid)))
            lctx.debug(retsend)
            if retsend and len(retsend.split(",")) > 1:
                if retsend.split(",")[1] != "SUCCESS":
                    lctx.error(retsend)
                    raise Exception("test trigger error", t2.testobj.TestInputData.testid)
            else:
                raise Exception("Test monitor start failure : Test -  ", t2.testobj.TestInputData.testid)

            track_monitor.append(ip)

            # get statistics hosts
            for s in t.testobj.TestInputData.stathostname.split(','):
                if len(s.strip()) == 0:
                    break
                lctx.debug("Start monitor")
                lctx.debug(s.strip())
                p = self.CPORT

                retsend = self.cl.send(s, p, self.ev.construct("DAYTONA_HEARTBEAT", ""))

                if retsend and len(retsend.split(",")) > 1:
                    if retsend.split(",")[1] != "ALIVE":
                        raise Exception("Remove host not avaliable - No Heartbeat ", t2.testobj.TestInputData.testid)
                    else:
                        retsend = self.cl.send(s, p,
                                               self.ev.construct("DAYTONA_HANDSHAKE", self.HOST + "," + str(self.PORT) + "," + str(t2.testobj.TestInputData.testid) + "," + s))
                        lctx.debug(retsend)
                        if retsend == "SUCCESS":
                            alive = True
                            server.serv.registered_hosts[s] = s
                            addr = socket.gethostbyname(s)
                            server.serv.registered_hosts[addr] = addr
                        else:
                            raise Exception("Unable to handshake with agent on stats host:" + s,
                                            t2.testobj.TestInputData.testid)
		else:
                    raise Exception("Remove host not avaliable - No Heartbeat ", t2.testobj.TestInputData.testid)

                # start stats monitors on req hosts
                # any host that blocks start monitor blocks the scheduling for the FW

		retsend = self.cl.send(s.strip(), p, self.ev.construct("DAYTONA_SETUP_TEST", serialize_str + ",STAT"))
                lctx.debug(retsend)

                if retsend and len(retsend.split(",")) > 1:
                    if retsend.split(",")[1] != "SUCCESS":
                        lctx.error(retsend)
                        raise Exception("test trigger error", t2.testobj.TestInputData.testid)
                else:
                    raise Exception("Test Setup Failure : Test -  ", t2.testobj.TestInputData.testid)

                retsend = self.cl.send(s.strip(), p,
                                       self.ev.construct("DAYTONA_START_MONITOR", str(t2.testobj.TestInputData.testid)))
                lctx.debug(retsend)

                if retsend and len(retsend.split(",")) > 1:
                    if retsend.split(",")[1] != "SUCCESS":
                        lctx.error(retsend)
                        raise Exception("test trigger error", t2.testobj.TestInputData.testid)
                else:
                    raise Exception("Test monitor start failure : Test -  ", t2.testobj.TestInputData.testid)

                track_monitor.append(s.strip())

            # Trigger the start of test to test box
            retsend = self.cl.send(t.testobj.TestInputData.exechostname, self.CPORT,
                                   self.ev.construct("DAYTONA_START_TEST", str(t2.testobj.TestInputData.testid)))
            lctx.debug(retsend)
            if retsend and len(retsend.split(",")) > 1:
                if retsend.split(",")[1] != "SUCCESS":
                    lctx.error(retsend)
                    raise Exception("test trigger error", t2.testobj.TestInputData.testid)
	    else:
                raise Exception("Failed to start Test : ", t2.testobj.TestInputData.testid)

            # Get status from tst box
            retsend = self.cl.send(t.testobj.TestInputData.exechostname, self.CPORT,
                                   self.ev.construct("DAYTONA_GET_STATUS", str(t2.testobj.TestInputData.testid)))

            if retsend and len(retsend.split(",")) > 1:
                if "RUNNING" == retsend.split(",")[1] or "MONITOR_ON" == retsend.split(",")[1]:
                    # update from setup to running
                    lctx.debug("Updating test status to running in DB")
                    t.updateStatus("setup", "running")
                    now = time.time()
                    tstr = str(time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime(now)))
                    t.updateStartTime(tstr)
		    self.dispatchQ__lock.acquire()
                    del self.dispatch_queue[t.testobj.TestInputData.frameworkid]
                    self.dispatchQ__lock.release()
		    self.lock.acquire()
                    self.running_tests[t.testobj.TestInputData.frameworkid] = t
                    self.lock.release()
                else:
                    lctx.error("Unable to determine status, testmon will garbage collect this testid")
                    # Garbage collect testid in runnning state that fails to give status
                    # Killing of threads on client host and remove from list with status=fail is done in testmon

        except Exception as e:
	    lctx.error(e)
            lctx.error("ERROR : Unknown trigger error : " + str(t.testobj.TestInputData.testid))
            t.updateStatus("setup", "failed")
            lctx.debug(traceback.print_exc())
            lctx.error("Removing Test " + str(t.testobj.TestInputData.testid) + " from dispatch Q")
            self.dispatchQ__lock.acquire()
            del self.dispatch_queue[t.testobj.TestInputData.frameworkid]
            self.dispatchQ__lock.release()

            if ip in track_monitor:
                retsend = self.cl.send(ip, self.CPORT,
                                       self.ev.construct("DAYTONA_STOP_MONITOR", str(t2.testobj.TestInputData.testid)))
                lctx.debug(retsend)

            retsend = self.cl.send(ip, self.CPORT,
                                   self.ev.construct("DAYTONA_ABORT_TEST", str(t2.testobj.TestInputData.testid)))
            lctx.debug(retsend)

            for s in t.testobj.TestInputData.stathostname.split(','):
                if len(s.strip()) == 0:
                    break
                if s.strip() in track_monitor:
                    retsend = self.cl.send(s.strip(), self.CPORT,
                                           self.ev.construct("DAYTONA_STOP_MONITOR",
                                                             str(t2.testobj.TestInputData.testid)))
                    lctx.debug(retsend)
                retsend = self.cl.send(s.strip(), self.CPORT, self.ev.construct("DAYTONA_CLEANUP_TEST", str(t2.testobj.TestInputData.testid)))
                lctx.debug(retsend)
            return "FAILED"

        return "SUCCESS"

    def __del__(self):
        self.testmon_thread.stop()
        self.scheduler_thread.stop()

    def dispatch(self, *args):
        dispatch_threads = defaultdict()
        while True:
            for k in self.testmap:
                found = False
		
		if k in self.dispatch_queue or k in self.running_tests:
                    found = True
                else:
                    lctx.debug("Found spot for test")

                if found:
                    continue

                try:
                    tmp_t = self.testmap[k][0]
                except Exception as e:
                    lctx.debug("No test object found in map")
                    continue

                if tmp_t is None:
                    continue

                alive = False

                h = tmp_t.testobj.TestInputData.exechostname
                try:
                    retsend = self.cl.send(h, self.CPORT, self.ev.construct("DAYTONA_HEARTBEAT", ""))

                    if retsend and len(retsend.split(",")) > 2:
                        status = retsend.split(",")[1]
                    else:
                        raise Exception("Remove host not avaliable - No Heartbeat ", tmp_t.testobj.TestInputData.testid)

                    if "ALIVE" == status:
                        ret = self.cl.send(h, self.CPORT,
                                           self.ev.construct("DAYTONA_HANDSHAKE", self.HOST + "," + str(self.PORT) + "," + str(tmp_t.testobj.TestInputData.testid) + "," + h))
                        if ret == "SUCCESS":
                            alive = True
                            lctx.debug("Handshake successful in scheduler, adding ip/hostname to reg hosts")
                            server.serv.registered_hosts[h] = h
                            addr = socket.gethostbyname(h)
                            lctx.debug(addr)
                            server.serv.registered_hosts[addr] = addr
                        else:
                            raise Exception("Unable to handshake with agent:" + h)

		except Exception as e:
                    lctx.error(e)
                    # pause the dbmon here as we dont want the same test to be picked again after we pop
                    self.dbinstance.mon_thread[0].pause()
                    self.dbinstance.lock.acquire()
                    t = self.testmap[k].pop(0)
                    t.updateStatus("waiting", "failed")
                    self.dbinstance.lock.release()
                    lctx.debug("Removed test from map : " + str(t.testobj.TestInputData.testid))
                    self.dbinstance.mon_thread[0].resume()
                    continue
                    # todo : add host to reg list if handshake successful

                if alive == True and found == False:
                    # for each framework pick one and move it to running, iff running has an empty slot.
                    lctx.debug("-------Found empty slot in dispatch and running Q-------")

                    # pause the dbmon here as we dont want the same test to be picked again after we pop
                    self.dbinstance.mon_thread[0].pause()
                    self.dbinstance.lock.acquire()
                    t = self.testmap[k].pop(0)
                    self.dbinstance.lock.release()

                    lctx.info("< %s" % t.testobj.TestInputData.testid)
		
		    self.dispatchQ__lock.acquire()
                    self.dispatch_queue[k] = t
                    self.dispatchQ__lock.release()

                    t.updateStatus("waiting", "setup")
                    self.dbinstance.mon_thread[0].resume()

                    try:
                        trigger_thread = common.FuncThread(self.trigger, True, t)
                        dispatch_threads[t.testobj.TestInputData.testid] = (trigger_thread, t)
                        trigger_thread.start()
                    except Exception as e:
                        lctx.error("Trigger error : " + str(t.testobj.TestInputData.testid))
			self.dispatchQ__lock.acquire()
                        del self.dispatch_queue[k]
                        self.dispatchQ__lock.release()
                        lctx.debug(e)

	    try:
                d = "DISPATCH [S/R] : "
                for k in self.dispatch_queue:
                    d = d + " |" + str(self.dispatch_queue[k].testobj.TestInputData.testid)
            except:
                lctx.debug("ERROR : Dispatch Q empty")

            lctx.debug(d)
            d = ""

            time.sleep(2)


    def testmon(self, *mon):
        process_results_threads = defaultdict()
        while True:
            d = "TSMON [R] : |"
            remove = False
            error = False

            for k in self.running_tests:
                if self.running_tests[k] is not None:
                    t = self.running_tests[k]

                    serialize_str = t.serialize();
                    t2 = testobj.testDefn()
                    t2.deserialize(serialize_str)
                    if t.testobj.TestInputData.testid != t2.testobj.TestInputData.testid:
                        lctx.error("testobj not same")
                        t.updateStatus("running", "failed")
                        remove = True
                        break  # out of for loop

                    try:
                        ret = self.cl.send(t.testobj.TestInputData.exechostname, self.CPORT,
                                           self.ev.construct("DAYTONA_GET_STATUS",
                                                             str(t2.testobj.TestInputData.testid)))
                        status = ret.split(",")[1]
                        lctx.debug(status)
                    except Exception as e:
                        lctx.debug(e)
                        t.updateStatus("running", "failed")
                        error = True
                        break  # out of for loop

                    if status == "RUNNING":
                        found = checkTestRunning(t.testobj.TestInputData.testid)
                        if not found:
                            error = True
                            break
                        d = d + str(self.running_tests[k].testobj.TestInputData.testid) + "|"
                    elif status in ["TESTEND", "TIMEOUT"]:
                        d = d + "*" + str(self.running_tests[k].testobj.TestInputData.testid) + "*|"
                        if t.testobj.TestInputData.end_status == "running":
                            lctx.debug(t.testobj.TestInputData.end_status)
                            if status == "TIMEOUT":
                                t.testobj.TestInputData.timeout_flag = True
                                t.updateStatus("running", "timeout")
                            else:
                                t.updateStatus("running", "completed")

                            pt = common.FuncThread(self.process_results, True, t,
                                                   t.testobj.TestInputData.end_status)
                            process_results_threads[t.testobj.TestInputData.testid] = (pt, t)
                            pt.start()
                            remove = True
                            break
                        elif t.testobj.TestInputData.end_status == "collating" or t.testobj.TestInputData.end_status == "completed" or t.testobj.TestInputData.end_status == "finished clean":
                            d = d + "*" + str(self.running_tests[k].testobj.TestInputData.testid) + "*|"
                        else:
                            remove = True
                            t.updateStatus("running", "failed")
                            lctx.error(
                                "ERROR : Unknown test status for : " + str(t.testobj.TestInputData.testid) + ":" + str(
                                    status))
                            break  # out of for loop
                            
                    elif status.strip() in ["FAILED", "TESTNA"]:
                        if status.strip() == "FAILED":
                            error = True
                        elif status.strip() in ["ABORT", "TESTNA"]:
                            remove = True
                        t.updateStatus("", "failed")
                        lctx.error("TEST " + status.strip() + " : Cleaning test from running queue")
                        break  # out of for loop
                    else:
                        remove = True
                        t.updateStatus("running", "failed")
                        lctx.error(
                            "ERROR : Unknown test status for : " + str(t.testobj.TestInputData.testid) + ":" + str(
                                status))
                        break  # out of for loop

                lctx.info(d)
                d = ""

            if error:
                retsend = None
                ip = t.testobj.TestInputData.exechostname
                try:
                    retsend = self.cl.send(ip, self.CPORT, self.ev.construct("DAYTONA_HEARTBEAT", ""))
                except:
                    pass

                if retsend and retsend.split(",")[1] == "ALIVE":
                    retsend = self.cl.send(ip, self.CPORT,
                                           self.ev.construct("DAYTONA_STOP_MONITOR",
                                                             str(t.testobj.TestInputData.testid)))
                    retsend = self.cl.send(ip, self.CPORT,
                                           self.ev.construct("DAYTONA_ABORT_TEST", str(t.testobj.TestInputData.testid)))
                for s in t.testobj.TestInputData.stathostname.split(','):
                    if len(s.strip()) == 0:
                        break
                    try:
                        retsend = self.cl.send(s.strip(), self.CPORT, self.ev.construct("DAYTONA_HEARTBEAT", ""))
                    except:
                        pass
                    if retsend and retsend.split(",")[1] == "ALIVE":
                        retsend = self.cl.send(s.strip(), self.CPORT,
                                               self.ev.construct("DAYTONA_STOP_MONITOR",
                                                                 str(t.testobj.TestInputData.testid)))

                        retsend = self.cl.send(s.strip(), self.CPORT,
                                               self.ev.construct("DAYTONA_CLEANUP_TEST",
                                                                 str(t.testobj.TestInputData.testid)))

                self.lock.acquire()
                for k in self.running_tests:
                    if self.running_tests[k].testobj.TestInputData.testid == t.testobj.TestInputData.testid:
                        lctx.debug("removing entry for this test")
                        rt = self.running_tests.pop(k)
                        break
                if k in self.running_tests:
                    del self.running_tests[k]
                self.lock.release()

            if remove:
                self.lock.acquire()
                for k in self.running_tests:
                    if self.running_tests[k].testobj.TestInputData.testid == t.testobj.TestInputData.testid:
                        lctx.debug("removing entry for this test")
                        rt = self.running_tests.pop(k)
                        break
                if k in self.running_tests:
                    del self.running_tests[k]
                self.lock.release()

            time.sleep(2)


def daytonaCli(self, *args):
    (obj, command, params, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
    lctx = LOG.getLogger("scheduler-clilog", "DH")

    cli_param_map = pickle.loads(params)
    if len(cli_param_map) != 3:
        return "Error|Not enough arguments"

    user = cli_param_map['user']
    password = cli_param_map['password']

    cli_command = cli_param_map['param'].split("|")[0]
    cli_param = cli_param_map['param'].split("|")[1]

    lctx.debug("Host received CLI command : " + cli_command)

    db = dbCliHandle()

    auth = db.authenticate_user(user, password)
    if auth != "SUCCESS":
        return auth

    if cli_command == "get_frameworkid_arg":
        arglist = db.getFrameworkIdArgs(cli_param)
        return arglist
    elif cli_command == "get_framework_arg":
        arglist = db.getFrameworkArgs(cli_param)
        return arglist
    elif cli_command == "add_test":
        res = db.addTest(cli_param, user)
        return res
    elif cli_command == "add_run_test":
        res = db.addTest(cli_param, user, 'scheduled')
        if res:
            if res.split("|")[0] == 'Error':
                return res
            else:
                testid = res.split("|")[1]
        else:
            return "Error|Test add failed"

        res = db.runTest(testid, user)
        if res.split("|")[0] == 'SUCCESS':
            return "SUCCESS|" + testid
        else:
            return res
    elif cli_command == "run_test":
        res = db.runTest(cli_param, user)
        return res
    elif cli_command == "get_result":
        res = db.getResult(cli_param, user)
        return res
    elif cli_command == "get_test_by_id":
        res = db.getTestByID(cli_param)
        return res
    elif cli_command == "update_test":
        res = db.updateTest(cli_param, user)
        return res
    elif cli_command == "update_run_test":
        res = db.updateTest(cli_param, user, 'scheduled')
        if res:
            if res.split("|")[0] == 'Error':
                return res
            else:
                testid = res.split("|")[1]
        else:
            return "Error|Test update failed"
        res = db.runTest(testid, user)
        if res.split("|")[0] == 'SUCCESS':
            return "SUCCESS|" + testid
        else:
            return res
    else:
        return "Error|Unknown command received on server"


def checkTestRunning(testid):
    lctx = LOG.getLogger("dblog", "DH")
    cfg = config.CFG("DaytonaHost", lctx)
    cfg.readCFG("config.ini")
    db = dbaccess.DBAccess(cfg, LOG.getLogger("dblog", "DH"))
    check = db.query("""SELECT COUNT(*) FROM CommonFrameworkSchedulerQueue where testid=%s""", (testid,), False, False);
    db.close()
    if check[0] == 0:
        return False
    else:
        return True


if __name__ == "__main__":
    # Port 0 means to select an arbitrary unused port

    lctx = LOG.getLogger("schedulerlog", "DH")
    cfg = config.CFG("DaytonaHost", lctx)
    cfg.readCFG("config.ini")

    common.logger.ROLE = "DHOST"
    db = dbaccess.DaytonaDBmon(cfg, LOG.getLogger("dblog", "DH"))
    sch = Scheduler(db, cfg, LOG.getLogger("schedulerlog", "DH"))

    server.serv.role = "DH"
    ase_serv = server.serv()
    server.serv.lctx = LOG.getLogger("listenerlog", "DH")
    ser = server.serv.ThreadedTCPServer((common.get_local_ip(), sch.PORT), server.serv.ThreadedTCPRequestHandler)
    server.serv.serverInstance = ser
    ip, port = ser.server_address
    server.lctx = LOG.getLogger("listenerlog", "DH")

    # Start a thread with the server -- that thread will then start one
    # more thread for each request
    server_thread = threading.Thread(target=ser.serve_forever)

    # Exit the server thread when the main thread terminates
    server_thread.daemon = True
    server_thread.start()
    lctx.info("Server loop running in thread:" + server_thread.name)
    lctx.info("Server started @ %s:%s" % (ip, port))

    sch.scheduler_thread.start()
    time.sleep(5)  # wait for 5 secs to dispatch DS to be loaded
    sch.testmon_thread.start()

    server_thread.join()
    lctx.info("Server thread ended")
    lctx.info("DB Mon thread ended")
    lctx.info("Schedule thread ended")

    # Scheduler
    # Start listener
    # Start DB mon : DB is checked and "ready to run" is picked and maintained in a hash-q (test obj is cobstructed in DB mon and put in Q):
    # Start client check thread: loop thru all "current exec" list and fetch status, take action on each status, update DB with finished
    # Start loop to pick items from DB_MON.hash-q and process , set DB to "running"
    # Fork out parallel exec for each framework in Q and add in "current exec"
    # USE DB to get FW args, details, params and setup tests (the process)

