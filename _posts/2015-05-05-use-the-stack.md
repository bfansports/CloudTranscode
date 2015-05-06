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

In CT, communication is done through AWS SQS.

The stack and the clients (applications using the stack, there can be many) send and receive JSON messages though SQS.

- The clients send JSON commands in the 'input' SQS queue. The stack reads from the 'input' queue.
- The Stack sends JSON messages in the 'output' SQS queue. The clients read the 'output' SQS queue.

All messages have a defined JSON format that must be respected.

### Client example

A client example is available in the 'client_example' folder: https://github.com/sportarchive/CloudTranscode/tree/master/client_example

All CloudTranscode clients should use the CTComSDK to communicate with the stack. If the SDK doesn't exists in your language, just use the SDK documentation and create your own. Contact us and we will provide support for the documentation.

See: http://sportarchive.github.io/CloudTranscode-SDK/

The current implementation of the SDK is in PHP.

#### Test Poller

Note: You need PHP installed on your machine to run this test.

Usage:

    $> php ClientPoller.php -k <AWS key> -s <AWS secret> -r <AWS region> -c clientConfig.json
   
You can also used environment variables to reference key, secret and region.

This script simply listen for incoming JSON messages from the stack and print them on the screen

#### Test Commander

Usage:

    $> php ClientCommander.php -k <AWS key> -s <AWS secret> -r <AWS region> -c clientConfig.json
   
You can also used environment variables to reference key, secret and region.

This script sends is used to manually commands to the stack. You have access to a prompt and you can send commands like: start_job, cancel_job, etc.

#### Config file

There is a config file located in "client_example" folder named "clientConfigSample.json". You can rename it and edit it to reflect your SQS queues configurations. Reference this file using the -c option for both programs.

This configuration file contains the client details to communicate with the stack: client name and the SQS queues URL.

It is the same JSON data you must pass to the CTComSDK methods in order to send commands to the stack. Each method in the CTComSDK needs to know which SQS they must talk to.
