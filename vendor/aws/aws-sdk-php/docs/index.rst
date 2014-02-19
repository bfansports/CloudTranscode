===============
AWS SDK for PHP
===============

.. toctree::
    :hidden:

    awssignup
    requirements
    installation
    quick-start
    migration-guide
    side-by-side

    credentials
    configuration
    feature-commands
    feature-waiters
    feature-iterators
    feature-models
    feature-facades
    performance
    faq

    service-autoscaling
    service-cloudformation
    service-cloudfront
    service-cloudfront-20120505
    service-cloudsearch
    service-cloudtrail
    service-cloudwatch
    service-datapipeline
    service-directconnect
    service-dynamodb
    service-dynamodb-20111205
    service-ec2
    service-elasticache
    service-elasticbeanstalk
    service-elasticloadbalancing
    service-elastictranscoder
    service-emr
    service-glacier
    service-iam
    service-importexport
    service-kinesis
    service-opsworks
    service-rds
    service-redshift
    service-route53
    service-s3
    service-ses
    service-simpledb
    service-sns
    service-sqs
    service-storagegateway
    service-sts
    service-support
    service-swf
    feature-dynamodb-session-handler
    feature-s3-stream-wrapper

The **AWS SDK for PHP** enables PHP developers to use `Amazon Web Services <http://aws.amazon.com/>`_ from their PHP
code, and build robust applications and software using services like Amazon S3, Amazon DynamoDB, Amazon Glacier, etc.
You can get started in minutes by installing the SDK through Composer — by requiring the ``aws/aws-sdk-php`` package —
or by downloading the standalone `aws.zip <http://pear.amazonwebservices.com/get/aws.zip>`_ or
`aws.phar <http://pear.amazonwebservices.com/get/aws.phar>`_ files.

Getting Started
---------------

* Before you use the SDK

  * `Sign up for AWS and get your AWS access keys <http://aws.amazon.com/developers/access-keys/>`_
  * :doc:`Verify that your system meets the minimum requirements for the SDK <requirements>`
  * :doc:`Install the AWS SDK for PHP <installation>`

* Using the SDK

  * :doc:`quick-start` – Everything you need to know to use the AWS SDK for PHP
  * `Sample Project <http://aws.amazon.com/developers/getting-started/php/>`_

* Migrating from Version 1 of the SDK?

  * :doc:`migration-guide` – Migrating from Version 1 of the SDK to Version 2
  * :doc:`side-by-side` – Using Version 1 and Version 2 of the SDK side-by-side in the same project

In-Depth Guides
---------------

* :doc:`credentials`
* :doc:`configuration`
* SDK Features

  * :doc:`feature-iterators`
  * :doc:`feature-waiters`
  * :doc:`feature-commands`
  * :ref:`Parallel Commands <parallel_commands>`
  * :doc:`feature-models`
  * :doc:`feature-facades`

* :doc:`faq`
* :doc:`performance`
* `Contributing to the SDK <https://github.com/aws/aws-sdk-php/blob/master/CONTRIBUTING.md>`_
* `Guzzle Documentation <http://docs.guzzlephp.org/en/latest/docs.html>`_

.. _supported-services:

Service-Specific Guides
-----------------------

* Amazon CloudFront

  .. indexlinks:: CloudFront

  * :doc:`Using the older 2012-05-05 API version <service-cloudfront-20120505>`

* Amazon CloudSearch

  .. indexlinks:: CloudSearch

* Amazon CloudWatch

  .. indexlinks:: CloudWatch

* Amazon DynamoDB

  .. indexlinks:: DynamoDb

  * :doc:`Special Feature: DynamoDB Session Handler <feature-dynamodb-session-handler>`
  * :doc:`Using the older 2011-12-05 API version <service-dynamodb-20111205>`

* Amazon Elastic Compute Cloud (Amazon EC2)

  .. indexlinks:: Ec2

* Amazon Elastic MapReduce (Amazon EMR)

  .. indexlinks:: Emr

* Amazon Elastic Transcoder

  .. indexlinks:: ElasticTranscoder

* Amazon ElastiCache

  .. indexlinks:: ElastiCache

* Amazon Glacier

  .. indexlinks:: Glacier

* Amazon Kinesis

  .. indexlinks:: Kinesis

* Amazon Redshift

  .. indexlinks:: Redshift

* Amazon Relational Database Service (Amazon RDS)

  .. indexlinks:: Rds

* Amazon Route 53

  .. indexlinks:: Route53

* Amazon Simple Email Service (Amazon SES)

  .. indexlinks:: Ses

* Amazon Simple Notification Service (Amazon SNS)

  .. indexlinks:: Sns

* Amazon Simple Queue Service (Amazon SQS)

  .. indexlinks:: Sqs

* Amazon Simple Storage Service (Amazon S3)

  .. indexlinks:: S3

  * :doc:`Special Feature: Amazon S3 Stream Wrapper <feature-s3-stream-wrapper>`

