#!/usr/bin/env python
# -*- coding:cp949 -*-
import ConfigParser


class CFG:
    def __init__(self, role, lctx):
        self.actionMap = {}
        self.role = role
        self.lctx = lctx
        self.daytona_dh_root = ''
        self.daytona_agent_root = ''
	self.execscript_location = ''
	self.agent_test_logs = ''
        self.DHPORT = 0
        self.CPORT = 0
        self.mysql_user=''
        self.msql_db=''
        self.mysql_host=''
        self.mysql_password=''
        self.email_user=''
        self.email_server=''
        self.smtp_server=''
        self.smtp_port = 0

    def readActionMap(self, cfile):
        self.actionMap = {}
        with open(cfile, "r") as ins:
            for line in ins:
                ln = line.split(",")
                self.actionMap[ln[0].strip()] = ln[1].strip() + "." + ln[2].strip() + "." + ln[3].strip()
	if self.lctx is not None:
            self.lctx.info("Action map loaded")

    def readCFG(self, cfile):
        self.readActionMap("action.map")
        Config = ConfigParser.ConfigParser()
        Config.read(cfile)
        if self.lctx is not None:
            self.lctx.info(Config.sections())
        self.daytona_dh_root = Config.get('DH', 'dh_root')
        self.DHPORT = int(Config.get('DH', 'port'))
        self.mysql_user=Config.get('DH', 'mysql-user')
        self.msql_db=Config.get('DH', 'mysql-db')
        self.mysql_host=Config.get('DH', 'mysql-host')
        self.mysql_password=Config.get('DH', 'mysql-password')
        self.email_user=Config.get('DH', 'email-user')
        self.email_server=Config.get('DH', 'email-server')
        self.smtp_server=Config.get('DH', 'smtp-server')
        self.smtp_port=int(Config.get('DH', 'smtp-port'))
        self.daytona_agent_root = Config.get('AGENT', 'agent-root')
	self.execscript_location = Config.get('AGENT', 'execscript_location')
	self.agent_test_logs = Config.get('AGENT', 'agent_test_logs_location')
        self.CPORT = int(Config.get('AGENT', 'port'))

