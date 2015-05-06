---
layout: page
title: "Run the stack"
category: start
date: 2015-05-05 16:45:18
order: 4
---

Now let's get to the fun part. We'll get the stack running.

### Run the stack

    $> cd CloudTranscode
    $> vagrant up

It will take some time to start. Vagrant will download Ubuntu, install Docker and more.

### Monitor the stack

Check the log files located in the ./logs folder.

You should have four log files:

   - Decider.log
   - InputPoller.log
   - ValidateAsset.log
   - TranscodeAsset.log

Each log file, contains the output of a program running.

<br>

<p>
<h4><a href="use-the-stack.html">Next: Pilot the stack</a></h4>
</p>
