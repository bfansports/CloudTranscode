# What is Cloud Transcode ?
Cloud Transcode is a custom distributed transcoding stack using Amazon AWS services.

The goal of this project is to create an open source, scalable and cheap distributed transcoding platform where users have complete control over
performance and cost. 

We start with video transcoding which is the most costly. Today's commercial solutions for video transcoding are way too expensive for large volumes. With this solution you can transcode large quantity of videos at the pace you want, thus controling your cost. 
We use FFMpeg for video transcoding.

With Cloud Transcode, you control: scale, speed and cost. You can even run everything locally if you want, no Cloud instance required. You
only need an Amazon AWS account and an Internet connection to use the required Amazon services: SWF, SQS and S3. 

It means that you can have a local, hybrid or full cloud setup using Amazon Ec2 instances, it's up to you.

# Transcoding supported
- **Video to Video transcoding**: One video IN, many videos OUT. Any formats and codecs supported by your ffmpeg.
- **Video to Thumbnails transcoding**: Snapshot at certain time in video or intervals snapshot every N seconds.
- **Watermark integration in video**: Take image IN and position it on top of the video. Custom position and transparency.

# High Level Architecture
![Alt text](/../images/high_level_arch.png?raw=true "High Level Architecture")

# Quick start with Vagrant
We create a Vagrant box (Virtual Machine) which provides pre-configured environment to run the stack. You can use Vagrant on any OS and quicky test the stack.

See: https://sportarchive.hackpad.com/Cloud-Transcode-project-poG8vKTC16J#:h=Quick-start-with-Vagrant

# Getting started
Simply type "make" in the top level directory of the project. It will fetch "PHP
composer" and download all dependencies.

Then follow the instructions here: https://sportarchive.hackpad.com/Installation-8zAu2d03Zxr

# Using the stack
Clients using the transcoding stack need to use the CloudTranscodeComSDK conceived to communicate with the stack. With it you can send commands to the stack and receive updates from it as well.
Available here: https://github.com/sportarchive/CloudTranscodeComSDK

# Detailed info 
Find the detailed documentation here: https://sportarchive.hackpad.com/Cloud-Transcode-project-poG8vKTC16J

## FFMpeg performance benchmark on Amazon EC2
Download the spreadsheet to compare the different Amazon EC2 instances cost and performances:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark/benchmark-aws-ffmpeg.xlsx

