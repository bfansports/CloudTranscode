---
layout: page
title: "S3 Entitlements"
category: deep
date: 2015-05-05 20:08:28
order: 10
---

If your clients are using a different AWS account than the CT Stack, then the Clients need to entitle to stack to their S3 buckets.

<b>Note:</b> If you run the clients with the same credentials as the S3 buckets owner, then you don't need entitlements. The owner can by default access the buckets.

### S3

Clients must grant the Stack S3 access:

   - READ access to a S3 bucket as input, so the stack can download S3 files.
   - WRITE access to an S3 bucket as output, so resulting transcoded files can be upload to it.

To entitle the stack on its buckets, Clients must setup a "Bucket Policy" on each bucket. 

#### Bucket policy

To setup the policy your client needs, the ARN information of the user OR role running the Stack.<br>
The stack Admin must provide that information to the Clients, and the Clients must entitle this ARN.

Below, we show how a client "ClientA" entitle the CloudTranscode role "stack_prod" to access two buckets named:

   - ClientA_bucket_in
   - ClientA_bucket_out


##### Input bucket policy

This is were the stack will download from. The stack needs READ rights or "GetObject" permission using AWS terminology.

<br>
<img src="../images/bucket1.png" />
<br>

This policy entitle role "stack_prod" from AWS account 000111333444555 to READ from our bucket.

    {

            "Version": "2008-10-17",
            "Id": "Policy1400343104177",
            "Statement": [
                    {
                            "Sid": "Stmt1400342869898",
                            "Effect": "Allow",
                            "Principal": {
                                    "AWS": "arn:aws:iam::000111333444555:role/stack_prod"
                            },
                            "Action": "s3:GetObject",
                            "Resource": "arn:aws:s3:::ClientA_bucket_in/*"
                    }
            ]
    }

##### Output bucket policy

This is were the stack will upload the resulting files to. The stack needs WRITE rights or "PutObject" permission using AWS terminology.

There we entitle role "stack_prod" of AWS account 000111333444555 to WRITE to our bucket

    {
            "Version": "2008-10-17",
            "Id": "Policy1400343104177",
            "Statement": [
                    {
                            "Sid": "Stmt1400342869898",
                            "Effect": "Allow",
                            "Principal": {
                                    "AWS": "arn:aws:iam::000111333444555:user/stack_prod"
                            },
                            "Action": "s3:PutObject",
                            "Resource": "arn:aws:s3:::ClientA_bucket_out/*"
                    }
            ]
    }

#### ARN

ARNs are URI provided by Amazon to identify resources. Each resource, has an ARN. It could be instances, workflows, S3 buckets, users, etc.

Below is the ARN of the user running the CT stack. This user needs to be entitled on the Client account to READ and WRITE on S3. 

    arn:aws:iam::000111333444555:user/stack_prod

It could also be an IAM role (recommended for Ec2 workers).

    arn:aws:iam::000111333444555:role/stack_prod_role

#### IAM Roles

AWS Ec2 instances can automatically assume a role at boot-up. A role describe what the machine can do on AWS. It grants temporary rights via a token. No AWS Keys to install on the instance, no security and keys rotation issues. The role is assumed automatically by Ec2, you don't have to manually request this role. Any calls to AWS services will use this role. 

This is very handy for auto-scaling. You should use roles in production.

