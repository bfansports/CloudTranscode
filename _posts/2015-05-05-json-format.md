---
layout: page
title: "JSON format"
category: deep
date: 2015-05-05 23:36:18
order: 30
---

CloudTrancode is using JSON exclusively for communication, configuration files, video preset files, etc.

Below is the definition of the JSON format understood by Cloud Transcode.

### Validation

In order to validate the JSON data provided, we use JSON-Schemas: [](http://json-schema.org/)

We use an implementation of JSON schemas by: https://github.com/justinrainbow/json-schema. You define the data type and structure of the data you are expecting, and validate the data against the schems.

All JSON schemas are located in: https://github.com/sportarchive/CloudTranscode/tree/master/json_schemas 

### Details

The format details are part of the SDK documentation here: http://sportarchive.github.io/CloudTranscode-SDK/
