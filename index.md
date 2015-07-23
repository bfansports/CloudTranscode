---
layout: default
title: "Cloud Transcode documentation"
---

## Welcome

Here you will find the necessary information to get going with Cloud Transcode (CT).

<br>

### What is CT?

Cloud Transcode offers a set of CPE Activities for transcoding media files. CPE is the stack that execute those activities.

You need the CPE project installed in order to get started with CT. Head to the CPE project page and follow the documentation and tutorial: https://github.com/sportarchive/CloudProcessingEngine

### How to use it?

To use Cloud Transcode you need to reference the activities it provides in your `pollers` configuration file. See: http://sportarchive.github.io/CloudProcessingEngine/config/config-files.html

### What can I do with it?

With CT you can transcode media files. For now we handle videos only but the stage is set for other type of transcodings.

For Videos, you can run the following tasks at scale:

   - **FFProbe:** Probe your video assets and gather metadata
   - **FFMpeg:** Transcode your video assets to alternative formats, generate thumbnails  

To execute and orchestrate your transcoding tasks, you must write a workflow Plan that can be interpreted by CPE Decider. CPE allows you to create Plans that describes your workflow execution. A Plan is a "simple" YAML file that defines your workflow steps and activities that execute them.

For more information about the Decider plan syntax, see the CPE documentation: http://sportarchive.github.io/CloudProcessingEngine/comp/decider.html

Also check the Decider syntax doc: http://sportarchive.github.io/CloudProcessingEngine-Decider/

Once the CPE stack is running with your activities, your client applications can start sending transcoding jobs to the CPE stack. Jobs are sent through AWS SQS using a JSON format that describe the transcoding job you want to perform.

### Task Tracking

Check out the project status and tasks on Pivotal Tracker:

   - https://www.pivotaltracker.com/n/projects/1044000

Ask your questions on Gitter:

   - [![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/sportarchive/CloudTranscode?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge) 
