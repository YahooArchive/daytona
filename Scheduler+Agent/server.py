#!/usr/bin/env python
# -*- coding:cp949 -*-
import socket
import SocketServer
import os
import threading
import time

import common
import action
import config
import testobj

from logger import LOG
from action import activeTest

global serverInstance
global actc


class ActionCaller:
    async_actions = []
    lock = threading.Lock()

    def __init__(self, lctx):
        self.lctx = lctx
        self.async_actions = []
        self.conf = config.CFG("DaytonaHost", lctx)
        self.conf.readCFG("config.ini")

    def removeActionItem(self, actionID):
        self.lock.acquire()
        for i in self.async_actions:
            (t,actionid,tst,ts) = i
            if actionid == actionID :
                self.lctx.debug("CALLBACK RCV: " + str(actionID))
                self.async_actions.remove(i)
        self.lock.release()

    def execute(self, command, paramcsv, actionID):
        # based on SYNCFLAG release from here
        # send actionID for currently being executed action based on this we can stream resp
        # keep exec details over time in a buffer with actionID mapped
        # send actionID NULL and hold return till exec is complete
        module = self.conf.actionMap[command.strip()].split(".")[0]
        function = self.conf.actionMap[command.strip()].split(".")[1]
        sync = self.conf.actionMap[command.strip()].split(".")[2]

        t2 = testobj.testDefn()
	if command == "DAYTONA_START_TEST":
            testid = int(paramcsv.split(",")[0])
            hosttype = paramcsv.split(",")[1]
            current_test = action.get_test(testid)
            if current_test:
                t2 = current_test.tobj

        m = __import__ (module)
        f = getattr(m,function)
        if sync == "T":  # wait for func to complete and return the ret
            self.lctx.debug("Executing SYNC ACTION for " + command.strip() + " : " + self.conf.actionMap[command.strip()] + ":" + str(actionID))
            ret = f(self, self, command, paramcsv, actionID, sync)
            self.lctx.debug("ACTION completed for " + command.strip() + " : " + self.conf.actionMap[command.strip()] + ":" + str(actionID))
            if command == "DAYTONA_CLI":
                return "actionID=" + str(actionID) + "%" + ret + "%" + "SYNC EXEC"
            else:
                return "actionID=" + str(actionID) + "," + ret + "," + "SYNC EXEC"
        else:
            self.lctx.debug("Executing ASYNC ACTION for " + command.strip() + " : " + self.conf.actionMap[command.strip()] + ":" + str(actionID))
            t1 = common.FuncThread(f, True, self, command, paramcsv, actionID, sync)
            if hosttype == "EXEC":
                x = (t1, actionID, t2, time.time())
                self.lock.acquire()
                self.async_actions.append(x)
                self.lctx.debug( "async_actions size :" + str(len(self.async_actions)))
                self.lock.release()
            t1.start()
            self.lctx.debug( "Executing ACTION for " + command.strip() + " : " + self.conf.actionMap[command.strip()] + ":" + str(actionID))
            return "actionID=" + str(actionID) + "," + "SUCCESS," + "ASYNC EXEC"


