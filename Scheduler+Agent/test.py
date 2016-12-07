import mysql.connector

dbuser='daytona'
dbpass='2wsxXSW@'
dbhost='localhost'
dbname='daytona'

print "connecting to database " + dbname + " on " + dbhost
config = {
        'user': dbuser,
        'password': dbpass,
        'port':3306,
        'host': dbhost,
        'database': dbname,
}
try:
        con = mysql.connector.connect(**config)
        print "Database connection successful"
except mysql.connector.Error as err:
        print("Something went wrong: {}".format(err))
