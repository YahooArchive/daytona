# <img src="docs/img/daytona_dark_text.png" alt="Daytona" width="250px">

Daytona is an application-agnostic framework for automated performance testing and analysis. Any performance testing script running on command line can be integrated into a Daytona framework for repeatable execution and methodical analysis.

## Update: Before using this code
We were alerted to a security flaw in this project that allows an authenticated user to upload a zip file and potentially enable a remote code execution attack. We are no longer mainintaining this project and request the community to help fix this code. If no one steps up to do so, we'll mark this code as archived and offer it in a read only mode with the caveat that it contains a flaw that you should repair. (Check the open issues on this repo.) If you do repair it, let us know. We want to offer the best quality code, but bugs don't fix themselves. So please help.

## Main features
* Repeatable execution of performance tests  
* Agnostic to any application
* Methodical analysis with unified presentation of application, system and hardware metrics
* Customizable performance report page 
* Email notification with test status and summary 
* Customizable harness for any application or load/performance test
* Comparison of multiple tests in tabular or graphical format
* Designed to be deployed as a hosted service
* Installation scripts for docker-compose and bare metal  
* Sample harness and test data loaded at installation for test drive
* Mobile-friendly design 
* Built-in profiling service for Perf and strace

## Documentation

* [Getting Started](docs/GettingStarted.md)
* [Architecture](docs/Architecture.md)
* [Documentation Index](docs/Documentation.md)

## Contact
* [Daytona-Developers](https://groups.google.com/d/forum/daytona-developers) for
  development discussions

## License

Copyright 2016 Yahoo Inc.

Licensed under the Apache License, Version 2.0: http://www.apache.org/licenses/LICENSE-2.0
