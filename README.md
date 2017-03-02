[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/sportarchive/CloudTranscode?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sportarchive/CloudTranscode/?branch=master)

### Updates 2017/03/02

The new version of Cloud Transcode is up and running. It now uses AWS Step Functions (SFN).

The legacy documentation (http://blog.bfansports.com/CloudTranscode/) is not yet up to date, but the JSON format mentioned in it is still partialy valid. For input example, just look into the 'input_sample' folder.

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

   - **Validate/Probe Asset**: Get the mime type of an asset and attempt to run `ffprobe`. Returns mime and ffprobe results.
   - **Custom FFMpeg command**: Run your own ffmpeg command
   - **Transcode from HTTP**: No need to put your input file in S3. We can pull it from HTTP and transcode it on the fly. Result files are still put into S3.
   - **Video to Video transcoding**: One video IN, many videos OUT. Any formats and codecs supported by your ffmpeg.
   - **Video to Thumbnails transcoding**: Snapshot at certain time in video or intervals snapshot every N seconds. Keep image ratio.
   - **Watermark integration in video**: Take image IN and position a watermark on top of the video. Custom position and transparency. Keep image ratio.
   - **Image to Image transcoding**: Use all the features ImageMagic (`convert` command) offers.


# How it works?

Cloud Transcode is a set of "activities" that are standalone scripts implementing the `CpeActivity` class located in the CloudProcessingEngine-SDK:
https://packagist.org/packages/sportarchive/cloud-processing-engine-sdk

Those activities listens to the AWS SFN (AWS Step functions) service for incoming tasks to process. One activity processes on type of tasks.
Tasks are defined in the SFN console, and are identified by their AWS ARNs (AWS resources identifier).

You can start N activity workers. You can scale your deployment based on your volume, capacity, cost, resources, etc.
You can run those activities with Docker, which is recommended. A Dockerfile is provided for you.

Your client applications can initiate new SFN workflows using the AWS SDK of your choice. The client apps will pass JSON input date to AWS SFN. <br>
SFN will then pass this input to your activities, which will then return a JSON output. This output can be passed on to the next activities.

You can build your own workflow and call any activities you want. You can implement your own activities as well. <br>
For example CT still needs those activities:

   - Document transcoding: DOC to PDF for example
   - Audio manipulation: Maybe this can be already done by FFMpeg? Never tested by if FFmpeg supports it or if there is a better tool for Audio.
   - Other ideas?

## State Machine

You must create a State Machine workflow in the AWS Step Function console to make things work.

In the folder `state_machines` you will find a basic transcoding workflow for SFN. It validates the input and then processes ALL the outputs you want in one activity.<br>
In sequence, this workflow calls:

   - 1 -> ValidateAssetActivity
   - N -> TranscodeAssetActivity

One TranscodeAssetActivity worker processes all outputs wanted, in sequence, not in parallel.

> *Note:* I couldn't make SFN transform on the fly the input data given to the activities.
> The array of "output files wanted" could have been split and one activity could be started for each output in the array, thus allowing parallel  transcoding.
> To achieve parallel transcoding, you need to execute several workflows, each with only one output wanted.
>
> Another solution, is to create a Validate SFN state machine, that only validates, and a Transcode SFN workflow that only transcodes.
> Your client applications would have to initiate them in sequence and thus keep track of status, and  implement some state machine of its own. Not good either.
>
> If SFN was implementing a way to manipulate the JSON, like you can do with Mapping Template on AWS API Gateway, it would be great.<br>
> See: http://docs.aws.amazon.com/apigateway/latest/developerguide/api-gateway-mapping-template-reference.html

## Run Activities

Activities are standalone scripts that can be started in command line.

``` bash
$> ./src/activities/ValidateAssetActivity.php -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:ValidateAsset
$> ./src/activities/TranscodeAssetActivity.php -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:TranscodeAsset
```

Or using Docker

```
$> sudo docker run sportarc/cloudtranscode ValidateAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:ValidateAsset
$> sudo docker run sportarc/cloudtranscode TranscodeAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:TranscodeAsset
```

Using this syntax you will start an activity worker that processes one type of activity.

## Integrate with your client app

Your workers will do the work as wanted but your client applications will not have any idea of what is going on.

In order to hook your client applications with CT, you must implement a class/interface with CT.

Your class will contain all the callback methods that will be called when events occur in your CT workflow:

   - onStart
   - onHeartbeat
   - onFail
   - onSuccess
   - onTranscodeDone

You must implement the `CpeClientInterface.php` interface located in the `CloudProcessingEngine-SDK` project:

   - Composer: https://packagist.org/packages/sportarchive/cloud-processing-engine-sdk
   - Github: https://github.com/sportarchive/CloudProcessingEngine-SDK

In order to pass this class to your Activity worker, you have to provide its location in command line using the [-C <client class path>] option.

That means that if you use Docker, you must create your own Docker image based on the CloudTranscode one, which will contain your custom classes.

A Dockerfile like this for example:


``` Dockerfile
FROM sportarc/cloudtranscode:3.2.2
MAINTAINER Sport Archive, Inc.

COPY clientInterfaces /usr/src/clientInterfaces
```

Just create a new folder, put the above Dockerfile in it and a clone the CloudTranscode repository in it too.
Then build your own image as follow: `sudo docker  build -t sportarc/cloudtranscode-prod .`

Then you can start your workers like this:

```
$> sudo docker run sportarc/cloudtranscode-prod ValidateAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:ValidateAsset -C /usr/src/clientInterfaces/ValidateAssetClientInterfaces.php
$> sudo docker run sportarc/cloudtranscode-prod TranscodeAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:TranscodeAllOutputAssets -C /usr/src/clientInterfaces/TranscodeAllOutputAssetsClientInterfaces.php
$> sudo docker run sportarc/cloudtranscode-prod TranscodeAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:TranscodeImageAsset -C /usr/src/clientInterfaces/TranscodeImagesAssetsClientInterfaces.php
$> sudo docker run sportarc/cloudtranscode-prod TranscodeAssetActivity -A arn:aws:states:eu-west-1:XXXXXXXXXXXX:activity:OnDemandTranscodeAsset -C /usr/src/clientInterfaces/OnDemandTranscodeAssetClientInterfaces.php
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

We are open to external contributions! Feel free to send us your Pull Requests.

Thanks for contributing !

# FFmpeg

The Cloud Transcode Docker image is based on two other images:

   - https://hub.docker.com/r/sportarc/ffmpeg/: Base image containing: Ubuntu 14, PHP CLI 5.6, FFmpeg
   - https://hub.docker.com/r/sportarc/cloudtranscode-base/: Adds `ImageMagic` to the above image


# FFMpeg performance benchmark on Amazon EC2

*This is already a little old and needs update with the latest AWS instances*

Download the spreadsheet to compare the different Amazon EC2 instances cost and performances running FFMpeg:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark/benchmark-aws-ffmpeg.xlsx
