---
layout: page
title: "Entitlements"
category: infra
date: 2015-05-05 20:08:28
---



#### 
Clients using the CT Stack need to grant S3 access to the Stack:
   - READ access to a S3 bucket as input, so the stack can download S3 files.
   - WRITE access to an S3 bucket as output, so resulting transcoded files can be upload to it.

To entitle the stack to access its buckets, Clients must setup a "Bucket Policy" on each bucket. 
