#!/usr/bin/env bash

set -eu -o pipefail

exec php "/usr/src/cloudtranscode/src/activities/$1.php" \
     "${@:2}"
