#!/usr/bin/env bash

set -x

LOGS=$HOME/logs/
mkdir -p $LOGS

exec > >(tee $LOGS/user-data.log|logger -t user-data ) 2>&1

if [ ! -e $CT_HOME ]; then
    echo "'$CT_HOME' doesn't exists! Abording"
    exit 2;
fi

# Get the configuration file from S3.
# Put your own config file in a private S3 bucket
aws s3 cp s3://cloud-transcode/config/cloudTranscodeConfig.json $CT_HOME/config/ --region us-east-1

if [ ! -z "$CT_ROLES" ]; then
    # Start PHP scripts based on roles
    echo "#### STARTING CLOUD TRANSCODE SCRIPTS ####"

    for role in $CT_ROLES; do
	if   [ "$role" == "decider" ]; then
	    echo "#-> STARTING DECIDER"
	    php $CT_HOME/src/Decider.php $DEBUG 2>&1 > $LOGS/Decider.log &
	elif [ "$role" == "inputPoller" ]; then
	    echo "#-> STARTING INPUT_POLLER"
	    php $CT_HOME/src/InputPoller.php $DEBUG 2>&1 > $LOGS/InputPoller.log &
	elif [ "$role" == "validateAsset" ]; then
	    echo "#-> STARTING VALIDATE_ASSET POLLER"
	    php $CT_HOME/src/ActivityPoller.php -a $CT_HOME/config/validateInputAndAsset-ActivityPoller.json $DEBUG 2>&1 > $LOGS/ValidateInputAndAsset.log &
	elif [ "$role" == "transcodeAsset" ]; then
	    echo "#-> STARTING TRANSCODE_ASSET POLLER"
	    php $CT_HOME/src/ActivityPoller.php -a $CT_HOME/config/transcodeAsset-ActivityPoller.json $DEBUG 2>&1 > $LOGS/TranscodeAsset.log &
	else
	    echo "Invalid role '$role'! Abording..."
	    exit 2;
	fi
    done
else
    echo "No $CT_ROLES provided!"
fi

