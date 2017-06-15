#!/usr/bin/env python
# -*- coding:cp949 -*-
import logging
import logging.handlers


class Singleton(type):
    _instances = {}

    def __call__(cls, *args, **kwargs):
        if cls not in cls._instances.keys():
            cls._instances[cls] = super(Singleton, cls).__call__(*args, **kwargs)
        return cls._instances[cls]


class LOG(object):
    __metaclass__ = Singleton

    _loggers = {}

    def __init__(self, *args, **kwargs):
        pass

    @staticmethod
    def getLogger(role=None, filename="", testlogger=False, filepath=""):
        role += filename
	
        if role not in LOG._loggers.keys():
            if testlogger:
                log_filename = filepath + filename + '_logging_rotatingfile.log'
            else:
                log_filename = filename + '_logging_rotatingfile.log'

            logging.basicConfig(level=logging.DEBUG,
                                format='%(asctime)s %(threadName)-12s %(name)-16s %(levelname)-6s {%(filename)-12s %(lineno)-4d}  %(message)-100s',
                                datefmt='%m-%d %H:%M',
                                filename=log_filename,
                                filemode='w')

            # Add the log message handler to the logger
            handler = logging.handlers.RotatingFileHandler(
                log_filename, maxBytes=1073741824, backupCount=2)
    
            console = logging.StreamHandler()
            formatter = logging.Formatter('%(asctime)s %(threadName)-14s %(name)-19s %(message)s')
            console.setLevel(logging.INFO)
            console.setFormatter(formatter)
    
            formatter = logging.Formatter('%(asctime)s %(levelname)-s : %(message)-100s','%m-%d %H:%M')
            LOG._loggers[role] = logging.getLogger(str(role))
            LOG._loggers[role].setLevel(logging.DEBUG)
    
            LOG._loggers[role].addHandler(handler)
            handler.setFormatter(formatter)
            LOG._loggers[role].addHandler(console)

        return LOG._loggers[role]


    @staticmethod
    def removeLogger(role, filename):
        role += filename
        if role in LOG._loggers.keys():
            del LOG._loggers[role]

