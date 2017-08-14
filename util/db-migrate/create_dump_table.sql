use daytona;
CREATE TABLE TestInputData_dump As
SELECT 
        `TestInputData`.`testid` AS `testid`,
        `TestInputData`.`frameworkid` AS `frameworkid`,
        `TestInputData`.`title` AS `title`,
        `TestInputData`.`purpose` AS `purpose`,
        `TestInputData`.`username` AS `username`,
        `TestInputData`.`priority` AS `priority`,
        `TestInputData`.`modified` AS `modified`,
        `TestInputData`.`creation_time` AS `creation_time`,
        `TestInputData`.`start_time` AS `start_time`,
        `TestInputData`.`end_time` AS `end_time`,
        `TestInputData`.`end_status` AS `end_status`,
        `TestInputData`.`cc_list` AS `cc_list`,
        `TestInputData`.`timeout` AS `timeout`
    FROM
        `TestInputData`;
        
create table ApplicationFrameworkArgs_dump As
SELECT 
        `ApplicationFrameworkArgs`.`framework_arg_id` AS `framework_arg_id`,
        `ApplicationFrameworkArgs`.`frameworkid` AS `frameworkid`,
        `ApplicationFrameworkArgs`.`argument_name` AS `argument_name`,
        `ApplicationFrameworkArgs`.`widget_type` AS `widget_type`,
        `ApplicationFrameworkArgs`.`argument_values` AS `argument_values`,
        `ApplicationFrameworkArgs`.`argument_default` AS `argument_default`,
        `ApplicationFrameworkArgs`.`argument_order` AS `argument_order`,
        `ApplicationFrameworkArgs`.`argument_description` AS `argument_description`
    FROM
        `ApplicationFrameworkArgs`;
        
create table ApplicationFrameworkMetadata_dump As
SELECT 
        `ApplicationFrameworkMetadata`.`frameworkid` AS `frameworkid`,
        `ApplicationFrameworkMetadata`.`productname` AS `productname`,
        `ApplicationFrameworkMetadata`.`frameworkname` AS `frameworkname`,
        `ApplicationFrameworkMetadata`.`title` AS `title`,
        `ApplicationFrameworkMetadata`.`purpose` AS `purpose`,
        `ApplicationFrameworkMetadata`.`frameworkowner` AS `frameworkowner`,
        `ApplicationFrameworkMetadata`.`execution_script_location` AS `execution_script_location`,
        `ApplicationFrameworkMetadata`.`creation_time` AS `creation_time`,
        `ApplicationFrameworkMetadata`.`last_modified` AS `last_modified`,
        `ApplicationFrameworkMetadata`.`default_timeout` AS `default_timeout`,
        `ApplicationFrameworkMetadata`.`argument_passing_format` AS `argument_passing_format`
    FROM
        `ApplicationFrameworkMetadata`;

create table CommonFrameworkAuthentication_dump As
SELECT 
        `CommonFrameworkAuthentication`.`username` AS `username`,
        `CommonFrameworkAuthentication`.`administrator` AS `administrator`,
        `CommonFrameworkAuthentication`.`frameworkid` AS `frameworkid`
    FROM
        `CommonFrameworkAuthentication`;
        
create table CommonFrameworkSchedulerQueue_dump as
SELECT 
        `CommonFrameworkSchedulerQueue`.`queueid` AS `queueid`,
        `CommonFrameworkSchedulerQueue`.`testid` AS `testid`,
        `CommonFrameworkSchedulerQueue`.`state` AS `state`,
        `CommonFrameworkSchedulerQueue`.`message` AS `message`,
        `CommonFrameworkSchedulerQueue`.`pid` AS `pid`,
        `CommonFrameworkSchedulerQueue`.`state_detail` AS `state_detail`
    FROM
        `CommonFrameworkSchedulerQueue`;

create table HostAssociation_dump as
SELECT 
        `HostAssociation`.`hostassociationid` AS `hostassociationid`,
        `HostAssociation`.`hostassociationtypeid` AS `hostassociationtypeid`,
        `HostAssociation`.`testid` AS `testid`,
        `HostAssociation`.`hostname` AS `hostname`
    FROM
        `HostAssociation`;
        
create table HostAssociationType_dump as 
SELECT 
        `HostAssociationType`.`hostassociationtypeid` AS `hostassociationtypeid`,
        `HostAssociationType`.`frameworkid` AS `frameworkid`,
        `HostAssociationType`.`name` AS `name`,
        `HostAssociationType`.`shared` AS `shared`,
        `HostAssociationType`.`execution` AS `execution`,
        `HostAssociationType`.`statistics` AS `statistics`,
        `HostAssociationType`.`default_value` AS `default_value`
    FROM
        `HostAssociationType`;

create table LoginAuthentication_dump as
SELECT 
        `LoginAuthentication`.`username` AS `username`,
        `LoginAuthentication`.`password` AS `password`,
        `LoginAuthentication`.`is_admin` AS `is_admin`,
        `LoginAuthentication`.`email` AS `email`,
        `LoginAuthentication`.`user_state` AS `user_state`
    FROM
        `LoginAuthentication`;
        
create table ProfilerFramework_dump as
SELECT 
        `ProfilerFramework`.`profiler_framework_id` AS `profiler_framework_id`,
        `ProfilerFramework`.`profiler` AS `profiler`,
        `ProfilerFramework`.`testid` AS `testid`,
        `ProfilerFramework`.`processname` AS `processname`,
        `ProfilerFramework`.`delay` AS `delay`,
        `ProfilerFramework`.`duration` AS `duration`
    FROM
        `ProfilerFramework`;

create table TestArgs_dump as
SELECT 
        `TestArgs`.`testargid` AS `testargid`,
        `TestArgs`.`framework_arg_id` AS `framework_arg_id`,
        `TestArgs`.`testid` AS `testid`,
        `TestArgs`.`argument_value` AS `argument_value`
    FROM
        `TestArgs`;

create table TestResultFile_dump as
SELECT 
        `TestResultFile`.`test_result_file_id` AS `test_result_file_id`,
        `TestResultFile`.`frameworkid` AS `frameworkid`,
        `TestResultFile`.`filename` AS `filename`,
        `TestResultFile`.`title` AS `title`,
        `TestResultFile`.`filename_order` AS `filename_order`
    FROM
        `TestResultFile`;
