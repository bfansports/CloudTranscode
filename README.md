[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/sportarchive/CloudTranscode?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/?branch=master)

### Updates 2020/05/09
Update to FFMpeg 4.2

# What is Cloud Transcode?
Cloud Transcode (CT) is your own distributed transcoding stack. With it you can transcode media files in a distributed way, at scale.

## Goal
The goal of this project is to create an open source, scalable and cheap distributed transcoding platform where users have complete control over performance and cost.

We started with video transcoding as it is the most costly, but the goal is to transcode any type media files (audio, documents and images). We use FFMpeg for video transcoding. CT also image transcoding using ImageMagic.

Today's commercial solutions for video transcoding are very expensive for large volumes. With this solution you can transcode large quantity of files at the pace you want, thus controling your cost.

## Benefits
With Cloud Transcode, you control: scale, speed and cost. You can run everything locally if you want, no Cloud instance required. Or you can deploy on AWS EC2, Beanstalk or Docker containers.

Your workers only need an Internet connection to use the required Amazon services: SFN (AWS Step Functions) and S3. It means that you can have a local, hybrid or full cloud setup. It's up to you.

## Activity supported

List of actions this stack allow you to do very quickly and at scale.

   - **Custom FFMpeg command**: Run and distribute your own `ffmpeg` commands
   - **Image to Image transcoding**: Run and sitribute your own ImageMagic `convert` commands
   - **Validate/Probe Asset**: Run `ffprobe` to get the `mime type` of an asset
   - **Transcode from HTTP**: Pull files from HTTP/S and transcode them on the fly at scale. Result files are put into AWS S3.
   - **Video to Video transcoding**: JSON input: One video IN, many videos OUT. Any formats and codecs supported by your ffmpeg.
   - **Video to Thumbnails transcoding**: JSON input: Snapshot at certain time in video or intervals snapshot every N seconds. Keep image ratio.
   - **Watermark integration in video**: JSON input: Take image IN and position a watermark on top of the video. Custom position and transparency. Keep image ratio.


# How it works?

Cloud Transcode is a set of "activities" that are standalone scripts implementing the `CpeActivity` class located in the CloudProcessingEngine-SDK:
https://packagist.org/packages/sportarchive/cloud-processing-engine-sdk

Those Activities listen to the Amazon SFN (Step functions) service for incoming tasks to process (You need an Amazon AWS account, yes). One activity will process one type of Tasks.

Tasks and Workflows (aka `State Machine`) are defined in the AWS SFN console, and are identified by their AWS ARNs (AWS resources identifier). You can then start `N` activity workers that will start listening for incoming jobs and execute them.

You can scale your infrastructure in AWS based on your volume, capacity, cost, resources, etc.
You can run those SFN Activities on Docker, which is recommended. A Dockerfile is provided for you.
But you can run on anything and anywhere.

Your client applications can initiate new SFN workflows using the AWS SDK of your choice. The client apps will pass JSON input date to AWS SFN. <br>
SFN will then pass this input to your activities, which will then return a JSON output. This output can be passed on to the next activities.

Thanks to the `CloudProcessingEngine-SDK` you can build your own workflow and call any activities you want. You can implement your own activities as well.

Cloud Transcode could use help on the following Activities if you are interested in participating:

   - Document transcoding: DOC to PDF for example
   - Audio manipulation: Maybe this can be already done by FFMpeg. Never tested if FFmpeg supports it or if there is a better tool for Audio.
   - Other ideas?

# Getting Started

## AWS Account

You need one. You will use the following AWS services:
S3, SFN, IAM, ECS (Docker cluster - Ideal), EC2, and several more.

The ideal setup in on AWS ECS which provides EC2 instances management and Docker container management.
You can use our Dockerfile to add your own configuration files to the final Docker image.
Create a ECS Activity, and tell it to run your image. You can then scale the cluster and auto adjust.

## State Machine

You must create a State Machine workflow in the AWS Step Function console to make things work.

In the folder `state_machines` of the project, you will find a basic transcoding workflow for SFN. It validates the input and then processes ALL the outputs you want in one activity.<br>
In sequence, this workflow calls:

   - 1 -> ValidateAssetActivity
   - N -> TranscodeAssetActivity

One TranscodeAssetActivity worker processes all outputs wanted, in sequence, not in parallel.

> *Note:* I couldn't make SFN transform on the fly the input data given to the activities.
> The array of "output files wanted" could have been split and one activity could be started for each output in the array, thus allowing parallel  transcoding.
> To achieve parallel transcoding, you need to have an intermediate activity that splits the input and initiate new workflows, each with only one output wanted.

## Run Activities

