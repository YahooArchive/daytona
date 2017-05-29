### Getting started with Daytona

#### Installing core Daytona components, scheduler and agent on the same machine (tested with Ubuntu 16.04 and Centos 7.3)
* Running all the core components, scheduler and agent on the same machine should be the first step in gaining familiarity with Daytona
* Clone the git repo
* cd daytona/InstallScrips/{Ubuntu,Centos}
* Edit config.sh to fill in the required credentials
* ./install_daytona_all.sh
* Make sure you see the Daytona login page at http://daytona-host

#### Installing Daytona agent on any other execution Host (Tested with Ubuntu 16.04 and Centos 7.3)
* This should be pursued only after you are familiar with basic functionalities of Daytona
* Clone the git repo
* cd daytona/InstallScrips/{Ubuntu,Centos}
* ./install_daytona_agent.sh

#### Using Docker
* A docker-compose.yaml file has been has been included for your convinience to install and run Daytona
* Install latest version of docker and docker-compose
* clone the git repo
* cd daytona
* docker-compose up
* Make sure you see the Daytona login page at http://daytona-host:8084

#### TestData, sample framework and sample execution script
To expedite your learning experience, Daytona has been packaged with a sample test framework, a sample execution script and an admin account which is configurable before installation through config.sh file in installation folder. Once UI comes up, user can register themselves and admin need to activate the account from settings panel. Also, You can login as admin with the password configured during installation and can browse and compare test results for few sample tests already packaged with the initial installation. To be able create and run a new test with the sample framework, you need to do the following:

* Select DaytonaSimpleFramework from the "Select Framework" pull-down menu
* Create a new test from the left panel "Test" section
* Add a Title and Purpose
* Hit "Save & Run"
* Test will be added to the scheduler queue
* Test should complete in few minutes and results ready for viewing
* From left panel: Test-->My Tests will list your tests
* Once you select a completed test: Views-->Test Report will show a composite Performance Report for the Test
* Output Files: shows all csv files associated with the test
* IP Address in the system metrics section (one IP address for each statistics host) shows all sar data (CPU,Memory, Network, I/O etc.,)

#### Execution script
Daytona execution scripts are wrappers for your performance test or benchmark scripts/programs run from command line. Arguments passed to the performance test are exposed through Daytona framework.Having a peformance test script running from the command line is a prerequite for a Daytona framework. Please refer to the packaged sample execution script for details. It executes the steps necessary to run the performance test, collects application level performance metrics and formats those metrics into .csv (tabular data), .plt (plotting) or .txt (plain text format) files suitable for viewing and analyzing results.
