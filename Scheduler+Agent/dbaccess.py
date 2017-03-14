#!/usr/bin/env python
# -*- coding:cp949 -*-
import mysql.connector
from mysql.connector import Error as DBERROR
import testobj
import time
import os
import threading
import common
import shutil
from collections import defaultdict

import common

#todo : write client interface to dump all thread and Q information at anytime, operational tool
class DBAccess():
    cfg=None
    def __init__(self, cfg, lctx):
      self._db_connection = None
      self._db_cur = None
      self.cfg = cfg
      self.lctx = lctx
      try:
          if (self._db_connection and self._db_cur) and (self._db_cur is not None ) and (self._db_connection is not None):
            self._db_cur.close()
            self._db_connection.close()

          self._db_connection = mysql.connector.connect(host=cfg.mysql_host,database=cfg.msql_db,user=cfg.mysql_user,password=cfg.mysql_password)
          self._db_cur = self._db_connection.cursor()

          self.lctx.debug('Connected to MySQL database')
      except DBERROR as e:
          self.lctx.error(e)

    def close(self):
        if (self._db_connection and self._db_cur) and (self._db_cur is not None ) and (self._db_connection is not None):
          self._db_cur.close()
          self._db_connection.close()

    def query(self, query, params, n, commit):
      self._db_cur.execute(query, params)
      if commit==True:
        self._db_connection.commit()
      if n==False:
        return self._db_cur.fetchone()
      else:
        return self._db_cur.fetchall()

    def __del__(self):
        self._db_connection.close()


class DaytonaDBmon():
    mon_thread = []
    lock = threading.Lock()
    tests_to_run = defaultdict(list)
    cfg=None

    def __init__(self, cfg, lctx):
      self.lctx = lctx
      self.db = DBAccess(cfg,lctx)
      self.cfg = cfg
      self.startMon()
      print "CFG in init :"
      print self.cfg.mysql_host

      common.createdir(cfg.daytona_dh_root, self.lctx)

      time.sleep(5) # wait for 5 secs for all recs to be loaded into map

    def __del__(self):
      self.db.close()

    #def createdir(self, dirent):
    #  print "entry"
    #  print dirent
    #  if dirent == "" or dirent is None:
    #    raise Exception("create dir failed", dirent)

    #  if not os.path.exists(os.path.dirname(dirent)):
    #    os.makedirs(os.path.dirname(dirent))
    #    self.lctx.debug("created:" + dirent) 
    #  else:
    #    self.lctx.debug("dir exists:" + dirent)


    def mon(self, *args):
      #query all waiting state tests and load into to_schedule, this is for restart case (similarly for running)
      #todo : reconcile running and scheduled testids, if db altered externally
      restarted = True
      print "CFG in mon :"
      print self.cfg.mysql_host
      while True:
        self.db = DBAccess(self.cfg, self.lctx)
        d = "DBMON [W] : |"
        for k in self.tests_to_run:
          l = self.tests_to_run[k]
          for t in l :
            d = d + str(t.testobj.TestInputData.testid) + "|"
        self.lctx.info(d)
        d = ""

        query_result = None

        if restarted == True :
          query_result = self.db.query("""select testid from CommonFrameworkSchedulerQueue where state = %s or state = %s or state = %s or state = %s or state = %s or state = %s""", ("scheduled","waiting","setup","running","completed","collating"), True, False);
          #query_result = self.db.query("""select testid from TestInputData where end_status = %s or end_status = %s or end_status = %s or end_status = %s or end_status = %s or end_status = %s""", ("scheduled","waiting","setup","running","completed","collating"), True, False);
          restarted = False
        else:
          status = "scheduled"
          query_result = self.db.query("""select testid from CommonFrameworkSchedulerQueue where state = %s""", (status,), True, False);
          #query_result = self.db.query("""select testid from TestInputData where end_status = %s""", (status,), True, False);

        #reset all states to scheduled, mostly required in a restart case
        #all items that make to the DBMON [Q] will be in scheduled state
        for testid in query_result:
          to = testobj.testDefn()
          to.testobj.TestInputData.testid = testid[0]
          res = to.construct(testid[0])
          res = to.updateStatus("*", "scheduled")

        d = "DBMON [Q] : "
        d = d + str(query_result)
        self.lctx.info(d)

        for testid in query_result:
          found = False
          for k in self.tests_to_run: #search across all FW
            l = self.tests_to_run[k] #list of tests ready to be run
            for t in l:
              self.lctx.debug(t.testobj.TestInputData.testid)
              self.lctx.debug(testid[0])
              self.lctx.debug(k)
              if t.testobj.TestInputData.testid == testid[0]:
                self.lctx.debug("Test already present in runQ")
                found = True

          if(found == False):
            to = testobj.testDefn()
            to.testobj.TestInputData.testid = testid[0]

            #todo handle return status
            res = to.construct(testid[0])

            #create required dirs and setup server side env
            prefix=self.cfg.daytona_dh_root +"/"+ to.testobj.TestInputData.frameworkname + "/" + str(to.testobj.TestInputData.testid) + "/" + "results" + "/"
            shutil.rmtree(prefix, ignore_errors=True)

            to.testobj.TestInputData.exec_results_path = prefix+to.testobj.TestInputData.exechostname + "/"
            to.testobj.TestInputData.exec_path = prefix+to.testobj.TestInputData.exechostname
            to.testobj.TestInputData.exec_log_path = prefix+to.testobj.TestInputData.exechostname+"/application"

            to.testobj.TestInputData.stats_results_path = defaultdict()
            for s in to.testobj.TestInputData.stathostname.split(','):
              to.testobj.TestInputData.stats_results_path[s.strip()] = prefix+s.strip()+"/"

            to.testobj.TestInputData.stats_results_path[to.testobj.TestInputData.exechostname] = prefix+to.testobj.TestInputData.exechostname+"/"

            common.createdir(to.testobj.TestInputData.exec_results_path, self.lctx)
            common.createdir(to.testobj.TestInputData.exec_path, self.lctx)
            common.createdir(to.testobj.TestInputData.exec_log_path, self.lctx)
            for s in to.testobj.TestInputData.stats_results_path:
              common.createdir(to.testobj.TestInputData.stats_results_path[s], self.lctx)

            res = to.updateStatus("scheduled", "waiting")

            sz = to.serialize()
            t2 = testobj.testDefn()
            t2.deserialize(sz)

            if t2.testobj.TestInputData.testid != to.testobj.TestInputData.testid :
              self.lctx.error("error in ser / dser")
              break

            #use a lock here
            self.lock.acquire()
            self.tests_to_run[to.testobj.TestInputData.frameworkid].append(to);
            self.lctx.debug("Adding : " + str(to.testobj.TestInputData.testid))
            self.lock.release()

        self.db.close()

        if self.mon_thread[0].check() == False :
          return
        time.sleep(5) #todo : make this config item

    def startMon(self):
      mthread = None
      mthread = common.FuncThread(self.mon, True)
      self.mon_thread.append(mthread)
      mthread.start()
