#!/bin/bash

######
# This script runs before we deploy and start the stack programs on the instance
######


###
# Here we setup syslog with http://loggly.com
###

LOGGLY_SYSLOG_CONF=22-loggly.conf

# Download from S3 syslog file for loggly. 
# Run as root as we put file in /etc/rsyslog.d/
php $CT_INSTALL_PATH/src/scripts/getFromS3.php --bucket $CT_CONFIG_BUCKET --file config/$LOGGLY_SYSLOG_CONF --to /etc/rsyslog.d/$LOGGLY_SYSLOG_CONF
# Restart syslog service
/usr/sbin/service rsyslog restart
