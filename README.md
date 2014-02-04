# What is Cloud Transcode ?
Cloud Transcode is a custom video transcoding stack using FFMpeg and Amazon AWS services such as: SWF, SQS and S3.

The goal of this project is to create an open source, scalable and cheap video transcoding plateform where users have complete control over performance and cost. Today's commercial solution for video transcoding are way too expensive for large volume. With this solution you can transcode large volume at the pace and price you want. 

With Cloud Transcode, you control your scale, transcoding speed and cost. You can even run everything locally if you want, no Cloud instance required! You only need an Amazon AWS account and an Internet connection to use the Amazon Cloud services: SWF, SQS and S3. I personaly run everything locally while developing and testing. It means that you can have a local, hybrid or full cloud setup using Amazon Ec2 instance, it's up to you.

# Detailed info 
http://sportarchive.github.io/CloudTranscode/

## FFMpeg performance benchmark on Amazon EC2

Download the spreadsheet to compare the different Amazon EC2 instances cost and performance:
https://github.com/sportarchive/CloudTranscode/blob/master/benchmark-aws-ffmpeg.xlsx
