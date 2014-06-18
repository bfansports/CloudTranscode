#!/usr/bin/env bash

set -x

export HOME=/home/ubuntu/
export CT_HOME=$HOME/CloudTranscode
export CT_LOGS=$HOME/logs/
export CT_ROLES="decider inputPoller validateAsset transcodeAsset"
export CT_CONFIG_PATH=s3://cloud-transcode/config/cloudTranscodeConfig.json
export AWS_DEFAULT_REGION=us-east-1
export DEBUG="-d"

sudo -H -E -u ubuntu bash -c '$CT_HOME/deploy/ec2/start.sh'