class serv:
    lctx = None
    actc = None
    role = None
    registered_hosts = None

    def __init__(self):
        serv.lctx = LOG.getLogger("listenerlog",serv.role)
        action.lctx = LOG.getLogger("actionlog", serv.role)
        # todo this integration has to be reviewed
        actc = ActionCaller(LOG.getLogger("actionlog", serv.role))
        serv.registered_hosts={}

    class ThreadedTCPRequestHandler(SocketServer.BaseRequestHandler):
        def __init__(self, request, client_address, server):
            global actc
            self.act = serv.actc
            SocketServer.BaseRequestHandler.__init__(self, request, client_address, server)
            return

        def setup(self):
            return SocketServer.BaseRequestHandler.setup(self)

        def finish(self):
            return SocketServer.BaseRequestHandler.finish(self)

        def handle(self):
            host = self.client_address[0]
            data = self.request.recv(8192)
            cur_thread = threading.current_thread()
            ev = data.split(":")
            serv.lctx.debug("Envelope contents : " + str(ev))
            cmd = ev[1]
            msgid = ev[2]
            params = ev[3]
            serv.lctx.info(cmd)
            serv.lctx.debug(msgid)
            serv.lctx.debug(params)

            if cmd == "DAYTONA_HANDSHAKE":
                # todo : maintain a list of daytona host that this server talks to only respond to the ones in the list

                p = params.split(",")
                if p[0] == "handshake1":
                    serv.registered_hosts[host] = host
                    addr = socket.gethostbyname(host)
                    serv.registered_hosts[addr] = addr

                    current_test = activeTest(0, None, None, None)
                    current_test.stathostip = p[4]
                    current_test.stathostport = self.server.server_address[1]
                    current_test.serverip = p[1]
                    current_test.testid = int(p[3])
                    current_test.serverport = int(p[2])
                    current_test.status = "SETUP"
		    test_logger = LOG.init_testlogger(current_test,"STAT")
                    if test_logger:
                        current_test.agent_log_file = test_logger.handlers[0].baseFilename

                    con = action.scheduler_handshake(current_test)
                    if con:
                        action.action_lock.acquire()
                        action.running_tests[int(p[3])] = current_test
                        action.action_lock.release()
                        response = "{}".format("SUCCESS")
			test_logger.info("Handshake successfull with daytona host : " + current_test.serverip)
                    else:
                        response = "{}".format("ERROR")
			test_logger.error("Handshake failed with daytona host : " + current_test.serverip)

                    self.request.sendall(response)
                    return
                else:
                    response = "{}".format("SUCCESS")
                    self.request.sendall(response)
                    return

            if host in serv.registered_hosts.keys() or cmd in ("DAYTONA_HEARTBEAT", "DAYTONA_CLI"):
                if cmd == "DAYTONA_STREAM_END":
                    serv.lctx.debug("End stream...")
                    return

                if cmd == "DAYTONA_STREAM_START":
                    filepath = params+"/execution.log"
                    d = os.path.dirname(filepath)
                    if not os.path.exists(d):
                        os.makedirs(d)

                    f = open(filepath,'wb')
                    serv.lctx.debug(filepath)

                    serv.lctx.debug("Receiving stream..." + filepath)
                    response = "{}".format("STREAMFILEREADY")
                    self.request.send(response)

                    l = self.request.recv(8192)
                    serv.lctx.debug(len(l))
                    while (l):
                        serv.lctx.debug("Receiving stream...")
                        f.write(l)
                        print l
                        serv.lctx.debug(l)
                        f.flush()
                        l = self.request.recv(8192)
                        if l == "DAYTONA_STREAM_END":
                            serv.lctx.debug("receiving term string : ")
                            break
                    f.close()
                    # response = "{}".format("SUCCESS")
                    # self.request.sendall(response)
                    serv.lctx.debug("Done receiving stream into : " + filepath)
                    return

                if cmd == "DAYTONA_FILE_UPLOAD":
                    p = params.split(",")
                    serv.lctx.debug("SER SERVER : " + params)
                    fn = p[0].split("/")
                    fn.reverse()
                    loc = p[1].strip()
                    serv.lctx.debug("SER SERVER : " + loc)

                    filepath = loc+fn[0]
                    d = os.path.dirname(filepath)
                    if not os.path.exists(d):
                        os.makedirs(d)

                    serv.lctx.debug("Receiving..." + filepath)
                    response = "{}".format("FILEREADY")
                    self.request.send(response)
                    f = open(filepath, 'wb')
                    l = self.request.recv(8192)
                    serv.lctx.debug(len(l))
                    while l:
                        serv.lctx.debug("Receiving...")
                        f.write(l)
                        f.flush()
                        l = self.request.recv(8192)
                        serv.lctx.debug(len(l))
                    f.close()
                    serv.lctx.debug("Done receiving results : " + filepath)
                    return

                if cmd == "DAYTONA_STOP_SERVER":
                    serverInstance.shutdown()
                    serverInstance.server_close()
                    response = "{}: {}".format(cur_thread.name, "Shutting down server")
                    self.request.sendall(response)
                    if len(self.act.async_actions) > 0 :
                        for pending in self.act.async_actions:
                            (t1, actionID, tst, ts) = pending
                            t1.stop()
                        serv.lctx.debug("DAYTONA_STOP_SERVER handler, Async action thread ended after stop : " + cur_thread.name)
                    return

                # todo : Set server to shutdown state, reject all incomming reqs if this flag set wait for all threads
                # to shutdown (with timeout) gracefully shutdown before timeout, or force close beyond timeout

                # exResp = self.act.execute(cmd, params, msgid)
                if serv.actc is None:
                    serv.actc = ActionCaller(LOG.getLogger("listenerlog", serv.role))

                exResp = serv.actc.execute(cmd, params, msgid)

                response = "{}: {}".format(cur_thread.name, exResp)
                self.request.sendall(response)
                if len(serv.actc.async_actions) > 0 :
                    serv.lctx.debug("Async action list : " + str(len(serv.actc.async_actions)))
                    for pending in self.act.async_actions:
                        (t1, actionID, tst, ts) = pending
                        t1.join()
                        serv.lctx.debug("Async action thread ended after join : " + t1.name)

            else:
                serv.lctx.error("Command recieved from unknown host before handshake")
                serv.lctx.error(host)

    class ThreadedTCPServer(SocketServer.ThreadingMixIn, SocketServer.TCPServer):
        pass

