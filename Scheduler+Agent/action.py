#!/usr/bin/env python
# -*- coding:cp949 -*-
import time
import subprocess
import threading
import common
from common import CommunicationError
import os
import time
import shutil
from shutil import copyfile
import sys
import traceback

import testobj
import client
import config
import envelope
from logger import LOG


lctx = None
class activeTest():
  def __init__(self, testid, actionID, exec_thread, testobj):
    self.testid = testid
    self.actionID = actionID
    self.exec_thread = exec_thread
    self.tobj = testobj
    self.stream = None
    self.status = ","
    self.serverip = ""
    self.serverport = 0
    self.execdir = ""
    self.logdir = ""
    self.resultsdir = ""
    self.statsdir = ""
    self.archivedir = ""
    self.execscriptfile = ""
    self.hostname = ""

  def clear():
    lctx.info("Clearing object contents")
    self.cleanup()

  def cleanup():
    lctx.info("Clearing FS, processes")

current_test = activeTest(0,None,None,None)

#todo : sub class
class commandThread(threading.Thread):
  def __init__(self, cmdstr, dcmdstr, streamfile, cdir):
    self.cmd = cmdstr
    self.dcmd = dcmdstr
    self.sfile  = streamfile
    self.cwd = cdir
    self.paused = False
    self._stop = threading.Event()
    self.stdout = None
    self.stderr = None
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
        self.state.wait() # block until notified
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
    if self.dcmd == "DAYTONA_START_TEST" :
      ca = self.cmd.split(" ")
      lctx.debug(ca)
      p = subprocess.Popen(ca, shell=False, stdout=subprocess.PIPE, stderr=subprocess.PIPE, cwd=self.cwd)
      #self.stdout, self.stderr = p.communicate()
      while True:
        out = p.stdout.read(1)
        if out == '' and p.poll() != None:
          break
        if out != '':
          sys.stdout.write(out)
          sys.stdout.flush()
          if self.sfile is not None:
            self.sfile.flush()

      for i in range(0,15):
        print "-----------------------------------------------------------------------"
        time.sleep(1)
        if self.sfile is not None:
          self.sfile.flush()
    else:
      ca = self.cmd.split(" ")
      lctx.debug(ca)
      p = subprocess.Popen(ca, shell=False, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
      self.stdout, self.stderr = p.communicate()

def exec_cmd(cmd, daytona_cmd, sync, obj, actionid):
  lctx.debug("Execute cmd : " + cmd)
  sfile = None
  cl = None
  ########
  if daytona_cmd == "DAYTONA_START_TEST" :
    cl = client.TCPClient(LOG.getLogger("clientlog", "Agent"))
    (current_test.stream, sfile)  = cl.stream_start(current_test.serverip , current_test.serverport ,  str(current_test.tobj.testobj.TestInputData.exec_log_path))
  ########

  if sfile is not None:
    sfile.flush()
  cthread = commandThread(cmd, daytona_cmd, sfile, current_test.execdir)
  current_test.exec_thread = cthread
  cthread.start()

  (t, aid, tst, ts)=(None, None, None, None)
  if sync == "T":
    lctx.debug( "Execute cmd in Sync ctx")
    cthread.join()
    #print cthread.stdout
    if sfile is not None:
      sfile.flush()
  else:
    #async action entry in the server table (need this to check self alive below)
    for tp in obj.async_actions:
      if tp[1] == actionid:
          #todo use tp
        #(t, aid, tst, ts) = obj.async_actions[0]
        (t, aid, tst, ts) = tp

    lctx.debug("Execute cmd in asSync ctx : " + str(actionid))

    timer_expire = False
    while True:
      lctx.debug("waiting for async action to complete : " + str(actionid))
      if cthread.stdout is not None:
        lctx.debug("printting output of stream ")
        #print cthread.stdout

      if sfile is not None:
        sfile.flush()

      if time.time() - ts > tst.testobj.TestInputData.timeout :
        lctx.error("Timer expired for this test, need to end this async action")
        timer_expire = True

      #todo : breakout of while after a timer event and exit after terminating thread
      #timeout is monitored outside in the scheduler server , for all async ops , the thread is paused from there via a CMD (ENDTEST)
      if t.check() == False or cthread.is_alive() == False:
        if daytona_cmd == "DAYTONA_START_TEST" :
          lctx.debug("end stream")
          cl.stream_end(current_test.serverip , current_test.serverport , str(current_test.tobj.testobj.TestInputData.exec_log_path) , current_test.stream, sfile)
          #cthread.stream = None

        #callback
        #removeactionid
        lctx.debug("Callback here removing item")
        obj.removeActionItem(actionid)
        break
      time.sleep(3)

  if daytona_cmd == "DAYTONA_START_TEST" :
    #todo : verify the TEST END on filesystem OR Failure
    if current_test.status != "TESTABORT":
      lctx.debug("Setting current test status to TESTEND")
      current_test.status = "TESTEND"

  lctx.debug(daytona_cmd + " END [" + str(actionid) + "]")
  return "SUCCESS"

def log( self, *args):
  (obj, command, test_serialized, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
  lctx.debug(args)

def startMonitor( self, *args):
  (obj, command, params, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
  lctx.debug("startMonitor")
  p = params.split(",")
  test_serialized = p[0]
  s = p[1].strip()

  try :
    current_test.tobj = testobj.testDefn()
    t2 = testobj.testDefn()
    t2.deserialize(test_serialized)
    current_test.tobj= t2
    current_test.testid = current_test.tobj.testobj.TestInputData.testid

    lctx.debug("TEST SETUP Monitor : " + str(current_test.tobj.testobj.TestInputData.testid))
    #todo : indicate a status that says this host is monitor-active
    #current_test.status = "TESTSETUP"

    cfg = config.CFG("DaytonaHost", lctx)
    cfg.readCFG("config.ini")
    prefix = cfg.daytona_agent_root + "/" + current_test.tobj.testobj.TestInputData.frameworkname + "/" + str(current_test.tobj.testobj.TestInputData.testid) + "/results/"
    current_test.statsdir = prefix + s + "/sar/"
    current_test.archivedir = prefix

    common.createdir(cfg.daytona_agent_root, lctx)
    common.createdir(current_test.statsdir, lctx)


    execline = cfg.daytona_mon_path + "/sar_gather_agent.pl --daemonize --root-dir=" + current_test.statsdir
    lctx.info(execline)
    exec_cmd(execline, command, sync, obj, actionID)

  except Exception as e:
    lctx.error(e)
    lctx.error(traceback.print_exc())
    return "ERROR"

  lctx.debug("Completed start monitor")
  return "SUCCESS"

def stopMonitor( self, *args):
  (obj, command, test_serialized, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
  t2 = testobj.testDefn()
  t2.deserialize(test_serialized)
  lctx.debug("stop monitor for test : " + str(t2.testobj.TestInputData.testid))

  if current_test.testid != t2.testobj.TestInputData.testid:
    lctx.debug("start mon  :  TestID dont match")
    return "ERROR"

  cfg = config.CFG("DaytonaHost", lctx)
  cfg.readCFG("config.ini")

  #stop the sar processes
  execline = cfg.daytona_mon_path + "/sar_gather_agent.pl --shutdown --root-dir=" + current_test.statsdir
  lctx.info(execline)
  exec_cmd(execline, command, sync, obj, actionID)

  #prepare mon results tarball here
  lctx.debug(current_test.statsdir)
  #os.remove(current_test.statsdir + "/sar.dat")
  os.remove(current_test.statsdir + "/sar_gather_agent_debug.out")
  #os.remove(current_test.statsdir + "/*.pid")
  lctx.debug("removed monitor temp files from : " + current_test.archivedir)

  common.make_tarfile(current_test.archivedir + "results_stats.tgz", current_test.archivedir)

  lctx.debug("Completed stop monitor")
  return "SUCCESS"

def setupTest( self, *args):
  (obj, command, test_serialized, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
  lctx.debug("setuptest")

  try :
    current_test.tobj = testobj.testDefn()
    t2 = testobj.testDefn()
    t2.deserialize(test_serialized)
    current_test.tobj= t2
    current_test.testid = current_test.tobj.testobj.TestInputData.testid

    lctx.debug("TEST SETUP : " + str(current_test.tobj.testobj.TestInputData.testid))
    current_test.status = "TESTSETUP"

    cfg = config.CFG("DaytonaHost", lctx)
    cfg.readCFG("config.ini")
    prefix = cfg.daytona_agent_root + "/" + current_test.tobj.testobj.TestInputData.frameworkname + "/" + str(current_test.tobj.testobj.TestInputData.testid) + "/results/"
    current_test.execdir = prefix + current_test.tobj.testobj.TestInputData.exechostname 
    current_test.logdir = prefix + current_test.tobj.testobj.TestInputData.exechostname + "/application"
    current_test.resultsdir = prefix
    current_test.statsdir = prefix + current_test.tobj.testobj.TestInputData.exechostname + "/sar/"
    current_test.archivedir = prefix

    common.createdir(cfg.daytona_agent_root, self.lctx)
    common.createdir(current_test.execdir, self.lctx)
    common.createdir(current_test.logdir, self.lctx)
    common.createdir(current_test.resultsdir, self.lctx)
    common.createdir(current_test.statsdir, self.lctx)

    #todo : check and validate if exec script is provided in expected format and
    #       the file exists in that location
    execscript = current_test.tobj.testobj.TestInputData.execution_script_location.split(":")[1]
    lctx.debug("TEST SETUP : " + str(execscript))

    tmp = str(execscript).split("/")
    tmp.reverse()
    lctx.debug(tmp)
    filename = tmp[0]
    current_test.execscriptfile = current_test.execdir+"/"+filename
    lctx.debug(current_test.execscriptfile)
  except Exception as e:
    lctx.error(e)
    lctx.error(traceback.print_exc())
    return "ERROR"

  try:
    ret = copyfile(execscript, current_test.execscriptfile)
  except shutil.Error as err:
    lctx.error("error copying file : " + str(execscript) + " to " + str(current_test.execscriptfile))
    return "ERROR"

  try:
    os.chmod(current_test.execscriptfile, 0744)
  except:
    lctx.error("error setting perm file : " +  str(current_test.execdir))
    return "ERROR"

  #create dirs
  #get exec script name
  #cp the exec script
  #set exec perm
  #update cur test obj with exec script
  #exec any custom setup script
  lctx.debug("Completed setuptest")
  return "SUCCESS"

def startTest( self, *args):
  (obj, command, test_serialized, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
  t2 = testobj.testDefn()
  t2.deserialize(test_serialized)
  lctx.debug("starttest : " + str(t2.testobj.TestInputData.testid))

  if current_test.tobj.testobj.TestInputData.testid == t2.testobj.TestInputData.testid:
    lctx.debug("TestID match")
    current_test.status = "TESTRUNNING"
    current_test.actionID = actionID
  else:
    lctx.error("cur test not same as test obj passed in starttest : " + str(t2.testobj.TestInputData.testid))
    return "ERROR"

  #Original execscript
  #execscript = current_test.tobj.testobj.TestInputData.execution_script_location

  #Copied execscript
  execscript = current_test.execscriptfile
  args = ""
  for a in current_test.tobj.testobj.TestInputData.execScriptArgs:
      args = args + " \"" + a[3] + "\""
  execline = execscript + " " + args
  lctx.debug("Execution line:" + execline)

  #execute the exec script here
  exec_cmd(execline, command, sync, obj, actionID)

  lctx.debug("Completed start test")
  return "SUCCESS"

def cleanup(*args):
  return "SUCCESS"

def endTest(self, *args):
  (obj, command, test_serialized, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
  lctx.debug("Ending test")
  t2 = testobj.testDefn()
  t2.deserialize(test_serialized)

  (t, aid, tst, ts)=(None, None, None, None)

  lctx.debug(args)
  lctx.debug(obj)
  lctx.debug(obj.async_actions)

  for tp in obj.async_actions:
    (t, aid, tst, ts)=tp
    if tst.testobj.TestInputData.testid == t2.testobj.TestInputData.testid:
      lctx.debug("Found ASYNC action pending for this this test")
      (t, aid, tst, ts) = obj.async_actions[0]
      break

  current_test.status = "TESTABORT"
  if t is not None:
    t.stop()
    t.join()
  lctx.debug("Stopped ASYNC action pending for this this test")


  cleanup()
  lctx.debug(command + "[" + str(actionID) + "]")
  return "SUCCESS"


def heartbeat( self, *args):
  return "ALIVE"

def setFinish( self, *args):
  (obj, command, test_serialized, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
  t2 = testobj.testDefn()
  t2.deserialize(test_serialized)
  lctx.debug(str(current_test.testid) + ":" + current_test.status)
  current_test.status = "TESTFINISHED"
  return "SUCCESS"

def getStatus( self, *args):
  (obj, command, test_serialized, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
  t2 = testobj.testDefn()
  t2.deserialize(test_serialized)
  lctx.debug(str(current_test.testid) + ":" + current_test.status)
  return current_test.status

def fileDownload(self, *args):
  cl = client.TCPClient(LOG.getLogger("clientlog", "Agent"))
  prm = args[2].split(',')
  lctx.debug(prm)

  test_serialized = prm[3].strip()
  t2 = testobj.testDefn()
  t2.deserialize(test_serialized)

  dest =""
  if prm[4] == "RESULTS" :
    dest = t2.testobj.TestInputData.exec_results_path
  elif prm[4] == "STATS" :
    s =  prm[5]
    lctx.debug(s)
    dest = t2.testobj.TestInputData.stats_results_path[s]
  else:
    dest = ""

  lctx.debug(prm[0])
  lctx.debug(prm[1])
  lctx.debug(prm[2])
  lctx.debug(dest)

  try:
    cl.sendFile(prm[0].strip(), int(prm[1].strip()), prm[2].strip(), dest.strip())
  except CommunicationError as e:
    lctx.error(e.value)
    return e.value
  return "SUCCESS"

def prepareResults(self, *args):
  (obj, command, test_serialized, actionID, sync) = (args[0], args[1], args[2], args[3], args[4])
  t2 = testobj.testDefn()
  t2.deserialize(test_serialized)
  lctx.debug("preparing results for test : " + str(t2.testobj.TestInputData.testid))

  if current_test.testid != t2.testobj.TestInputData.testid:
    lctx.debug("start mon  :  TestID dont match")
    return "ERROR"

  #stop the sar processes

  #prepare results tarball here
  common.make_tarfile(current_test.archivedir + "results.tgz", current_test.archivedir)

  lctx.debug(command + "[" + str(actionID) + "]")
  return "SUCCESS"

