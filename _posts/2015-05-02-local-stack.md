---
layout: page
title: "Run a local stack"
category: start
date: 2015-05-02 16:45:18
---

The beauty of Cloud services is that you can use them accross the Internet. Wherever you are.

Cloud Transcode uses Cloud services to:

   - Track workflows: [SWF](http://aws.amazon.com/swf/)
   - Communicate: [SQS](http://aws.amazon.com/sqs/)
   - Store files: [S3](http://aws.amazon.com/s3/)

As long as you have an Internet connection and an AWS account, <b>you can run it anywhere.</b>

### Requirements

We will run the stack locally in a Virtual Machine. We are using VirtualBox in this example, but VMWare works as well. 

We are using Vagrant to start the VM and configure it for you.

More about [Vagrant](https://docs.vagrantup.com/v2/installation/index.html)

#### VirtualBox and Vagrant

Install both applications on your system.

   - [Install VirtualBox](https://www.virtualbox.org/wiki/Downloads) 
   - [Install Vagrant](http://www.vagrantup.com/downloads) 

Vagrant will start an Ubuntu VM on your local machine. In the VM, Vagrant will install Docker. The Docker container will run the whole stack.

In production, you won't need Vagrant. You would deploy N Docker containers on your machines.

Each container would have a different role:

{% include ct_roles.md %}

### Get Started

Now let's get to the fun part. We'll get the stack running.

#### Install the stack

    $> git clone https://github.com/sportarchive/CloudTranscode.git

#### Run the stack

    $> cd CloudTranscode
    $> vagrant up

#### Monitor the stack

Check the log files located in the ./logs folder.

You should have four log files:

   - Decider.log
   - InputPoller.log
   - ValidateAsset.log
   - TranscodeAsset.log

Each log file, contains the output of a program running.

### Pilot the stack

It's nice, you have a stack running but it doesn't do s#%t yet ...


