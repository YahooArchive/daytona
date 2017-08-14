#!/bin/bash

source ../config.sh

mysql -u ${db_user} -p${db_password} ${db_name} < ./create_dump_table.sql

mysqldump -u ${db_user} -p${db_password} ${db_name} TestInputData_dump ApplicationFrameworkArgs_dump ApplicationFrameworkMetadata_dump CommonFrameworkAuthentication_dump CommonFrameworkSchedulerQueue_dump HostAssociation_dump HostAssociationType_dump LoginAuthentication_dump ProfilerFramework_dump TestArgs_dump TestResultFile_dump --no-create-info > old_db_data_dump.sql

sed -i -e 's/_dump//g' old_db_data_dump.sql

mysql -u ${db_user} -p${db_password} ${db_name} -e "DROP table if exists TestInputData_dump,ApplicationFrameworkArgs_dump,ApplicationFrameworkMetadata_dump,CommonFrameworkAuthentication_dump,CommonFrameworkSchedulerQueue_dump,HostAssociation_dump,HostAssociationType_dump,LoginAuthentication_dump,ProfilerFramework_dump,TestArgs_dump,TestResultFile_dump;"

mysql -u ${db_user} -p${db_password} ${db_name} < ./BootstrapDBdump.sql

mysql -u ${db_user} -p${db_password} ${db_name} < ./old_db_data_dump.sql

mysql -u ${db_user} -p${db_password} ${db_name} -e "DROP table if exists GlassWindow,CommonHardwareMetadata;"

rm -rf old_db_data_dump.sql
