#!/usr/bin/env python
# -*- coding:cp949 -*-
import glob
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
  def getLogger(name=None, ROLE=""):
    name = name+ROLE
    if name not in LOG._loggers.keys():
      LOG_FILENAME =  ROLE + '_logging_rotatingfile.log'
      logging.basicConfig(level=logging.DEBUG,
          format='%(asctime)s %(threadName)-12s %(name)-16s %(levelname)-6s {%(filename)-12s %(lineno)-4d}  %(message)-100s',
          datefmt='%m-%d %H:%M',
          filename=LOG_FILENAME,
          filemode='w')

      # Add the log message handler to the logger
      handler = logging.handlers.RotatingFileHandler(
          LOG_FILENAME, maxBytes=1073741824, backupCount=2)

      console = logging.StreamHandler()
      formatter = logging.Formatter('%(asctime)s %(threadName)-14s %(name)-19s %(message)s')
      console.setLevel(logging.INFO)
      console.setFormatter(formatter)

      formatter = logging.Formatter('%(asctime)s - %(name)16s - %(message)s')
      LOG._loggers[name] = logging.getLogger(str(name))
      LOG._loggers[name].setLevel(logging.DEBUG)
      LOG._loggers[name].addHandler(handler)
      handler.setFormatter(formatter)
      LOG._loggers[name].addHandler(console)

    return LOG._loggers[name]

