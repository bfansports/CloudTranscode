---
layout: page
title: "Pilot the stack"
category: start
date: 2015-05-03 18:55:05
order: 5
---

You have a running stack! Nice, but unless you can send it jobs it's useless.

In order to send jobs to the Stack you must send it a 'start_job' order. 
You must also poll incoming messages so you know the transcoding jobs progression.

In CT, Communication is done through AWS SQS.

The stack and the clients (applications using the stack, there can be many) send and receive JSON messages though SQS.

- The clients send JSON commands in the 'input' SQS queue. The stack reads from the 'input' queue.
- The Stack sends JSON messages in the 'output' SQS queue. The clients read the 'output' SQS queue.

All messages have a defined JSON format that must be respected.

### Test Poller

### Test Commander

