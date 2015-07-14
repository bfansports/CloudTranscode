[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/sportarchive/CloudTranscode?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge) 
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/?branch=master) 

## Updates [07/14/2015]
> Happy Bastille day to all French Folks<br>
> The documentation has been updated. You should be able to get CT to work by following the doc. <br>
> The Docker images are being finalized.<br>

> MAJOR PROJECT UPDATE:
>   - The Framework has moved out of this repo to the CPE project: https://github.com/sportarchive/CloudProcessingEngine
>   - This repo only retains the code performing the transcoding which executed by your workers (ActivityPoller).
>
> If you have forked this project, you will need to update your fork.

# What is Cloud Transcode ?
Cloud Transcode is your own distributed transcoding stack. With it you can transcode media files in a distributed way, at scale.

## Goal
The goal of this project is to create an open source, scalable and cheap distributed transcoding platform where users have complete control over performance and cost. 

We started with video transcoding as it is the most costly, but the goal is to transcode any type media files (audio, documents and images). We use FFMpeg for video transcoding.

Today's commercial solutions for video transcoding are very expensive for large volumes. With this solution you can transcode large quantity of files at the pace you want, thus controling your cost. 

## Benefits
With Cloud Transcode, you control: scale, speed and cost. You can run everything locally if you want, no Cloud instance required. Or you can deploy on AWS EC2, Beanstalk or Docker containers. 

Your workers only need an Internet connection to use the required Amazon services: SWF, SQS and S3. It means that you can have a local, hybrid or full cloud setup. It's up to you.

## Transcoding supported

   - **Video to Video transcoding**: One video IN, many videos OUT. Any formats and codecs supported by your ffmpeg.
   - **Video to Thumbnails transcoding**: Snapshot at certain time in video or intervals snapshot every N seconds. Keep image ratio.
   - **Watermark integration in video**: Take image IN and position a watermark on top of the video. Custom position and transparency. Keep image ratio.

We are working to support ALL FFmpeg options.

# How to use CT ?

Cloud Transcode is a set of "activities" that are executed by the Cloud Processing Engine (CPE) project. 

CPE you can execute workflows (chain of tasks) in a distributed way using the SWF cloud service. It initiate tasks executions on workers that you deploy. You can deploy your workers anywhere: locally or in the Cloud. Your workers (machines running your tasks) only need an Internet connection to access the AWS services.

CPE allows the execution of arbitrary workflow that you define yourself. CPE is a good fit for any type of orchestrated batch processing that needs to span over several workers.

CPE makes use of the following AWS services:

   - SWF (Simple Workflow): Define your own workflow and let SWF track its progress and initiate tasks.
   - SQS (Simple Queue Messaging): Your client applications communicate with the CPE stack simply using SQS messages.

**You need to clone the CPE project to get going with Cloud Transcode.**

So head to the CPE project page, clone it and discover what you can do with CPE: https://github.com/sportarchive/CloudProcessingEngine

The CPE detailed documentation is here: http://sportarchive.github.io/CloudProcessingEngine/

## CT Documentation

To understand all about Cloud Transcode and the transcoding activities,
head to the CT documentation here: http://sportarchive.github.io/CloudTranscode/

We explain how to create transcoding jobs and detail all available transcoding options.

# Contributing

We need help from the community to develop other types of transcoding:

   - Audio
   - Image
   - Document

The transcoders classes are already created, they just need to be implemented. (Check the `src/activities/transcoders/` folder)

Thanks for contributing !

# FFMpeg performance benchmark on Amazon EC2

Download the spreadsheet to compare the different Amazon EC2 instances cost and performances running FFMpeg:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark/benchmark-aws-ffmpeg.xlsx

