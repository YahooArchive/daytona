#!/usr/bin/env python
# -*- coding:cp949 -*-

import dbaccess
from logger import LOG
import mysql.connector
from mysql.connector import Error as DBERROR


_db_connection = None
_db_cur = None

_db_connection = mysql.connector.connect(host='localhost',database='daytona',user='daytona',password='anotyadPassword')
_db_cur = _db_connection.cursor()


lctx = LOG.getLogger("schedulerlog", "DH")
dba = dbaccess.DBAccess(lctx)
query_result = dba.query("""select * from TestInputData""", (""), True, False)
print query_result


