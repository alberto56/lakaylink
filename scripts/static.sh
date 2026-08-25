#!/bin/bash
#
# Static analysis.
#
set -e

echo 'Performing static analsys'
echo 'If you are getting a false negative, use:'
echo ''
echo '// @phpstan-ignore-next-line'
echo ''

# See https://github.com/dcycle/docker-phpstan-drupal.
docker run --rm \
  -v "$(pwd)"/drupal/custom-modules:/var/www/html/modules/custom \
  -v "$(pwd)"/drupal/custom-themes:/var/www/html/themes/custom \
  -v "$(pwd)"/scripts/lib/phpstan:/phpstan-drupal \
  dcycle/phpstan-drupal:4 \
  /var/www/html/modules/custom \
  /var/www/html/themes/custom \
  -c /phpstan-drupal/phpstan.neon \
  --memory-limit=512M
