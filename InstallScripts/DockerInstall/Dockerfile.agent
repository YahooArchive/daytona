FROM ubuntu:16.04

ARG MYSQL_DATABASE
ARG MYSQL_USER
ARG MYSQL_PASSWORD

RUN if [ -z $MYSQL_DATABASE ] || [ -z $MYSQL_USER ] || [ -z $MYSQL_PASSWORD ]; then echo 'one or more variables are undefined in .env'; exit 1; fi

RUN apt-get -y  update && apt-get -y upgrade && apt-get -y dist-upgrade
RUN apt-get install sysstat -y
RUN apt-get install python -y
RUN mkdir -p /tmp/ExecScripts
RUN mkdir -p /tmp/Scheduler+Agent
RUN mkdir -p /tmp/daytona_sarmonitor
RUN apt-get install libdatetime-perl -y
RUN apt-get install strace -y
COPY ExecScripts/ /tmp/ExecScripts/
COPY Scheduler+Agent/ /tmp/Scheduler+Agent
COPY Scheduler+Agent/daytona_sarmonitor /tmp/daytona_sarmonitor

RUN printf "[DH]\ndh_root:"/var/www/html/daytona/daytona_root/test_data_DH"\nport:52222\nmysql-user:"${MYSQL_USER}"\nmysql-db:"${MYSQL_DATABASE}"\nmysql-host:db\nmysql-password:"${MYSQL_PASSWORD}"\n" >> /tmp/Scheduler+Agent/config.ini
RUN printf "email-user:"daytona"\nemail-server:"yahoo.com"\nsmtp-server:"localhost"\nsmtp-port:"25"\n\n" >> /tmp/Scheduler+Agent/config.ini
RUN printf "[AGENT]\nagent-root:/tmp/daytona_root/test_data_AGENT/\nmon-path:/tmp/daytona_sarmonitor/bin/\nport:52223" >> /tmp/Scheduler+Agent/config.ini

WORKDIR /tmp/Scheduler+Agent
RUN printf "sleep 60\npython ./agent.py" > start_agent.sh
RUN chmod +x start_agent.sh
CMD ./start_agent.sh
