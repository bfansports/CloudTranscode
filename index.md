---
layout: default
title: "Cloud Transcode documentation"
---

> Hi! Thanks for looking into this. If you need help or have any questions drop us a message at: ct-support@sportarchive.tv

### What is CT?

Cloud Transcode (CT) let you transcode media files at scale. It's still early stage but it works and is used by us at Sport Archive.

We use the power of the <a href="https://github.com/sportarchive/CloudProcessingEngine" target="_blank">Cloud Processing Engine (CPE)</a> to start transcoding jobs. CPE was originaly Cloud Transcode but we wanted to reuse the core for other purposes. CPE allows you to scale processes and run workflows of processes using Amazon Simple Workflow Service (SWF).

CPE uses the following 3 AWS services: [S3](https://aws.amazon.com/s3/), [SQS](https://aws.amazon.com/sqs/) and [SWF](https://aws.amazon.com/swf/)

The CT project itself now contains only the logic for transcoding files. It's called the activities. You can code many types of activities that could run with CPE. 

CT provides two activities: ValidateAsset and TranscodeAsset.

### What can I do with it?

#### Validate and Probe assets

With CT you can use the ValidateAsset activity to test files at scale. You could have 1000 of workers validating images and videos when needed. Scale up and down at will with AutoScaling groups on Amazon. Deploy that using Docker and [AWS Container Services (ECS)](https://aws.amazon.com/ecs/) and you have a fleet of Validators completly elastic.

To Validate a file, we run the `file` and `ffprobe` commands on a 1024k header retrieved from your file on AWS S3. The data the ValidateAsset activity returns, contains codec specs, audio tracks, size, bitrates, etc. The whole FFprobe output, plus the mime type.

#### Transcode assets

You can only transcode videos at the moment but the stage is set for other type of transcodings. We could easily do it for pictures using imagemagik! Anyone?

For videos you can run arbitrary ffmpeg commands or craft simple commands in JSON. You can use FFmpeg presets as well. You can create thumbnails and use Watermarks. Your client applications can start the FFMpeg jobs they want. Your job description will specify a source file in AWS S3, what transcode you want and where you want to push the result file.

### How to use it?

You need the CPE project installed in order to get started with CT.

Head to the CPE project page and follow the documentation and tutorial: https://github.com/sportarchive/CloudProcessingEngine

To orchestrate your validation and transcoding activities, you must write a workflow Plan that can be interpreted by the CPE Decider. CPE allows you to write Plans describing your workflow execution. A Plan is a "simple" YAML file that defines your workflow steps and activities that execute them. There could be one or many activities in a plan. Chained together or not so they can run in parallel. You can have retries and fallbacks based on other activities output.

#### Imagine a workflow

My app uploads a video file in S3 and it triggers MyTranscodingWorkflow.yaml using the SDK provided or by sending a simple JSON message sent to a dedicated SQS queue.

This workflow could something like that:

                                  - Transcode for iPad
                                  - TS for PS4
    - ValidateAsset-> if valid -> - TS for Iphone
                                  - TS HD-1080
                                  - TS HD-720

### Get going

Start with CPE. You must understand all the AWS services first too. SQS, S3 and SWF. I insist on that.

For more information about the Decider plan syntax, see the CPE documentation: <br>
http://sportarchive.github.io/CloudProcessingEngine/comp/decider.html

To use Cloud Transcode you need to reference the activities it provides in your `pollers` configuration file.<br>
See: http://sportarchive.github.io/CloudProcessingEngine/config/config-files.html

Once the CPE stack is running CT activities, your client applications can start requesting transcoding jobs to CPE.

### Task Tracking

Check out the project status and tasks on Pivotal Tracker:

   - https://www.pivotaltracker.com/n/projects/1044000

Ask your questions on Gitter:

   - [![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/sportarchive/CloudTranscode?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge) 
