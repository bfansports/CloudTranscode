#!/bin/bash

# This script prepare the stack and start the proper elements (Decider, InputPoller or ActivityPoller) 
# based on the "$CT_INSTANCE_ROLES" variable
# Use $CT_INSTALL_PATH to locate the stack installation path

# Important: Make sure your instance got started with the proper "user_data" to load the proper environment.
# See: ec2_user_data.sh script.

# Based on CONFIG_MODE we activate debug or not
DEBUG=""
if [ "$CONFIG_MODE" == "DEV" ]; 
then
    DEBUG=" -d "
fi

if [ -z $CT_INSTALL_PATH ] || [ -z $CT_LINUX_USER ] || [ -z $CT_INSTANCE_ROLES ];
then
    echo "[ERROR] Missing \$CT_INSTALL_PATH, \$CT_LINUX_USER or \$CT_INSTANCE_ROLES variables!"
    exit 2
fi

cd $CT_INSTALL_PATH
# Install composer and download dependencies
sudo -u $CT_LINUX_USER -E bash -c "make"

# If S3 bucket specified for config file, we pull it and put it in 'config' folder
if [ ! -z $CT_CONFIG_BUCKET ] && [ ! -z $CT_CONFIG_FILE ];
then
    # Download configuration file
    sudo -u $CT_LINUX_USER -E bash -c "php $CT_INSTALL_PATH/src/scripts/getFromS3.php --bucket $CT_CONFIG_BUCKET --file $CT_CONFIG_FILE --to ${CT_INSTALL_PATH}/config/cloudTranscodeConfig.json"
fi

# Check roles in $CT_INSTANCE_ROLES and start proper programs
IFS=',' read -ra ROLES <<< "$CT_INSTANCE_ROLES"
for i in "${ROLES[@]}"; do
    # Check for wrong role
    if [ $i != "DECIDER" ] && [ $i != "INPUT_POLLER" ] && [ $i != "VALIDATE_ASSET" ] && [ $i != "TRANSCODE_ASSET" ];
    then
	echo "[ERROR] One of the role provided is invalid '$i'! Valid roles are: DECIDER,INPUT_POLLER,VALIDATE_ASSET,TRANSCODE_ASSET"
	exit 2;
    fi

    # Start program for role
    case "$i" in 
	'DECIDER')
	    cmd="php $CT_INSTALL_PATH/src/Decider.php $DEBUG &"
	    echo "[INFO] Starting: $cmd"
	    ;;
	'INPUT_POLLER')
	    cmd="php $CT_INSTALL_PATH/src/InputPoller.php $DEBUG &"
	    echo "[INFO] Starting: $cmd"
	    ;;
	'VALIDATE_ASSET')
	    cmd="php $CT_INSTALL_PATH/src/ActivityPoller.php -a config/validateInputAndAsset-ActivityPoller.json $DEBUG &"
	    echo "[INFO] Starting: $cmd"
	    ;;
	'TRANSCODE_ASSET')
	    cmd="php $CT_INSTALL_PATH/src/ActivityPoller.php -a config/transcodeAsset-ActivityPoller.json $DEBUG &"
	    echo "[INFO] Starting: $cmd"
	    ;;
	*)
	    echo "[ERROR] Unknown Role '$i' for this instance!"
	    exit 2
    esac

    # Exec role
    sudo -u $CT_LINUX_USER -E bash -c "$cmd"
    
done


