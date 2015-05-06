---
layout: page
title: "Technologies"
category: deep
date: 2015-05-05 19:26:02
order: 0
---

### How it works?

To manage storage, workflows and communication we are using the following Amazon services:

   - Amazon SWF (Simple Workflow),
   - Amazon SQS (Messages) for external communication (input, output), 
   - Amazon S3 for storage. 

#### Transcoding sequence

New transcoding jobs are submitted by clients through Amazon SQS using the proper JSON format. 

When a new job is received by the stack, the following sequence happens: 

   - One worker downloads the input file to be transcoded from Amazon S3 and validates it. If valid, the workflow moves on.
   - N workers transcode the input file into N output files. One worker per output file. Finally all output videos are uploaded back to S3.
   - Jobs and activities updates are communicated through SQS. The clients listen to these updates.

You can have many clients all communicating through separate SQS channels. SQS perform the entitlements.

### Amazon SWF

Amazon SWF offers the capabilities to scale easily by distributing transcoding tasks across several registered "workers". They can run independently from virtually anywhere as long as an Internet connection is available.

It means that you can have workers running locally and others in the cloud (Ec2 or other cloud providers). To start, no need to run Amazon EC2 cloud instances, your desktop will suffice. The only requirement is an Amazon AWS account. You will be billed by Amazon for the use of SWF, SQS and S3. Which is very cheap (few cents for several GB and thousands of transcoding jobs)

### Amazon SQS

Amazon SQS let the stack and its clients communicate, all through Amazon. It's a distributed messaging system. The stack and clients could be anywhere as long as Internet is available.

Following the proper JSON format, you can submit new jobs to the stack. You will receive updates and errors through SQS as well. Just listen to the 'output' channel for updates or send commands to the stack through the 'input' channel: start job, cancel job, etc.
