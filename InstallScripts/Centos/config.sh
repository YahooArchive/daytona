#!/usr/bin/env bash

# Password Policy (Recommendation)
# Password Length 8 to 12 Characters
# Atleast one lowercase
# Atleast one uppercase
# Atleast one digit
# Atleast one special character : @#-_$%^&+=§!?

# Daytona DB Details
db_name=daytona
db_user=daytona
db_host=localhost

#### To be filled in by admin before installation
db_password=
db_root_pass=

# Daytona Installation - Recommendation, Don't change below directories path
daytona_install_dir=$HOME/daytona_prod
daytona_data_dir=/var/www/html/daytona/daytona_root/test_data_DH

#### UI credentails to be filled by user before installation
ui_admin_pass=

#### SMTP Server Info to be filled by admin before installation
# SMTP server details depends on your org's IT policy
# You can run a a SMTP server on localhost if it's not blocked from sending email 
# Please contact your IT department and obtain functional SMTP server info

email_user=daytona
email_domain=yahoo.com
smtp_server=localhost
smtp_port=25
