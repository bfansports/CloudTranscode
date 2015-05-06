---
layout: page
title: "ActivityPoller"
category: struct
date: 2015-05-06 17:50:14
order: 200
---

The ActivityPoller executes activity tasks. There are N ActivityPollers and they each execute one task at a time.

They run independently on machines with Internet access and poll the AWS SWF workflow for incoming tasks.

### More info

http://docs.aws.amazon.com/amazonswf/latest/developerguide/swf-dg-develop-activity.html#swf-dg-polling-activity-tasks

### Activities

Activities are started by the ActivityPoller when a new task that can be handled is received. The ActivityPoller will instanciate an activity class (see 'activities' folder) and will execute the 'do_activity' method.

Transcoding activities will executes "transcoders". Different transcoders can be found in the 'activities/transcoders' folder.

There is one transcoder per filetype: VIDEO, IMG, AUDIO, DOC



