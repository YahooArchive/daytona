-- MySQL dump 10.13  Distrib 5.7.17, for macos10.12 (x86_64)
--
-- Host: localhost    Database: daytona
-- ------------------------------------------------------
-- Server version	5.7.19-0ubuntu0.16.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `daytona`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `daytona` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `daytona`;

--
-- Table structure for table `ApplicationFrameworkArgs`
--

DROP TABLE IF EXISTS `ApplicationFrameworkArgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ApplicationFrameworkArgs` (
  `framework_arg_id` int(11) NOT NULL AUTO_INCREMENT,
  `frameworkid` int(11) DEFAULT NULL,
  `argument_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `widget_type` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `argument_values` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `argument_default` text COLLATE utf8_unicode_ci,
  `argument_order` int(11) NOT NULL DEFAULT '0',
  `argument_description` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`framework_arg_id`),
  UNIQUE KEY `framework_argument_name` (`argument_name`,`frameworkid`),
  KEY `framework_arg_id` (`framework_arg_id`),
  KEY `argument_name` (`argument_name`),
  KEY `frameworkid` (`frameworkid`),
  CONSTRAINT `ApplicationFrameworkArgs_fk_frameworkid` FOREIGN KEY (`frameworkid`) REFERENCES `ApplicationFrameworkMetadata` (`frameworkid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ApplicationFrameworkArgs`
--

LOCK TABLES `ApplicationFrameworkArgs` WRITE;
/*!40000 ALTER TABLE `ApplicationFrameworkArgs` DISABLE KEYS */;
INSERT INTO `ApplicationFrameworkArgs` VALUES (1,100,'Iterations','text','','3',0,'Number of iterations'),(2,100,'Delay','text','','10',1,'Delay between each iteration');
/*!40000 ALTER TABLE `ApplicationFrameworkArgs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ApplicationFrameworkMetadata`
--

DROP TABLE IF EXISTS `ApplicationFrameworkMetadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ApplicationFrameworkMetadata` (
  `frameworkid` int(11) NOT NULL AUTO_INCREMENT,
  `productname` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `frameworkname` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `purpose` text COLLATE utf8_unicode_ci,
  `frameworkowner` text COLLATE utf8_unicode_ci NOT NULL,
  `execution_script_location` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `creation_time` datetime DEFAULT NULL,
  `last_modified` datetime DEFAULT NULL,
  `default_timeout` tinyint(4) DEFAULT '0',
  `argument_passing_format` varchar(128) COLLATE utf8_unicode_ci DEFAULT 'arg_order',
  PRIMARY KEY (`frameworkid`),
  UNIQUE KEY `framework_frameworkname` (`frameworkname`),
  KEY `frameworkid` (`frameworkid`),
  KEY `frameworkname` (`frameworkname`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ApplicationFrameworkMetadata`
--

LOCK TABLES `ApplicationFrameworkMetadata` WRITE;
/*!40000 ALTER TABLE `ApplicationFrameworkMetadata` DISABLE KEYS */;
INSERT INTO `ApplicationFrameworkMetadata` VALUES (100,'Daytona','DaytonaSampleFramework','Sample framework to demonstrate basic features of Daytona','Demonstrate basic features of Daytona','admin','SampleDaytonaFramework/sample_execscript.sh','2017-08-03 18:21:49','2017-08-03 18:21:49',0,'arg_order');
/*!40000 ALTER TABLE `ApplicationFrameworkMetadata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `CommonFrameworkAuthentication`
--

DROP TABLE IF EXISTS `CommonFrameworkAuthentication`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CommonFrameworkAuthentication` (
  `username` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `administrator` tinyint(4) NOT NULL DEFAULT '0',
  `frameworkid` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`username`,`frameworkid`),
  KEY `username` (`username`),
  KEY `frameworkid` (`frameworkid`),
  CONSTRAINT `CommonFrameworkAuthentication_fk_frameworkid` FOREIGN KEY (`frameworkid`) REFERENCES `ApplicationFrameworkMetadata` (`frameworkid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `CommonFrameworkAuthentication`
--

LOCK TABLES `CommonFrameworkAuthentication` WRITE;
/*!40000 ALTER TABLE `CommonFrameworkAuthentication` DISABLE KEYS */;
INSERT INTO `CommonFrameworkAuthentication` VALUES ('admin',1,100);
/*!40000 ALTER TABLE `CommonFrameworkAuthentication` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `CommonFrameworkSchedulerQueue`
--

DROP TABLE IF EXISTS `CommonFrameworkSchedulerQueue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `CommonFrameworkSchedulerQueue` (
  `queueid` int(11) NOT NULL AUTO_INCREMENT,
  `testid` int(11) DEFAULT NULL,
  `state` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8_unicode_ci,
  `pid` int(11) DEFAULT '0',
  `state_detail` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`queueid`),
  UNIQUE KEY `queue_testid` (`testid`),
  KEY `queueid` (`queueid`),
  KEY `testid` (`testid`),
  CONSTRAINT `CommonFrameworkSchedulerQueue_fk_testid` FOREIGN KEY (`testid`) REFERENCES `TestInputData` (`testid`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `CommonFrameworkSchedulerQueue`
--

LOCK TABLES `CommonFrameworkSchedulerQueue` WRITE;
/*!40000 ALTER TABLE `CommonFrameworkSchedulerQueue` DISABLE KEYS */;
/*!40000 ALTER TABLE `CommonFrameworkSchedulerQueue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `HostAssociation`
--

DROP TABLE IF EXISTS `HostAssociation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `HostAssociation` (
  `hostassociationid` int(11) NOT NULL AUTO_INCREMENT,
  `hostassociationtypeid` int(11) NOT NULL,
  `testid` int(11) NOT NULL,
  `hostname` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`hostassociationid`),
  KEY `hostassociationid` (`hostassociationid`),
  KEY `testid` (`testid`),
  KEY `hostassociationtypeid` (`hostassociationtypeid`),
  CONSTRAINT `HostAssociation_fk_hostassociationtypeid` FOREIGN KEY (`hostassociationtypeid`) REFERENCES `HostAssociationType` (`hostassociationtypeid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `HostAssociation_fk_testid` FOREIGN KEY (`testid`) REFERENCES `TestInputData` (`testid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `HostAssociation`
--

LOCK TABLES `HostAssociation` WRITE;
/*!40000 ALTER TABLE `HostAssociation` DISABLE KEYS */;
INSERT INTO `HostAssociation` VALUES (1,1,1000,'ip-172-31-11-217'),(2,1,1001,'ip-172-31-11-217');
/*!40000 ALTER TABLE `HostAssociation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `HostAssociationType`
--

DROP TABLE IF EXISTS `HostAssociationType`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `HostAssociationType` (
  `hostassociationtypeid` int(11) NOT NULL AUTO_INCREMENT,
  `frameworkid` int(11) NOT NULL,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `shared` tinyint(4) DEFAULT '0',
  `execution` tinyint(4) DEFAULT '1',
  `statistics` tinyint(4) DEFAULT '1',
  `default_value` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`hostassociationtypeid`),
  UNIQUE KEY `frameworkid_name` (`frameworkid`,`name`),
  KEY `hostassociationtypeid` (`hostassociationtypeid`),
  KEY `frameworkid` (`frameworkid`),
  CONSTRAINT `HostAssociationType_fk_frameworkid` FOREIGN KEY (`frameworkid`) REFERENCES `ApplicationFrameworkMetadata` (`frameworkid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `HostAssociationType`
--

LOCK TABLES `HostAssociationType` WRITE;
/*!40000 ALTER TABLE `HostAssociationType` DISABLE KEYS */;
INSERT INTO `HostAssociationType` VALUES (1,100,'execution',0,1,0,'ip-172-31-11-217'),(2,100,'statistics',0,0,1,'');
/*!40000 ALTER TABLE `HostAssociationType` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `LoginAuthentication`
--

DROP TABLE IF EXISTS `LoginAuthentication`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `LoginAuthentication` (
  `username` varchar(128) NOT NULL,
  `password` varchar(128) NOT NULL,
  `is_admin` bit(1) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `user_state` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `LoginAuthentication`
--

LOCK TABLES `LoginAuthentication` WRITE;
/*!40000 ALTER TABLE `LoginAuthentication` DISABLE KEYS */;
INSERT INTO `LoginAuthentication` VALUES ('admin','$2y$10$aoARhNSvoVxuCe.0nsJ6SOrLiXlFat2X38W/WPwY.25MhyqqGPyuO','','admin@daytona.com','Active');
/*!40000 ALTER TABLE `LoginAuthentication` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ProfilerFramework`
--

DROP TABLE IF EXISTS `ProfilerFramework`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ProfilerFramework` (
  `profiler_framework_id` int(11) NOT NULL AUTO_INCREMENT,
  `profiler` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `testid` int(11) NOT NULL,
  `processname` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `delay` int(11) NOT NULL DEFAULT '0',
  `duration` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`profiler_framework_id`),
  KEY `profiler_framework_id` (`profiler_framework_id`),
  KEY `testID_idx` (`testid`),
  CONSTRAINT `testID` FOREIGN KEY (`testid`) REFERENCES `TestInputData` (`testid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ProfilerFramework`
--

LOCK TABLES `ProfilerFramework` WRITE;
/*!40000 ALTER TABLE `ProfilerFramework` DISABLE KEYS */;
INSERT INTO `ProfilerFramework` VALUES (1,'PERF',1000,NULL,10,10),(2,'STRACE',1001,'mysql',12,20),(3,'PERF',1001,'mysql',10,15);
/*!40000 ALTER TABLE `ProfilerFramework` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `TestArgs`
--

DROP TABLE IF EXISTS `TestArgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `TestArgs` (
  `testargid` int(11) NOT NULL AUTO_INCREMENT,
  `framework_arg_id` int(11) DEFAULT NULL,
  `testid` int(11) DEFAULT NULL,
  `argument_value` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`testargid`),
  UNIQUE KEY `test_framework_arg_id` (`testid`,`framework_arg_id`),
  KEY `testargid` (`testargid`),
  KEY `framework_arg_id` (`framework_arg_id`),
  KEY `testid` (`testid`),
  CONSTRAINT `TestArgs_fk_testid` FOREIGN KEY (`testid`) REFERENCES `TestInputData` (`testid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `TestArgs`
--

LOCK TABLES `TestArgs` WRITE;
/*!40000 ALTER TABLE `TestArgs` DISABLE KEYS */;
INSERT INTO `TestArgs` VALUES (1,1,1000,'3'),(2,2,1000,'10'),(3,1,1001,'3'),(4,2,1001,'10');
/*!40000 ALTER TABLE `TestArgs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `TestInputData`
--

DROP TABLE IF EXISTS `TestInputData`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `TestInputData` (
  `testid` int(11) NOT NULL AUTO_INCREMENT,
  `frameworkid` int(11) NOT NULL,
  `title` varchar(128) COLLATE utf8_unicode_ci DEFAULT '',
  `purpose` text COLLATE utf8_unicode_ci,
  `username` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `priority` int(11) DEFAULT '1',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creation_time` timestamp NULL DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `end_status` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cc_list` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `timeout` int(11) DEFAULT NULL,
  PRIMARY KEY (`testid`),
  KEY `testid` (`testid`),
  KEY `frameworkid` (`frameworkid`),
  CONSTRAINT `TestInputData_fk_frameworkid` FOREIGN KEY (`frameworkid`) REFERENCES `ApplicationFrameworkMetadata` (`frameworkid`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1002 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `TestInputData`
--

LOCK TABLES `TestInputData` WRITE;
/*!40000 ALTER TABLE `TestInputData` DISABLE KEYS */;
INSERT INTO `TestInputData` VALUES (1000,100,'Daytona Sample Test 1','','admin',NULL,'2017-08-03 18:34:16','2017-08-03 18:24:26','2017-08-03 18:33:27','2017-08-03 18:34:16','finished clean','',0),(1001,100,'Daytona Sample Test 1','','admin',NULL,'2017-08-03 18:36:55','2017-08-03 18:35:51','2017-08-03 18:36:03','2017-08-03 18:36:55','finished clean','',0);
/*!40000 ALTER TABLE `TestInputData` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `TestResultFile`
--

DROP TABLE IF EXISTS `TestResultFile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `TestResultFile` (
  `test_result_file_id` int(11) NOT NULL AUTO_INCREMENT,
  `frameworkid` int(11) DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `filename_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`test_result_file_id`),
  KEY `test_result_file_id` (`test_result_file_id`),
  KEY `filename` (`filename`),
  KEY `frameworkid` (`frameworkid`),
  CONSTRAINT `TestResultFile_fk_frameworkid` FOREIGN KEY (`frameworkid`) REFERENCES `ApplicationFrameworkMetadata` (`frameworkid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `TestResultFile`
--

LOCK TABLES `TestResultFile` WRITE;
/*!40000 ALTER TABLE `TestResultFile` DISABLE KEYS */;
INSERT INTO `TestResultFile` VALUES (1,100,'results.csv','Application Performance Metrics',0),(2,100,'%EXECHOST,0%/sar/cpu.plt:%user','User Mode CPU for Stat Host #0',1),(3,100,'%EXECHOST,0%/sar/cpu.plt:%system','System Mode CPU for Stat Host #0',2),(4,100,'multicol.csv','CSV File with multiple columns and rows',3);
/*!40000 ALTER TABLE `TestResultFile` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2017-08-03 12:29:56

