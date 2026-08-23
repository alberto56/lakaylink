#!/bin/bash
#
# Run accessitility tests.
#
set -e

if [ ! -f ./do-not-commit/dom-captures/custom-login-page.html ]; then
  >&2 echo 'Please run ./scripts/end-to-end-tests.sh first to get DOM captures'
  >&2 echo 'of internal pages.'
  exit 1
fi

docker run --rm --network lakaylink_default dcycle/pa11y:2 http://webserver -T 8
docker run --rm --network lakaylink_default dcycle/pa11y:2 http://webserver/dom-captures/custom-login-page.html -T 12
echo 'If this script passes, that means the number of errors is below the allowed threshold.'
