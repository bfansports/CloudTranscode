---
layout: default
title: "Cloud Transcode documentation"
---

## Welcome

Here you will find the necessary information to get going with Cloud Transcode.

<br>

### What is Cloud Transcode?

Cloud Transcode is an Open Source transcoding stack. It uses Amazon AWS services for managing workflows, storage and communication.

The goal of this project is to create a distrubuted transcoding platform for organizations to have complete control over transcoding performance and cost.

We started by implementing video transcoding as it's the most costly, but the framework will be able to transcode any media types (audio, documents and images).

### Why?

Today's commercial transcoding solutions are very expensive for large volumes. With this solution you can transcode large quantity of videos at the pace you want, thus controling your cost.

With Cloud Transcode, you control: scale, speed and cost.

You can run everything locally if you want, no Cloud instance required. You only need an Amazon AWS account and an Internet connection to use the required Amazon services: SWF, SQS and S3.

It means that you can have a local, hybrid or full cloud setup on Amazon Ec2 instances, it's up to you.

### How can I use it?

Check the Getting Started documentation to get going quickly.

We provide a Docker container for running the stack elements. It's very easy to get started.

It's more time consuming to setup your AWS account properly :)

The Docker container can play the role you want in the stack. There are 3 roles:

{% include ct_roles.md %}

### Supported operations

   - <b>Video to Video transcoding:</b> One video IN, many videos OUT. Any formats and codecs supported by your FFMpeg.
   - <b>Video to Thumbnails transcoding:</b> Snapshot the video at a given time or intervals snapshot every N seconds.
   - <b>Watermark integration in video:</b> Position an image as a watermark on top of the video. Custom position and transparency are supported.

### High level architecture

![High level architecture design]({{ site.url }}./images/high_level_arch.png)

<a href="/../sdk-doc/build/index.html">TEST GOGO</a>