* Amazon Simple Workflow Service (Amazon SWF)

  .. indexlinks:: Swf

* Amazon SimpleDB

  .. indexlinks:: SimpleDb

* Auto Scaling

  .. indexlinks:: AutoScaling

* AWS CloudFormation

  .. indexlinks:: CloudFormation

* AWS CloudTrail

  .. indexlinks:: CloudTrail

* AWS Data Pipeline

  .. indexlinks:: DataPipeline

* AWS Direct Connect

  .. indexlinks:: DirectConnect

* AWS Elastic Beanstalk

  .. indexlinks:: ElasticBeanstalk

* AWS Identity and Access Management (AWS IAM)

  .. indexlinks:: Iam

* AWS Import/Export

  .. indexlinks:: ImportExport

* AWS OpsWorks

  .. indexlinks:: OpsWorks

* AWS Security Token Service (AWS STS)

  .. indexlinks:: Sts

* AWS Storage Gateway

  .. indexlinks:: StorageGateway

* AWS Support

  .. indexlinks:: Support

* Elastic Load Balancing

  .. indexlinks:: ElasticLoadBalancing

Articles from the Blog
----------------------

* `Syncing Data with Amazon S3 <http://blogs.aws.amazon.com/php/post/Tx2W9JAA7RXVOXA/Syncing-Data-with-Amazon-S3>`_
* `Amazon S3 PHP Stream Wrapper <http://blogs.aws.amazon.com/php/post/TxKV69TBGSONBU/Amazon-S3-PHP-Stream-Wrapper>`_
* `Transferring Files To and From Amazon S3 <http://blogs.aws.amazon.com/php/post/Tx9BDFNDYYU4VF/Transferring-Files-To-and-From-Amazon-S3>`_
* `Provision an Amazon EC2 Instance with PHP <http://blogs.aws.amazon.com/php/post/TxMLFLE50WUAMR/Provision-an-Amazon-EC2-Instance-with-PHP>`_
* `Uploading Archives to Amazon Glacier from PHP <http://blogs.aws.amazon.com/php/post/Tx7PFHT4OJRJ42/Uploading-Archives-to-Amazon-Glacier-from-PHP>`_
* `Using AWS CloudTrail in PHP - Part 1 <http://blogs.aws.amazon.com/php/post/Tx3HGFCVGT92TS8/Using-AWS-CloudTrail-in-PHP-Part-1>`_
* `Using AWS CloudTrail in PHP - Part 2 <http://blogs.aws.amazon.com/php/post/Tx31JYLN2SC3GHB/Using-AWS-CloudTrail-in-PHP-Part-2>`_
* `Providing credentials to the AWS SDK for PHP <http://blogs.aws.amazon.com/php/post/Tx1F82CR0ANO3ZI/Providing-credentials-to-the-AWS-SDK-for-PHP>`_
* `Using Credentials from AWS Security Token Service <http://blogs.aws.amazon.com/php/post/Tx25ITJRCL1IWT4/Using-Credentials-from-AWS-Security-Token-Service>`_
* `Iterating through Amazon DynamoDB Results <http://blogs.aws.amazon.com/php/post/TxJGHHKBUJO1AL/Iterating-through-Amazon-DynamoDB-Results>`_
* `Sending requests through a proxy <http://blogs.aws.amazon.com/php/post/Tx9FZ2MY1XP7X6/Sending-requests-through-a-proxy>`_
* `Wire Logging in the AWS SDK for PHP <http://blogs.aws.amazon.com/php/post/Tx1W2JMJBQHBNRS/Wire-Logging-in-the-AWS-SDK-for-PHP>`_
* `Streaming Amazon S3 Objects From a Web Server <http://blogs.aws.amazon.com/php/post/Tx2C4WJBMSMW68A/Streaming-Amazon-S3-Objects-From-a-Web-Server>`_
* `Static Service Client Facades <http://blogs.aws.amazon.com/php/post/Tx21B65ULGUTGBP/Static-Service-Client-Facades>`_

Presentations
-------------

Slides
~~~~~~

* `Mastering the AWS SDK for PHP <http://www.slideshare.net/AmazonWebServices/mastering-the-aws-sdk-for-php-tls306-aws-reinvent-2013>`_
* `Getting Good with the AWS SDK for PHP <https://speakerdeck.com/jeremeamia/getting-good-with-the-aws-sdk-for-php>`_
* `Using DynamoDB with the AWS SDK for PHP <http://www.slideshare.net/AmazonWebServices/using-dynamod-bwith-aws-sdk-for-php-tls305>`_
* `Controlling the AWS Cloud with PHP <https://speakerdeck.com/jeremeamia/controlling-the-aws-cloud-with-php>`_

Videos
~~~~~~

* `Mastering the AWS SDK for PHP <http://youtu.be/_zaW2VZB1ok>`_ (AWS re:Invent 2013)
* `Using DynamoDB with the AWS SDK for PHP <http://www.youtube.com/watch?v=h_u3Ig5Cpv0>`_ (AWS re:Invent 2012)
