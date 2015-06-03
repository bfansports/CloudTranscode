#!/usr/bin/env bash

set -eu -o pipefail

envsubst \
    < config/cloudTranscodeConfigTemplate.json \
    > /etc/cloudTranscodeConfig.json

exec php "/usr/src/cloudtranscode/src/$1.php" \
    -c /etc/cloudTranscodeConfig.json \
    "${@:2}"
