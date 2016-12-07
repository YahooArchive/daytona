## Daytona Administration

### Failover & HA
At this point, we do not have fail-over or HA configured for Daytona. We run just one primary Daytona instance. We recognize this is an important aspect for continuous operation and we plan to address it in upcoming releases. Until then, please take regular backup of MySQL database and the file system daytona_root and save it in a location from where it can be restored in case of a failure of the primary Daytona instance.

### Restarting Components

* Apache: On the Daytona host, run the command: sudo service apache2 restart 
* MySQL:  On the Daytona host, run the command: sudo service mysql restart 
* Scheduler: On the Daytona host, kill the scheduler process and run the commmand: cd /home/ubuntu/Scheduler+Agent; nohup python ./scheduler.py & 
* Agent: On the execustion host(s), kill the agent process and run the command: cd /home/ubuntu/Scheduler+Agent; nohup pyhton ./agent.py & 