Activities are standalone scripts writen in PHP (legacy reasons, but it's clean!) that can be started in command line.

```
bash $> ./src/activities/ValidateAssetActivity.php -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:ValidateAsset
bash $> ./src/activities/TranscodeAssetActivity.php -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:TranscodeAsset
```

Or using Docker

```
$> sudo docker run 501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode:4.2 ValidateAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:ValidateAsset
$> sudo docker run 501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode:4.2 TranscodeAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:TranscodeAsset
```

Using these commands, you can start an activity worker that processes one type of activity. In these cases `ValidateAssetActivity` and `TranscodeAssetActivity`

## Integrate with your client app

Your Cloud Transcode workers (or custom workers) will do the work as wanted but your client applications that initiated the workflow will not have any idea of what is going on.

In order to hook your client applications with Cloud Transcode, you must implement a PHP Interface.

Your Interface will contain all the callback methods that will be called when events occur in your CT workflow:

   - onStart
   - onHeartbeat
   - onFail
   - onSuccess
   - onTranscodeDone

You must implement the `CpeClientInterface.php` interface located in the `CloudProcessingEngine-SDK` project:

   - Composer: https://packagist.org/packages/sportarchive/cloud-processing-engine-sdk
   - Github: https://github.com/sportarchive/CloudProcessingEngine-SDK

In order to pass this class to your Activity worker, you have to provide its location in command line using the [-C <client class path>] option.

That means that if you use Docker, you must create your own Docker image based on the one provided in the project, which will contain your custom classes in it.

A Dockerfile like this for example:


``` Dockerfile
FROM 501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode:4.2
MAINTAINER bFAN Sports

COPY clientInterfaces /usr/src/clientInterfaces
```

Just create a new folder, put the Dockerfile above in it and clone the CloudTranscode repository in it too.
Then build your own image as follow: `sudo docker build -t 501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode-prod .`

Then you can start your workers like this:

```shell
$> sudo docker run 501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode-prod:4.2 ValidateAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:ValidateAsset -C /usr/src/clientInterfaces/ValidateAssetClientInterfaces.php
$> sudo docker run 501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode-prod:4.2 TranscodeAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:TranscodeAllOutputAssets -C /usr/src/clientInterfaces/TranscodeAllOutputAssetsClientInterfaces.php
$> sudo docker run 501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode-prod:4.2 TranscodeAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:TranscodeImageAsset -C /usr/src/clientInterfaces/TranscodeImagesAssetsClientInterfaces.php
$> sudo docker run 501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode-prod:4.2 TranscodeAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:OnDemandTranscodeAsset -C /usr/src/clientInterfaces/OnDemandTranscodeAssetClientInterfaces.php
```

As you can see, you can create many SFN tasks. Each task will execute the same activity code, but they are connected to different client applications using different Interface classes.
This way you can have several sets of workers for all your workflows and client applications.
Each worker will be processing only certain tasks. They are hooked to different client applications using the custom interface classes.

## Input format

In the `input_sample` folder, we provide input samples that you can edit to match your setup.

The main idea is:

   - One `input` section which describes what must be processed
   - One `output` section which contains a list of wanted output. Each output is describe by a JSON and specifies the work to be done and where the resulting file should go.

A Simple example that takes a `.mp4` as input and generate two thumbnails and a proxy video using a FFmpeg template:

``` json
{
    "input_asset": {
        "type": "VIDEO",
        "bucket": "cloudtranscode-eu-dev",
        "file": "/input/video1.mp4"
    },
    "output_assets": [
        {
            "type": "THUMB",
            "mode": "snapshot",
            "bucket": "cloudtranscode-eu-dev",
            "path": "/output/",
            "file": "thumbnail_sd.jpg",
            "s3_rrs": true,
            "s3_encrypt": true,
            "size": "-1:159",
            "snapshot_sec": 5
        },
        {
            "type": "THUMB",
            "mode": "snapshot",
            "bucket": "cloudtranscode-eu-dev",
            "path": "/output/",
            "file": "thumbnail_hd.jpg",
            "s3_rrs": true,
            "s3_encrypt": true,
            "size": "-1:720",
            "snapshot_sec": 5
        },
        {
            "type": "VIDEO",
            "bucket": "cloudtranscode-eu-dev",
            "path": "/output/",
            "file": "video1.mp4",
            "s3_rrs": true,
            "s3_encrypt": true,
            "keep_ratio": false,
            "no_enlarge": false,
            "preset": "360p-4.3-generic",
            "watermark": {
                "bucket": "cloudtranscode-eu-dev",
                "file": "/no-text-96px.png",
                "size": "96:96",
                "opacity": 0.2,
                "x": -20,
                "y": -20
            }
        }
    ]
}

```

You can also submit custom FFmpeg commands, or specify as many output as you want here.

> *Note:* As mentioned above, those outputs are processed in sequence by the same worker, NOT in parallel.

# Contributing

Feel free to send us your Pull Requests.

Thanks for contributing !

# FFmpeg

CloudTranscode uses FFmpeg 4.2

The CloudTranscode Docker image is based on two other images:

   - https://hub.docker.com/r/sportarc/ffmpeg/
   - https://hub.docker.com/r/sportarc/cloudtranscode-base/


# FFMpeg performance benchmark on Amazon EC2

*This is already a little old and needs update with the latest AWS instances*

Download the spreadsheet to compare the different Amazon EC2 instances cost and performances running FFMpeg:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark/benchmark-aws-ffmpeg.xlsx
