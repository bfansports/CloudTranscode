---
layout: page
title: "Config files"
category: deep
date: 2015-05-05 23:43:56
order: 1
---

All configuration files are location in the 'config' folder:
https://github.com/sportarchive/CloudTranscode/blob/master/config/

### cloudTranscodeConfig.json

You must rename cloudTranscodeConfigSample.json to cloudTranscodeConfig.json. The stack is expecting a file named 'cloudTranscodeConfig.json' but using the right command line parameter you can load any config file you want.

This config file contains Cloud Transcode main configuration information. You must customize this file for your need.

cloudTranscodeConfig.json is devided in three sections:

   - aws: Can contain your AWS Region, Key and Secret. This can be left empty if you use environment variables or Ec2 Roles. See: http://docs.aws.amazon.com/AWSEC2/latest/UserGuide/iam-roles-for-amazon-ec2.html
   - cloudTranscode: contains all core configuration information:<br>
     You MUST at least edit: "cloudTranscode"->"workflow"->
     
        - "domain": Name of the domain to create/use
        - "name": Name of the workflow to create/use
        - "description": Name of the workflow to create/use
        - "decisionTaskList": Name of the taskList (task queue) to use

   - clients: List all the clients that can use the stack. They will all have their own SQS queues for communication: <br>
     You MUST at least edit the client "name" and: "clients"->"queues"->
     
        - "input": URL of the input SQS queue
        - "output" : URL of the output SQS queue

You can obviously edit everything else if you want to. The timeout values for each activities can be tweaked at will.

For more information see [SQS](http://aws.amazon.com/swf/)
