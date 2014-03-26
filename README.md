[![Build Status](https://travis-ci.org/Ceache/CloudTranscode.svg?branch=master)](https://travis-ci.org/Ceache/CloudTranscode)

# What is Cloud Transcode ?
Cloud Transcode is a custom distributed transcoding stack using FFMpeg and
Amazon AWS services.

The goal of this project is to create an open source, scalable and cheap
distributed transcoding platform where users have complete control over
performance and cost. Today's commercial solution for video transcoding are way
too expensive for large volumes. With this solution you can transcode large
volume at the pace and price you want. 

With Cloud Transcode, you control scale, and transcoding speed and cost. You
can even run everything locally if you want, no Cloud instance required. You
only need an Amazon AWS account and an Internet connection to use the Amazon
services needed for Cloud Transcode: SWF, SQS and S3. 

It means that you can have a local, hybrid or full cloud setup using Amazon Ec2
instance, it's up to you.

# Detailed info 
http://sportarchive.github.io/CloudTranscode/

## FFMpeg performance benchmark on Amazon EC2

Download the spreadsheet to compare the different Amazon EC2 instances cost and performances:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark/benchmark-aws-ffmpeg.xlsx

## Getting started

Simply type "make" in the top level directory of the project. It will fetch PHP
composer and download all dependencies.
