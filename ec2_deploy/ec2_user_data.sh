#!/bin/bash

######
# This script can be passed on as user data to an EC2 instance in order to install CloudTranscode
# It clone the project and then run the deploy script
######

export CONFIG_MODE=DEV
# You need to declare the AWS region you want to use for you SWF and SQS service.
export AWS_REGION=us-east-1

# Git repo for CloudTranscode. You should fork the project and point to your fork
export CT_GITHUB_REPO=https://github.com/sportarchive/CloudTranscode.git
# This list the roles this instance will have. This order the deploy script to start the proper scripts at bootup
export CT_INSTANCE_ROLES=DECIDER,INPUT_POLLER,VALIDATE_ASSET,TRANSCODE_ASSET
export CT_INSTALL_PATH=/home/ubuntu/CloudTranscode
# Linux user on the Ec2 instance that will run the programs
export CT_LINUX_USER=ubuntu
# Bucket and file on S3 where we can get your "cloudTranscodeConfig.json" and install it in the config folder
# !Customize this with using YOUR bucket!
export CT_CONFIG_BUCKET=cloud-transcode
export CT_CONFIG_FILE=config/cloudTranscodeConfig.json

# Custom script to be executed prior to stack deploy. if needed
export CT_PREDEPLOY_SCRIPT=$CT_INSTALL_PATH/ec2_deploy/pre-deploy.sh
# Custom script to be executed after stack deploy and startup. if needed
#export CT_POSTDEPLOY_SCRIPT=$CT_INSTALL_PATH/ec2_deploy/post-deploy.sh



## GIT CLONE ##
# Clone project from github and run the deploy script
sudo -u $CT_LINUX_USER -E bash -c "git clone $CT_GITHUB_REPO $CT_INSTALL_PATH"



## PRE DEPLOY ##
# Call a custom script pre-deployment (CT_PREDEPLOY_SCRIPT). If any.
if [ ! -z $CT_PREDEPLOY_SCRIPT ];
then
    sudo -u $CT_LINUX_USER -E bash -c "CT_PREDEPLOY_SCRIPT"
fi

## DEPLOY ##
# Deploy and start the PHP programs based on this instance role (CT_INSTANCE_ROLES)
sudo -u $CT_LINUX_USER -E bash -c "$CT_INSTALL_PATH/ec2_deploy/deploy_ct.sh"

## POST DEPLOY ##
# Call a custom script post deployment (CT_POSTDEPLOY_SCRIPT). If any.
if [ ! -z $CT_POSTDEPLOY_SCRIPT ];
then
    sudo -u $CT_LINUX_USER -E bash -c "CT_POSTDEPLOY_SCRIPT"
fi
