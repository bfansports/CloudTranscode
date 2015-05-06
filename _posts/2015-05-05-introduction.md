---
layout: page
title: "Introduction"
category: start
date: 2015-05-05 19:41:37
order: 1
---


The beauty of Cloud services is that you can use them accross the Internet. Wherever you are.

<b>Cloud Transcode (CT) is your own Cloud service.</b><br>
You run it Locally or in the Cloud and your clients can use it from anywhere.

CT uses the following Cloud services to make this happen:

   - Track workflows: [SWF](http://aws.amazon.com/swf/)
   - Communicate: [SQS](http://aws.amazon.com/sqs/)
   - Store files: [S3](http://aws.amazon.com/s3/)

As long as you have an Internet connection and an Amazon AWS account, <b>you can run it anywhere and use it from anywhere.</b>

### Concept

On one side there is the CT Stack, running somewhere in the Cloud or Locally.

On the other side, there are Clients, using the Stack for transcoding stuffs. They send jobs to the stack and listen for updates from the stack.

Each client should have its own AWS account. Clients are listed and referenced in the CT Stack main configuration file.

<b>Note:</b> You can have the stack and the clients running on the same AWS account. It's not recommended in production for clarity and security purposes. 

### Communication: SQS & JSON

Clients and Stack communicate through SQS using JSON messages.

Each client is assigned two SQS queues:

   - input queue: Used by the client to send orders to the stack. The stack listen to it.
   - output queue: Used by the stack to send message back to the clients. The clients listen to their respective 'output' queue.

Entitlements are set on the Queues such as the client can read from the 'output' queue and write to the 'input' queue. The oposite entitlements are set for the stack.

### Storage: S3

File storage is done on AWS S3.

Each client must configure two S3 buckets:

   - input bucket: Contains the input files to be transcoded. The stack must be entitled by the client to read from it.
   - output bucket: Receive transcoded files from the Stack. The Stack must be entitled by the client to write in it.

### Usage

The stack is packaged in a Docker container for easy deployment.

Any applications (Clients) can use it as long as your application can send JSON messages to the SQS service.

#### SDK

To make it easier, we have create a SDK that interact with SQS for you.

Visit the SDK documentation here: <a href="http://sportarchive.github.io/CloudTranscode-SDK/" target="_blank">http://sportarchive.github.io/CloudTranscode-SDK/</a>

The first implementation of this SDK is in PHP: https://github.com/sportarchive/CloudTranscode-PHP-SDK

<br>

<p>
<h4><a href="#">Next: Setup AWS</a></h4>
</p>
