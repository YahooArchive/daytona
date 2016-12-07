#!/usr/bin/env python
# -*- coding:cp949 -*-

import threading
import SocketServer
import time
from collections import defaultdict


import server
import config
import common
import testobj
from logger import LOG


if __name__ == "__main__":
    # Port 0 means to select an arbitrary unused port
    lctx = LOG.getLogger("agentlog","Agent")
    cfg = config.CFG("Agent", lctx)
    cfg.readCFG("config.ini")

    HOST = common.get_local_ip()
    PORT = cfg.CPORT

    common.createdir(cfg.daytona_agent_root, lctx)

    server.serv.role = "Agent"
    base_serv = server.serv()

    #server.serv.lctx = LOG.getLogger("listenerlog","Agent")
    #ser = server.serv.ThreadedTCPServer((HOST, PORT), server.serv.ThreadedTCPRequestHandler)

    ser = base_serv.ThreadedTCPServer((HOST, PORT), server.serv.ThreadedTCPRequestHandler)
    server.serv.serverInstance = ser
    ip, port = ser.server_address

    #server.lctx = LOG.getLogger("listenerlog", "Agent")

    # Start a thread with the server -- that thread will then start one
    # more thread for each request
    server_thread = threading.Thread(target=ser.serve_forever)

    # Exit the server thread when the main thread terminates
    server_thread.daemon = True
    server_thread.start()

    lctx.info("Server loop running in thread:" + server_thread.name)
    lctx.info("Server started @ %s:%s" %(ip , port))


    #list of current exec ASYNC jobs
    #print actc.async_actions

    while True :
      if server.serv.actc is not None:
        d = "ASYNC Jobs ["
        lctx.debug(server.serv.actc.async_actions)
        for pending in server.serv.actc.async_actions:
          (t1, actionID, tst, ts) = pending
          diff = time.time() - ts
          lctx.debug(str(tst.testobj.TestInputData.timeout))
          d = d + (str(tst.testobj.TestInputData.testid)+":"+str(diff)) + ","
        d = d + "]"
        lctx.info(d)

        time.sleep(2)

    server_thread.join()
    lctx.info("Server thread ended")


