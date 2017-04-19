FROM php:7.0-apache

ARG MYSQL_DATABASE
ARG MYSQL_USER
ARG MYSQL_PASSWORD
ENV daytona_ui_config /var/www/html/daytona/daytona_config.ini

RUN if [ -z $MYSQL_DATABASE ] || [ -z $MYSQL_USER ] || [ -z $MYSQL_PASSWORD ]; then echo 'one or more variables are undefined in .env'; exit 1; fi

RUN apt-get update && apt-get install libmcrypt-dev python python-mysql.connector -y
RUN apt-get install zlib1g-dev
RUN docker-php-ext-install mysqli pdo pdo_mysql mcrypt zip
RUN apt-get install python-requests -y

RUN a2enmod rewrite
RUN mkdir /var/www/html/daytona

COPY UI/ /var/www/html/daytona

RUN echo dbname = '"'${MYSQL_DATABASE}'"' | tee -a $daytona_ui_config > /dev/null
RUN echo username = '"'${MYSQL_USER}'"' | tee -a $daytona_ui_config > /dev/null
RUN echo servername = '"db"' | tee -a $daytona_ui_config > /dev/null
RUN echo password = '"'${MYSQL_PASSWORD}'"' | tee -a $daytona_ui_config > /dev/null
RUN echo cookie_key = '"'`head -c8 /dev/urandom | sha512sum | base64 | head -c 64`'"' | tee -a $daytona_ui_config > /dev/null

RUN mkdir -p /tmp/Scheduler+Agent
COPY Scheduler+Agent/ /tmp/Scheduler+Agent/
RUN printf "[DH]\ndh_root:"/var/www/html/daytona/daytona_root/test_data_DH"\nport:52222\nmysql-user:"${MYSQL_USER}"\nmysql-db:"${MYSQL_DATABASE}"\nmysql-host:db\nmysql-password:"${MYSQL_PASSWORD}"\n" >> /tmp/Scheduler+Agent/config.ini
RUN printf "email-user:"daytona"\nemail-server:"yahoo.com"\nsmtp-server:"localhost"\nsmtp-port:"25"\n\n" >> /tmp/Scheduler+Agent/config.ini
RUN printf "[AGENT]\nagent-root:/tmp/daytona_root/test_data_AGENT/\nmon-path:/tmp/daytona_sarmonitor/bin/\nport:52223" >> /tmp/Scheduler+Agent/config.ini

RUN mkdir -p /tmp/daytona_sarmonitor
COPY Scheduler+Agent/daytona_sarmonitor/ /tmp/daytona_sarmonitor/

RUN mkdir -p /var/www/html/daytona/daytona_root/test_data_DH
RUN ln -s /var/www/html/daytona/daytona_root/test_data_DH /var/www/html/daytona/test_data
COPY TestData/ /var/www/html/daytona/daytona_root/test_data_DH

RUN sed -i.bak 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/daytona/' /etc/apache2/sites-available/000-default.conf

WORKDIR /tmp/Scheduler+Agent
RUN printf "sleep 60\npython ./scheduler.py &" > start_scheduler.sh
RUN chmod +x start_scheduler.sh
CMD ./start_scheduler.sh && apache2-foreground
