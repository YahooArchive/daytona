#!/usr/bin/env python
# -*- coding:cp949 -*-
import uuid

class DaytonaEnvelope():
  #todo : write all members correctly
  def __init__(self):
    self.msgid = None
    self.timestamp = None
    self.host = None

  def construct(self, command, data):
    self.msgid = uuid.uuid4()
    #todo : include security appid here/ hostip_name
    return "DAYTONA_CMD:" + command + ":" + str(self.msgid) + ":" + data


