---
layout: page
title: "InputPoller"
category: struct
date: 2015-05-06 17:55:23
order: 300
---

There is one InputPoller running per clients using the stack. Each InputPoller listens to the client SQS input queue for incoming orders.

When a new command comes in, the InputPoller execute the proper action:

   - Start a job (Start SWF workflow)
   - Cancel a job (Cancel SWF workflow)
   - etc
