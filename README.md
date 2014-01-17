Introduction
============

Cloud Transcode is a custom transcoding stack using FFMPEG and Amazon AWS.

The goal of this project is to create a fully independant video transcoding stack. Will support any media in future (pics, images, audio).

It runs on Amazon Ec2, uses Amazon SWF (Simple Workflow), and Amazon SQS for external communication (input, output).
It runs independently on Amazon, receive JSON input via SQS, pull the input file from S3, validate and transcode it following the input configuration setup, and finaly output result transcoded file(s) back on S3.

Amazon SWF offers the capabilities to scale easily, distributing transcoding tasks across all register "workers" which can run independently virtualy anywhere as long as an Internet connection is available :) Thanks cloud

Install and configure the worker (client) on virtualy any linux and it should work. Port it to your plateform/language if you can ! You will have the power of FFMPEG, distributed in the cloud. Add more workers (e.g: Ec2 instance) and you will have more horse power.

Amazon SQS let you interface with the stack from anywhere. You use the JSON format and you can submit jobs to the stack. You recieve info, errors and updates through SQS too. Just listen to the channel.


Dev specs:
==========

Uses latest PHP5 (coding on Ubuntu 13.10, PHP 5.5)
Uses Amazon PHP SDK: http://aws.amazon.com/sdkforphp/
Latest FFMPEG: https://trac.ffmpeg.org/wiki/UbuntuCompilationGuide

Ongoing:
========
* AWS OpsWork integration for easy deployment: I want to use a custom instance-stored AMI containing all the necessary dependencies to boot up with a working Decider or Worker and start processing incoming tasks. This will be necessary for doing auto scaling. Using Chef, OpsWork can fire configration scripts to update the instance at different stage: boot, configure, shutdown, etc... Using this feature we can start services, and deploy custom config for our stack. My AMI almost work but I'm running into a bug :/


Done:
=====
Basic FFMPEG implementation
Basic SQS implementation
Basic SWF workflow. 3 steps:
* ValidateInputAsset
* TranscodeInputAsset
* ValidateTranscodedAsset


TODO
====
- Use log_out() accross the whole project. Or do better logging ! syslog anyone ?
- Handle all JSON input parameters for transcoding. FFMPEG command mapping. For inspiration see: https://app.zencoder.com/docs/api/encoding 
- Communicate status, progression and errors back through SQS
- Use AWS OpsWork to handle auto deployment. Chef recipe for OpsWork can be found in this repo: https://github.com/sportarchive/cloudTranscodeChef
- Improve Decider to enable worker auto scaling based on load




