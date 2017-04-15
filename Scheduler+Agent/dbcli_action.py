import dbaccess
import config
import pickle
import time
import requests
import json
from logger import LOG

HOST_IP_PREFIX = 'http://'  # change this to 'https' for SSL connections
HOST_IP_SUFFIX = '/verifyuser.php'

class dbCliHandle():
    def __init__(self):
        self.lctx = LOG.getLogger("dblog", "DH")
        self.cfg = config.CFG("DaytonaHost", self.lctx)
        self.cfg.readCFG("config.ini")
        self.db = dbaccess.DBAccess(self.cfg, self.lctx)

    def __del__(self):
        if self.db is not None:
            self.db.close()

    def getFrameworkIdArgs(self, frameworkid):
        try:
            query_res = self.db.query(
                """SELECT frameworkname FROM ApplicationFrameworkMetadata WHERE frameworkid = %s""",
                (frameworkid,), False, False)
            if query_res:
                framework_name = query_res[0]
                query_res = self.db.query(
                    """SELECT argument_name,argument_default FROM ApplicationFrameworkArgs WHERE frameworkid = %s""",
                    (frameworkid,), True, False)
                return "SUCCESS|" + framework_name + "|" + pickle.dumps(query_res)
            else:
                return "Error|Invalid framework ID, try again"
        except:
            return "Error|Something went wrong with db while fetching arguments"

    def getFrameworkArgs(self, frameworkname):
        try:
            query_res = self.db.query(
                """SELECT frameworkid FROM ApplicationFrameworkMetadata WHERE frameworkname = %s""",
                (frameworkname,), False, False)
            if query_res:
                framework_id = query_res[0]
                framework_id = str(framework_id)
                query_res = self.db.query(
                    """SELECT argument_name,argument_default FROM ApplicationFrameworkArgs WHERE frameworkid = %s""",
                    (framework_id,), True, False)
                return "SUCCESS|" + framework_id + "|" + pickle.dumps(query_res)
            else:
                return "Error|Invalid framework name, try again"
        except:
            return "Error|Something went wrong with db while fetching arguments"

    def addTest(self, testdetails, username, state='new'):
        testdetails_map = pickle.loads(testdetails)
        if all(k in testdetails_map for k in (
                "frameworkid", "frameworkname", "title", "exec_host", "cc", "priority", "purpose", "timeout",
                "stat_hosts",
                "test_arg")):
            frameworkid = testdetails_map['frameworkid']
            title = testdetails_map['title']
            exec_host = testdetails_map['exec_host']
            cc = testdetails_map['cc']
            priority = testdetails_map['priority']
            purpose = testdetails_map['purpose']
            timeout = testdetails_map['timeout']
            stat_hosts = testdetails_map['stat_hosts']
            test_arg = testdetails_map['test_arg']

        else:
            return "Error|Some fields are missing from test file, please verify"
        try:
            # Getting test arguments ID from ApplicationFrameworkArgs for framework ID
            query_res = self.db.query(
                """SELECT argument_name, framework_arg_id FROM ApplicationFrameworkArgs WHERE frameworkid = %s ORDER BY argument_order""",
                (frameworkid,), True, False)
            if query_res:
                arg_list = query_res
                now = time.time()
                tstr = str(time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime(now)))

                # Adding test data in TestInput table
                query_res = self.db.query(
                    """INSERT INTO TestInputData(cc_list, creation_time, frameworkid, modified, priority, profile_duration, profile_start, profilehostname, purpose, timeout, title, username, end_status) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s) """,
                    (cc, tstr, frameworkid, tstr, priority, None, None, None, purpose, timeout, title, username, state),
                    False, False, True)
                if query_res:
                    testid = query_res

                    # Adding all test arguments in TestArgs
                    for arg_tuple in arg_list:
                        if arg_tuple[0] in test_arg:
                            arg_val = test_arg[arg_tuple[0]]
                            arg_id = arg_tuple[1]
                            query_res = self.db.query(
                                """INSERT INTO TestArgs ( argument_value, framework_arg_id, testid ) VALUES (%s,%s,%s)""",
                                (arg_val, arg_id, testid), False, False, True)
                            if not query_res:
                                raise Exception("Error|Test argument insertion failed")

                    # Delete any previously associated hosts associated with testid
                    query_res = self.db.query("""DELETE FROM HostAssociation WHERE testid = %s""",
                                              (testid,), False, False)

                    # Adding execution host in HostAssociation
                    query_res = self.db.query("""INSERT INTO CommonHardwareMetadata(hostname, added, updated) VALUES(%s, %s, %s ) ON DUPLICATE KEY UPDATE updated = %s""",
                                              (exec_host, tstr, tstr, tstr), False, True)

                    query_res = self.db.query(
                        """INSERT INTO HostAssociation (hostassociationtypeid, testid, hostname) SELECT hostassociationtypeid, %s, %s FROM HostAssociationType WHERE frameworkid = %s AND name = %s""",
                        (testid, exec_host, frameworkid, 'execution'), False, False, True)

                    if not query_res:
                        raise Exception("Error|Adding execution host for the test failed")

                    # Adding statistic hosts in HostAssociation
                    stat_hosts_arr = stat_hosts.split(",")
                    if len(stat_hosts_arr) > 0:
                        for host in stat_hosts_arr:
                            query_res = self.db.query(
                                """INSERT INTO CommonHardwareMetadata(hostname, added, updated) VALUES(%s, %s, %s ) ON DUPLICATE KEY UPDATE updated = %s""",
                                (host, tstr, tstr, tstr), False, True)
                            query_res = self.db.query(
                                """INSERT INTO HostAssociation (hostassociationtypeid, testid, hostname) SELECT hostassociationtypeid, %s, %s FROM HostAssociationType WHERE frameworkid = %s AND name = %s""",
                                (testid, host, frameworkid, 'statistics'), False, False, True)
                            if not query_res:
                                raise Exception("Error|Adding statistics host for the test failed")

                    self.db.commit()
                    return "SUCCESS|" + str(testid)
                else:
                    raise Exception("Error|Insert test failed")
            else:
                raise Exception("Error|Invalid framework ID provided in test details, check definition file")

        except Exception as err:
            self.db.rollback()
            self.lctx.debug(err)
            return str(err)

    def runTest(self, testid, username):

        try:
            # Verify whether test ID is valid
            query_res = self.db.query("""SELECT username FROM TestInputData WHERE testid = %s""", (testid,), False, False)
            if not query_res:
                raise Exception("Error|Test ID is not valid")

            if query_res[0] != username:
                raise Exception("Error|User is not authorised to run this test")

            query_res = self.db.query("""SELECT * FROM CommonFrameworkSchedulerQueue WHERE testid = %s""", (testid,),
                                      True, False)
            if query_res:
                raise Exception("Error|Test " + testid + " is already running")

            # Adding test into CommonFrameworkSchedulerQueue
            query_res = self.db.query(
                """INSERT INTO CommonFrameworkSchedulerQueue (testid, state, pid) VALUES ( %s, %s, %s )""",
                (testid, 'scheduled', 0), False, True, True)
            if not query_res:
                raise Exception("Error|Test start failed")

            return "SUCCESS|Test started successfully"

        except Exception as err:
            self.db.rollback()
            self.lctx.debug(err)
            return str(err)

    def getResult(self, testid, username):

        try:
            # Verify whether test ID is valid
            query_res = self.db.query("""SELECT username,frameworkid FROM TestInputData WHERE testid = %s""", (testid,), False, False)
            if not query_res:
                raise Exception("Error|Test ID is not valid")

	    frameworkid = query_res[1]

            if query_res[0] != username:
                raise Exception("Error|User is not authorised to get results for this test")
	    
            query_res = self.db.query(
                """SELECT frameworkname FROM ApplicationFrameworkMetadata WHERE frameworkid = %s""",
                (frameworkid,), False, False)

	    frameworkname = query_res[0]
	    
	    query_res = self.db.query(
                """SELECT hostname FROM HostAssociation JOIN HostAssociationType USING(hostassociationtypeid) WHERE testid = %s and name = %s""",
                (testid,'execution'), False, False)
            
	    exec_host = query_res[0] 
	    prefix=self.cfg.daytona_dh_root +"/"+ frameworkname + "/" + testid + "/" + "results" + "/" + exec_host + "/"
	    filename = prefix + "results.csv"
	    with open(filename, 'rb') as handle:
    		data = handle.read()

	    self.lctx.info(data)
            return "SUCCESS|" + data
	except (OSError, IOError) as e:
	    self.db.rollback()
            self.lctx.debug(e)
            return "Error|File Not Found"
        except Exception as err:
            self.db.rollback()
            self.lctx.debug(err)
            return str(err)


    def getTestByID(self, testid):
        test_details = {}
        try:
            query_res = self.db.query(
                """SELECT testid, frameworkname, TestInputData.title, TestInputData.purpose, priority, timeout, cc_list, testid, frameworkid, TestInputData.modified, TestInputData.creation_time, start_time, end_time, end_status, end_detail, username FROM TestInputData JOIN ApplicationFrameworkMetadata USING(frameworkid) WHERE testid = %s""",
                (testid,), False, False)
            if not query_res:
                raise Exception("Error|Test ID is not valid")

            test_details['testid'] = query_res[0]
            test_details['frameworkname'] = query_res[1]
            test_details['title'] = query_res[2]
            test_details['purpose'] = query_res[3]
            test_details['priority'] = query_res[4]
            test_details['timeout'] = query_res[5]
            test_details['cc'] = query_res[6]
            test_details['testid'] = query_res[7]
            test_details['frameworkid'] = query_res[8]
            test_details['modified'] = query_res[9]
            test_details['creation'] = query_res[10]
            test_details['start'] = query_res[11]
            test_details['end'] = query_res[12]
            test_details['status'] = query_res[13]
            test_details['status_detail'] = query_res[14]
            test_details['user'] = query_res[15]

            query_res = self.db.query(
                """SELECT name, hostname FROM HostAssociation JOIN HostAssociationType USING(hostassociationtypeid) WHERE testid = %s""",
                (testid,), True, False)
            if query_res:
                stat_host_list = []
                for host_type, ip in query_res:
                    if host_type == "execution":
                        test_details['exec_host'] = ip
                    elif host_type == "statistics":
                        stat_host_list.append(ip)
                if len(stat_host_list) > 0:
                    test_details['stat_host'] = ",".join(stat_host_list)
                else:
                    test_details['stat_host'] = ''

            if 'exec_host' not in test_details:
                test_details['exec_host'] = ''

            query_res = self.db.query(
                """SELECT argument_name, argument_value FROM TestArgs JOIN ApplicationFrameworkArgs USING(framework_arg_id) WHERE testid = %s ORDER BY testargid""",
                (testid,), True, False)

            if query_res:
                test_arg_list = {}
                for arg, value in query_res:
                    test_arg_list[arg] = value

                test_details['test_arg'] = test_arg_list
            return "SUCCESS|" + pickle.dumps(test_details)

        except Exception as err:
            self.db.rollback()
            self.lctx.debug(err)
            return str(err)

    def updateTest(self, test_details, username, state='new'):

        testdetails_map = pickle.loads(test_details)
        frameworkid = testdetails_map['frameworkid']
        title = testdetails_map['title']
        exec_host = testdetails_map['exec_host']
        cc = testdetails_map['cc']
        priority = testdetails_map['priority']
        purpose = testdetails_map['purpose']
        timeout = testdetails_map['timeout']
        stat_hosts = testdetails_map['stat_host']
        test_arg = testdetails_map['test_arg']
        testid = testdetails_map['testid']

        try:
            query_res = self.db.query("""SELECT username FROM TestInputData WHERE testid = %s""", (testid,), False,
                                      False)
            if not query_res:
                raise Exception("Error|Test ID is not valid")

            if query_res[0] != username:
                raise Exception("Error|User is not authorised to update this test")

            # Getting test arguments ID from ApplicationFrameworkArgs for framework ID
            query_res = self.db.query(
                """SELECT argument_name, framework_arg_id FROM ApplicationFrameworkArgs WHERE frameworkid = %s ORDER BY argument_order""",
                (frameworkid,), True, False)
            if query_res:
                arg_list = query_res
                now = time.time()
                tstr = str(time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime(now)))

                # Updating test data in TestInput table
                query_res = self.db.query(
                    """UPDATE TestInputData SET cc_list = %s, cpu_profiling = 0, modified = %s, priority = %s, purpose = %s, timeout = %s, title = %s, end_status = %s  WHERE testid = %s """,
                    (cc, tstr, priority, purpose, timeout, title, state, testid), False, False, False, True)
                if query_res > 0:
                    # Updating all test arguments in TestArgs
                    for arg_tuple in arg_list:
                        if arg_tuple[0] in test_arg:
                            arg_val = test_arg[arg_tuple[0]]
                            arg_id = arg_tuple[1]
                            query_res = self.db.query(
                                """UPDATE TestArgs set argument_value = %s WHERE framework_arg_id = %s AND testid = %s""",
                                (arg_val, arg_id, testid), False, False, False, True)
                            if query_res < 1:
                                raise Exception("Error|Test argument update failed")

                    # Delete any previously associated hosts associated with testid
                    query_res = self.db.query("""DELETE FROM HostAssociation WHERE testid = %s""",
                                              (testid,), False, False)

                    # Adding execution host in HostAssociation
                    query_res = self.db.query("""INSERT INTO CommonHardwareMetadata(hostname, added, updated) VALUES(%s, %s, %s ) ON DUPLICATE KEY UPDATE updated = %s""",
                                              (exec_host, tstr, tstr, tstr), False, True)

                    query_res = self.db.query(
                        """INSERT INTO HostAssociation (hostassociationtypeid, testid, hostname) SELECT hostassociationtypeid, %s, %s FROM HostAssociationType WHERE frameworkid = %s AND name = %s""",
                        (testid, exec_host, frameworkid, 'execution'), False, False, True)

                    if not query_res:
                        raise Exception("Error|Updating execution host for the test failed")

                    # Adding statistic hosts in HostAssociation
                    stat_hosts_arr = stat_hosts.split(",")
                    if len(stat_hosts_arr) > 0:
                        for host in stat_hosts_arr:
                            query_res = self.db.query(
                                """INSERT INTO CommonHardwareMetadata(hostname, added, updated) VALUES(%s, %s, %s ) ON DUPLICATE KEY UPDATE updated = %s""",
                                (host, tstr, tstr, tstr), False, True)
                            query_res = self.db.query(
                                """INSERT INTO HostAssociation (hostassociationtypeid, testid, hostname) SELECT hostassociationtypeid, %s, %s FROM HostAssociationType WHERE frameworkid = %s AND name = %s""",
                                (testid, host, frameworkid, 'statistics'), False, False, True)
                            if not query_res:
                                raise Exception("Error|Updating statistics host for the test failed")

                    self.db.commit()
                    return "SUCCESS|" + str(testid)
                else:
                    raise Exception("Error|Update test failed")
            else:
                raise Exception("Error|Invalid framework ID provided in test details, check definition file")

        except Exception as err:
            self.db.rollback()
            self.lctx.debug(err)
            return str(err)

    def authenticate_user(self, luser, lpassword):
        res = requests.post(HOST_IP_PREFIX + 'localhost' + HOST_IP_PREFIX, data={'user': luser, 'password': lpassword})
        if res.status_code != 200:
            if res.status_code == 401:
                return "Error|Invalid username/password"
            else:
                return "Error|User authentication failed with HTTP error code : " + str(res.status_code)
        else:
            return "SUCCESS"
