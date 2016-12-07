
import csv

import smtplib
import email.mime.multipart
import email.mime.base
import socket
import common
from logger import LOG

from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

f=open("results.csv")
res=f.read()
f.close()
reader = csv.reader(open("results.csv"))
htmlfile = ""
rownum = 0
htmlfile = '<table cellpadding="10">'
for row in reader:
    if rownum == 0:
      htmlfile = htmlfile + '<tr>'
      for column in row:
        htmlfile = htmlfile + '<th width="70%">' + column + '</th>'
      htmlfile = htmlfile + '</tr>'
    else:
      htmlfile = htmlfile + '<tr>'    
      for column in row:
        htmlfile = htmlfile + '<td width="70%">' + column + '</td>'
      htmlfile = htmlfile + '</tr>'
    rownum += 1
    htmlfile = htmlfile + '</table>'


subject = "Test SAMPLE completed successfully"

mail_content ="<BR>Test id : {sample id} \
               <BR> Framework : {sample framework} \
               <BR> Title : {sample title}<BR><BR>"
mail_content = mail_content + "<BR>==========================================================<BR><BR>"
mail_content = mail_content + "<BR>Purpose : {sample}<BR> \
                               <BR>Creation time : {TIME} \
                               <BR>Start time : {TIME} \
                               <BR>End time : {TIME}<BR>"
mail_content = mail_content + "<BR>Your test executed successfully.\
                               <BR>Results (Contents of results.csv)<BR>"
mail_content = mail_content + "<BR>==========================================================<BR>"
mail_content = mail_content + "<BR>" + htmlfile + "<BR>"


lctx = LOG.getLogger("schedulerlog", "DH")
curhost="ip-172-31-19-107.us-west-2.compute.internal"
smtp_server="localhost"
smtp_port=25
common.send_email("test email with results", "tychester@gmail.com", mail_content, "", lctx, "ubuntu", curhost, smtp_server, smtp_port)



