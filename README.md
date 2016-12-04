[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/sportarchive/CloudTranscode?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge) 
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/?branch=master) 

# Updates

AWS anounced AWS Step Functions which is basically an AWS implementation of our decider. You will describe your execution flow visually, it will generate a "plan" and then executes Lambda functions or activities in workers. This new services will be great for CloudTranscode as it will simplify the setup greatly. It will remove the need for a Decider. We could also get rid of the InputPoller.php and initiate workflow directly from the client applications. 

We will work on moving to this soon. If you are interested in participating, let us know!

# What is Cloud Transcode ?
Cloud Transcode (CT) is your own distributed transcoding stack. With it you can transcode media files in a distributed way, at scale.

## Goal
The goal of this project is to create an open source, scalable and cheap distributed transcoding platform where users have complete control over performance and cost. 

We started with video transcoding as it is the most costly, but the goal is to transcode any type media files (audio, documents and images). We use FFMpeg for video transcoding. CT also image transcoding using ImageMagic.

Today's commercial solutions for video transcoding are very expensive for large volumes. With this solution you can transcode large quantity of files at the pace you want, thus controling your cost. 

## Benefits
With Cloud Transcode, you control: scale, speed and cost. You can run everything locally if you want, no Cloud instance required. Or you can deploy on AWS EC2, Beanstalk or Docker containers. 

Your workers only need an Internet connection to use the required Amazon services: SWF, SQS and S3. It means that you can have a local, hybrid or full cloud setup. It's up to you.

## Transcoding supported

   - **Video to Video transcoding**: One video IN, many videos OUT. Any formats and codecs supported by your ffmpeg.
   - **Video to Thumbnails transcoding**: Snapshot at certain time in video or intervals snapshot every N seconds. Keep image ratio.
   - **Watermark integration in video**: Take image IN and position a watermark on top of the video. Custom position and transparency. Keep image ratio.
   - **Image to Image transcoding**: Use all the features ImageMagic (`convert` command) offers.

We are working to support ALL FFmpeg options.

# How to use CT ?

Cloud Transcode is a set of "activities" that are executed by the Cloud Processing Engine (CPE) project. 

With CPE you can execute workflows (chain of tasks) in a distributed way using the SWF cloud service. It initiate tasks executions on workers that you deploy. You can deploy your workers anywhere: locally or in the Cloud. Your workers (machines running your tasks) only need an Internet connection to access the AWS services.

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
   - Documents

The transcoders classes are already created, they just need to be implemented. (Check the `src/activities/transcoders/` folder)

Thanks for contributing !

# FFMpeg performance benchmark on Amazon EC2

Download the spreadsheet to compare the different Amazon EC2 instances cost and performances running FFMpeg:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark/benchmark-aws-ffmpeg.xlsx

