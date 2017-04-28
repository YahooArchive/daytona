FROM mysql:latest

ARG DAYTONA_UI_ADMIN
ARG DAYTONA_UI_ADMIN_PASSWORD
ARG MYSQL_DATABASE
ARG MYSQL_USER
ARG MYSQL_PASSWORD

RUN if [ -z $DAYTONA_UI_ADMIN ] || [ -z $DAYTONA_UI_ADMIN_PASSWORD ] || [ -z $MYSQL_DATABASE ] || [ -z $MYSQL_USER ] || [ -z $MYSQL_PASSWORD ]; then echo 'one or more variables are undefined in .env'; exit 1; fi

RUN apt-get update && apt-get install php5-cli -y

COPY DbSchema/DbSchema.sql /docker-entrypoint-initdb.d/daytona.sql

RUN php -r "print(password_hash(getenv('DAYTONA_UI_ADMIN_PASSWORD'), PASSWORD_DEFAULT));" > /tmp/passwd
RUN pass=`cat /tmp/passwd` && echo "UPDATE LoginAuthentication set password='${pass}' where username='${DAYTONA_UI_ADMIN}';" \
> /docker-entrypoint-initdb.d/users.sql
RUN echo "update HostAssociationType set default_value='agent' where frameworkid=51 and name='execution';" >> /docker-entrypoint-initdb.d/users.sql
RUN rm /tmp/passwd && apt-get remove php5-cli --purge -y && apt-get autoremove -y
