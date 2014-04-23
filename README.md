[![Build Status](https://travis-ci.org/sportarchive/CloudTranscode.svg?branch=master)](https://travis-ci.org/sportarchive/CloudTranscode)

# What is Cloud Transcode ?
Cloud Transcode is a custom distributed transcoding stack using Amazon AWS services.

The goal of this project is to create an open source, scalable and cheap
distributed transcoding platform where users have complete control over
performance and cost. 

We start with video transcoding which is the most costly. Today's commercial solutions for video transcoding are way
too expensive for large volumes. With this solution you can transcode large quantity of videos at the pace you want, thus controling your cost. 

With Cloud Transcode, you control scale, speed and cost. You
can even run everything locally if you want, no Cloud instance required. You
only need an Amazon AWS account and an Internet connection to use the Amazon
services needed: Amnazon SWF, SQS and S3. 

It means that you can have a local, hybrid or full cloud setup using Amazon Ec2
instance, it's up to you.

# Transcoding supported

- *Video to Video transcoding*: One video IN, many videos OUT. Any format and codec supported by ffmpeg.
- Video to Thumbnails transcoding: Snapshot at certain time in video or intervals snapshot every N seconds.
- Watermark integration in video: Take image IN and position it on top of the video. Custom position and transparency.

# Detailed info 
http://sportarchive.github.io/CloudTranscode/

## FFMpeg performance benchmark on Amazon EC2

Download the spreadsheet to compare the different Amazon EC2 instances cost and performances:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark/benchmark-aws-ffmpeg.xlsx

## Getting started

Simply type "make" in the top level directory of the project. It will fetch PHP
composer and download all dependencies.

Then follow the instructions here: https://sportarchive.hackpad.com/Cloud-Transcode-Installation-Deployment-8zAu2d03Zxr

