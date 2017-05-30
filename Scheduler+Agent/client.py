#!/usr/bin/env python
# -*- coding:cp949 -*-
import socket
import sys
import common
import time
from common import CommunicationError

class TCPClient ():
  #todo : Constructor and destructor
  def __init__(self, logctx):
    self.lctx = logctx

  def handShake(self, ip, port, handshakeData):
    #todo : handshake data will have ver, exec details
    #call send below with message = ev.construct("DAYTONA_HANDSHAKE", "handshake_data"))
    return True;

  def stream_end(self, ip, port, fwip, s, sfile):
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    try:
      sfile.write("DAYTONA_STREAM_END")
      sock.connect((ip, port))
      sock.send("DAYTONA_CMD:DAYTONA_STREAM_END:0:"+fwip)
      s.close()
      sys.stdout = sys.__stdout__
    except IOError:
      self.lctx.error("Server not responding, perhaps server not running")
      self.lctx.error(ip + "," +  "could not stream ")
    finally:
      self.lctx.debug("closing sock")
      sock.shutdown(socket.SHUT_RDWR)
      sock.close()
    return

  def stream_start(self, ip, port, fwip):
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sfile = None
    try:
      sock.connect((ip, port))
      self.lctx.debug(ip + "," + str(port))
      msg = "DAYTONA_CMD:DAYTONA_STREAM_START:0:"+fwip
      sock.send(msg)
      response = sock.recv(8192)
      if response == "STREAMFILEREADY":
          self.lctx.debug(response)
      #response = sock.recv(8192)
      sfile = sock.makefile('wb')
      sys.stdout = sfile
    except IOError:
      self.lctx.error("Server not responding, perhaps server not running")
      self.lctx.error(ip + "," +  "could not stream ")
      # raise CommunicationError("ERROR:" + ip + "," +  "could not stream")
    return (sock,sfile)

  def sendFile(self, ip, port, filename, loc):
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    response = None
    try:
      sock.connect((ip, port))
      msg = "DAYTONA_CMD:DAYTONA_FILE_UPLOAD:0:"+filename+","+loc+""
      #sock.sendall(msg)
      sock.send(msg)
      self.lctx.debug(msg)
      response = sock.recv(8192)
      if response == "FILEREADY":
          self.lctx.debug(response)
      f = open(filename,'rb')
      l = f.read(8192)
      while (l):
        self.lctx.debug("Sending...")
        self.lctx.debug(len(l))
        #sock.send(l)
        sock.sendall(l)
        l = f.read(8192)
      f.close()
      self.lctx.debug("Done Sending")
      sock.shutdown(socket.SHUT_WR)
    except IOError:
      self.lctx.error("Server not responding, perhaps server not running")
      self.lctx.error(ip + "," +  "could not send file : " + filename)
      # raise CommunicationError("ERROR:" + ip + "," +  "could not send file : " + filename)
    finally:
      self.lctx.debug("closing sock")
      try:
        sock.shutdown(socket.SHUT_RDWR)
      except:
        pass
      sock.close()
    return

  def send(self, ip, port, message):
    l = message.split(":")
    self.lctx.debug(l[0]+":"+l[1]+":"+l[2])
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    response = None
    try:
      sock.settimeout(5)
      sock.connect((ip, port))
      sock.sendall(message)
      response = sock.recv(8192)
      self.lctx.debug("Received: {}".format(response))
      #todo : based on response actionID expect more and loop
      #if actionID is null the exec of this cmd has ended 
    except IOError:
      self.lctx.error("Server not responding, perhaps server not running")
      self.lctx.error("Could not send message to " + ip + ":" + str(port)+ "," +  message)
      # raise CommunicationError("ERROR:" + ip + "," +  message)
    finally:
      self.lctx.debug("closing sock")
      try:
        sock.shutdown(socket.SHUT_RDWR)
      except:
        pass
      sock.close()
    return response
