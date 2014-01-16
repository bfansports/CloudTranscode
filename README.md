cloudTranscode
==============

Custom transcoding stack using FFMPEG and Amazon AWS.

The goal of this project is to create a fully independant video transcoding stack. Will support any media in future (pics, images, audio).

It runs on Amazon Ec2, uses Amazon SWF (Simple Workflow), and Amazon SQS for external communication (input, output).
It runs independently on Amazon, receive JSON input via SQS, pull the input file from S3, validate and transcode it following the input configuration setup, and finaly output result file(s) back on S3.

Amazon SWF offers the capabilities to scale easily, distributing transcoding tasks across all register "workers" which can run independently virtualy anywhere as long as there is an Internet connection. Power of the cloud, hello :)

Install and configure the client on virtualy any linux and it should work. Port it to your plateform/language if you can ! You will have the power of ffmpeg, distributed in the cloud. Add more workers (e.g: Ec2 instance) and you will have more horse power.

Amazon SQS let you interface with the stack from anywhere. You use the right language and you can submit jobs to the stack. You recieve info, errors and updates through SQS too. Just listen to the channel.


Specs:
======

Uses latest PHP5 (coding on 5.5)
Uses Amazon PHP SDK
Object Oriented code
Basic FFMPEG implementation


Dependencies:
=============


TODO
====
- Use log_out() accross the whole project. Or do better logging !
- Handle all JSON input parameters for transcoding. FFMPEG command mapping.
- Better error handling
