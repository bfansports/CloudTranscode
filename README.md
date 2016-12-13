[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/sportarchive/CloudTranscode?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/?branch=master)

## Cloud Transcode 2.0 has arrived

This version greatly simplifies the work required to setup Cloud Transcode. No more SQS queues, listener, commander, nor decider.

Thanks to AWS Steps Functions, the new AWS service, you can now create your workflow in the console which removes the need for deciders.

You just need to deploy Activities for processing your jobs: ValidateAssetActivity ad TranscodeAssetActivity are the two activities CT supports.

In order to update your client application upon progress, success or failure for example, your activity now accepts a custom class implementing an Interface to your application. Updating your DB, or notifying 3rd party apps has never been so easy.

We hope that more of you will start using CT now that it is VERY easy to get going. Read on!

# What is Cloud Transcode ?
Cloud Transcode (CT) is your own distributed transcoding stack. With it you can transcode media files in a distributed way, at scale.

## Goal
The goal of this project is to create an open source, scalable and cheap distributed transcoding platform where users have complete control over performance and cost.

We started with video transcoding as it is the most costly, but the goal is to transcode any type media files (audio, documents and images). We use FFMpeg for video transcoding. CT also image transcoding using ImageMagic.

Today's commercial solutions for video transcoding are very expensive for large volumes. With this solution you can transcode large quantity of files at the pace you want, thus controling your cost.

## Benefits
With Cloud Transcode, you control: scale, speed and cost. You can run everything locally if you want, no Cloud instance required. Or you can deploy on AWS EC2, Beanstalk or Docker containers.

Your workers only need an Internet connection to use the required Amazon services: SWF, SQS and S3. It means that you can have a local, hybrid or full cloud setup. It's up to you.

## Activity supported

   - **Probe Asset**: Get the mime type of an asset and attempt to run `ffprobe`. Returns mime and ffprobe results.
   - **Custom FFMpeg command**: Run your own ffmpeg command
   - **Transcode from HTTP**: No need to put your input file in S3. We can pull it from HTTP and transcode it on the fly. Result files to S3.
   - **Video to Video transcoding**: One video IN, many videos OUT. Any formats and codecs supported by your ffmpeg.
   - **Video to Thumbnails transcoding**: Snapshot at certain time in video or intervals snapshot every N seconds. Keep image ratio.
   - **Watermark integration in video**: Take image IN and position a watermark on top of the video. Custom position and transparency. Keep image ratio.
   - **Image to Image transcoding**: Use all the features ImageMagic (`convert` command) offers.

We are working to support ALL FFmpeg options.

# How to use CT ?

Cloud Transcode is a set of "activities" that are standalone scripts implementing the `CpeActivity` class located in the CloudProcessingEngine-SDK:
https://packagist.org/packages/sportarchive/cloud-processing-engine-sdk

Those activities listens to the AWS Step Function service for incoming task to process.

## State Machine

You must create a State Machine in the AWS Step Function console. This is the default workflow we use at **BFan Sports** to process our videos:

You can then start this workflow from the console or using the AWS SDK. See:
http://docs.aws.amazon.com/step-functions/latest/dg/concepts-state-machine-executions.html

## Run Activities

Activities are standalone scripts that can be started in command line.

``` bash
$> ./src/activities/ValidateAssetActivity.php -h
$> ./src/activities/TranscodeAssetActivity.php -h
```

They can also be ran into Docker. A Docker image is provided which depends from images:

   - https://hub.docker.com/r/sportarc/cloudtranscode-base/

```
$> sudo docker run sportarc/cloudtranscode ValidateAssetActivity
$> sudo docker run sportarc/cloudtranscode TranscodeAssetActivity
```

## Integrate with your client app

You start transcoding from your client application. A web server, an API, anything. Now, how do you update your DB, send notifications, and integrate this transcoding stack with your client app?

This way:

   - Check the CloudProcessingEngine-SDK for the Interface file called: `src/SA/CpeSdk/CpeClientInterface.php`

You must implement this PHP Interface and put your synchronization logic in there. For each event in the task, your interface class will be called and you will get the necessary data to update your DB for example.

In order to pass this class to the Activity, you have to provide its path in command line using the [-C <client class path>] option.

That means that if you use Docker, you must create your own image based on the CloudTranscode one which will contain this class. A Dockerfile like this for example:


``` Dockerfile
FROM sportarc/cloudtranscode-prod
MAINTAINER Sport Archive, Inc.

COPY clientInterfaces/* /etc/cloudtranscode/
```

Just create a folder with this Dockerfile and a clone of the CloudTranscode repository.


# Contributing

We need help from the community to develop other types of transcoding:

   - Audio
   - Documents

The transcoders classes are already created, they just need to be implemented. (Check the `src/activities/transcoders/` folder)

Thanks for contributing !

# FFMpeg performance benchmark on Amazon EC2

Download the spreadsheet to compare the different Amazon EC2 instances cost and performances running FFMpeg:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark/benchmark-aws-ffmpeg.xlsx
