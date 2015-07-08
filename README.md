## Updates [07/07/2015]
> The first major Beta community release is out<br>
> We're refactor this project to make it more generic and more usable.

Detailed changes:
   - The Core (Pollers and Decider) has moved out of this repo to the CPE project: https://github.com/sportarchive/CloudProcessingEngine
   - This repo only retains the actual transcoding code executed by your workers.

> If you have forked this project, you will need to update your fork.

# What is Cloud Transcode ?
Cloud Transcode is a set of transcoding activities for transcoding media files at scale. It is your own distributed transcoding stack.

The goal of this project is to create an open source, scalable and cheap distributed transcoding platform where users have complete control over
performance and cost. 

We start with video transcoding which is the most costly, but the goal is to transcode any media (audio, documents and images). We use FFMpeg for video transcoding.

Today's commercial solutions for video transcoding are very expensive for large volumes. With this solution you can transcode large quantity of videos at the pace you want, thus controling your cost. 

With Cloud Transcode, you control: scale, speed and cost. You can run everything locally if you want, no Cloud instance required. You only need an Amazon AWS account and an Internet connection to use the required Amazon services: SWF, SQS and S3. 

It means that you can have a local, hybrid or full cloud setup on Amazon Ec2 instances, it's up to you.

## Transcoding supported

   - **Video to Video transcoding**: One video IN, many videos OUT. Any formats and codecs supported by your ffmpeg.
   - **Video to Thumbnails transcoding**: Snapshot at certain time in video or intervals snapshot every N seconds.
   - **Watermark integration in video**: Take image IN and position a watermark on top of the video. Custom position and transparency.

# How to use CT ?

Cloud Transcode relies on the Cloud Processing Engine (CPE) project which allows processing at scale. Using CPE you can execute workflows (chain of tasks) in a distributed way and at scale locally or in the Cloud. Your workers (machines running your tasks) only need an Internet connection to access the AWS services.

CPE allow any type of batch processing at scale and relies on two AWS services:

   - SWF: Simple Workflow
   - SQS: Simple Queue Messaging

**You need to clone the CPE project to get going with Cloud Transcode.**

So head to the project page here and see what you can do with CPE: https://github.com/sportarchive/CloudProcessingEngine<br>
The CPE detailed documentation is here: http://sportarchive.github.io/CloudProcessingEngine/

## CT Documentation

To understand all about Cloud Transcode and the transcoding activities,
head to the detailed documentation here: http://sportarchive.github.io/CloudTranscode/

# Contributing

We need help from the community to develop other type of transcoding:

   - Audio
   - Image
   - Document

The transcoders PHP files are already created, they just need to be implemented.

Thanks for contributing !

# FFMpeg performance benchmark on Amazon EC2

Download the spreadsheet to compare the different Amazon EC2 instances cost and performances running FFMpeg:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark/benchmark-aws-ffmpeg.xlsx

