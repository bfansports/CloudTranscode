#!/usr/bin/env bash

export PATH=$PATH:$HOME/bin/

# Get user data
USER_DATA=`curl http://169.254.169.254/latest/user-data`
if [ -z $USER_DATA ]; then
    echo "No userdata provided!"
    exit 2;
fi
# Eval userdata
eval $USER_DATA

# Verbose bash execution
set -x
# redirect this output to log file
exec > >(tee $CT_LOGS/user-data.log|logger -t user-data ) 2>&1

# Is CT_HOME defined?
if [ ! -e $CT_HOME ]; then
    echo "'$CT_HOME' doesn't exists! Abording"
    exit 2;
fi
# Refresh CloudTranscode code and make
cd $CT_HOME && git pull -f && make
mkdir -p $CT_LOGS

# Get the configuration file from S3.
# Put your own config file in a private S3 bucket
aws s3 cp $CT_CONFIG_PATH $CT_HOME/config/ --region $AWS_DEFAULT_REGION

# Check CT_ROLES and start appropriate scripts
if [ ! -z "$CT_ROLES" ]; then
    echo "#### STARTING CLOUD TRANSCODE SCRIPTS ####"
    for role in $CT_ROLES; do
	if   [ "$role" == "decider" ]; then
	    echo "#-> STARTING DECIDER"
	    php $CT_HOME/src/Decider.php $DEBUG 2>&1 > $CT_LOGS/Decider.log &
	elif [ "$role" == "inputPoller" ]; then
	    echo "#-> STARTING INPUT_POLLER"
	    php $CT_HOME/src/InputPoller.php $DEBUG 2>&1 > $CT_LOGS/InputPoller.log &
	elif [ "$role" == "validateAsset" ]; then
	    echo "#-> STARTING VALIDATE_ASSET POLLER"
	    php $CT_HOME/src/ActivityPoller.php -a $CT_HOME/config/validateInputAndAsset-ActivityPoller.json $DEBUG 2>&1 > $CT_LOGS/ValidateInputAndAsset.log &
	elif [ "$role" == "transcodeAsset" ]; then
	    echo "#-> STARTING TRANSCODE_ASSET POLLER"
	    php $CT_HOME/src/ActivityPoller.php -a $CT_HOME/config/transcodeAsset-ActivityPoller.json $DEBUG 2>&1 > $CT_LOGS/TranscodeAsset.log &
	else
	    echo "Invalid role '$role'! Abording..."
	    exit 2;
	fi
    done
else
    echo "No $CT_ROLES provided!"
fi

