#!/bin/bash

export CONFIG_MODE=DEV
export CT_INSTANCE_TYPE=DECIDER

cd /home/ubuntu
sudo -u ubuntu -H sh -c "git clone https://github.com/sportarchive/CloudTranscode.git"
cd /home/ubuntu/CloudTranscode
sudo -u ubuntu -H sh -c "make"
cd /home/ubuntu/CloudTranscode/src
sudo -u ubuntu -H sh -c "/usr/bin/php Decider.php -d > /tmp/Decider.out 2>&1 &"
