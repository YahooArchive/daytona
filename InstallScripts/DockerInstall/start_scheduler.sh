sleep 60
dh_root_value=/tmp/daytona_root/test_data_DH

sed "s|dh_root_value|$dh_root_value|g" config.ini > config.ini.tmp
mv config.ini.tmp config.ini 

python ./scheduler.py &
