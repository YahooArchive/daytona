### Daytona FAQ

##### What is Daytona?
Daytona is a framework for automated performance testing and analysis. Any performance testing script running on command line can be integrated into a Daytona framework for repeatable execution and methodical analysis

##### How is Daytona different from so many other performance tools available out there?
Daytona is a generic framework that can be used for performance testing and analysis of any application. It wraps your existing performance test scripts/programs into a framework for repeatable execution, collection of a standard set of application metrics you define. Standard system metrics like sar data (cpu, memory, network disk I/O etc.,) are automatically collected by Daytona for plotting purpose. Multiple tests within the same framework can be compared with the UI. 

##### How do I get familiar with Daytona?
Please use GettingStared document to install Daytona and use the packaged SampleTestFramework to browse existing test data

##### What is a Daytona Framework?
A Daytona framework is analogous to a performance test suite or a performance benchmark

##### What is a Daytona Test?
A test in Daytona is an instance of a performance test within a specific framework. For example, you could run multiple tests within the same framework varying the throughput as an argument. 

##### What is a Test Report?
A Test Report is a composite artifact assembled with all the essential aspects of a performance test in one place (Application KPI, System Metrics, Test parameters, H/W configuration data). You can customize the contents of a test report on the Framework page. Test reports for multiple test can be compared.

##### What is a .plt file?
A .plt file is essentially a .csv file with a timestamp as the 1st column. Any data required to be plotted need to be placed into a .plt file. 

##### Does Daytona support profiling?
Support for Perf and strace is in progress.  

##### Can we submit tests into Daytona from command line?
This is work in progress. This is essential for integration into your CI-CD pipleline


