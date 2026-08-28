#!/bin/bash
#
# Run end-to-end tests and keep track of markup and screenshots.
#

set -e

USER=admin
PASS=$(./scripts/uuid.sh)

echo 'Updating password for admin so our testbot knows how to login'
docker compose exec -T drupal /bin/bash -c 'drush upwd $(drush uinf --uid=1 --field=name) '"$PASS"


PRODUCT_TITLE="Test Product"

docker compose exec -T drupal drush php:eval "
\$storage = \Drupal::entityTypeManager()->getStorage('commerce_product');

\$existing = \$storage->loadByProperties(['title' => '$PRODUCT_TITLE']);

if (!\$existing) {
  \$product = \Drupal\commerce_product\Entity\Product::create([
    'type' => 'default',
    'title' => '$PRODUCT_TITLE',
    'stores' => [1],
    'status' => 1,
  ]);
  \$product->save();

  \$variation = \Drupal\commerce_product\Entity\ProductVariation::create([
    'type' => 'default',
    'sku' => 'TEST-SKU-001',
    'title' => '$PRODUCT_TITLE Variation',
    'price' => [
      'number' => '19.99',
      'currency_code' => 'USD',
    ],
    'product_id' => \$product->id(),
    'status' => 1,
  ]);
  \$variation->save();

  \$product->addVariation(\$variation);
  \$product->save();

  print 'Product created';
}
else {
  print 'Product already exists';
}
"


docker compose exec -T drupal drush php:eval "

\$userStorage = \Drupal::entityTypeManager()->getStorage('user');

\$users = [
  [
    'name' => 'test_unverified',
    'mail' => 'test_unverified@example.com',
    'password' => '$PASS',
    'roles' => ['unverified'],
  ],
  [
    'name' => 'test_seller',
    'mail' => 'test_seller@example.com',
    'password' => '$PASS',
    'roles' => ['seller'],
    'field_allowed_stores' => [1],
  ],
  [
    'name' => 'test_buyer',
    'mail' => 'test_buyer@example.com',
    'password' => '$PASS',
    'roles' => ['buyer'],
    'field_allowed_stores' => [1],
  ],
];

foreach (\$users as \$userData) {

  \$existing = \$userStorage->loadByProperties([
    'name' => \$userData['name'],
  ]);

  if (\$existing) {
    print \$userData['name'] . ' already exists ' . PHP_EOL;
    continue;
  }

  \$user = \Drupal\user\Entity\User::create([
    'name' => \$userData['name'],
    'mail' => \$userData['mail'],
    'status' => 1,
  ]);

  \$user->setPassword(\$userData['password']);

  foreach (\$userData['roles'] as \$role) {
    \$user->addRole(\$role);
  }

  if (isset(\$userData['field_allowed_stores'])) {
    \$user->set(
      'field_allowed_stores',
      \$userData['field_allowed_stores']
    );
  }

  \$user->save();

  print \$userData['name'] . ' created' . PHP_EOL;
}
"

echo 'change passwords'
docker compose exec -T drupal /bin/bash -c 'drush upwd test_unverified '"$PASS"
docker compose exec -T drupal /bin/bash -c 'drush upwd test_seller '"$PASS"
docker compose exec -T drupal /bin/bash -c 'drush upwd test_buyer '"$PASS"


echo 'Running our tests'
docker run \
  -e DRUPALUSER=admin \
  -e DRUPALMAIL="user+1@localhost.localdomain" \
  -e DRUPALPASS="$PASS" \
  --rm -v "$(pwd)"/tests/browser-tests:/app/test \
  --network lakaylink_default \
  -v "$(pwd)"/do-not-commit/screenshots:/artifacts/screenshots \
  -v "$(pwd)"/do-not-commit/dom-captures:/artifacts/dom-captures \
  dcycle/browsertesting:4

BASE="$(pwd)"
echo "* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * "
echo " SEE YOUR SCREENSHOTS IN"
echo " $BASE/do-not-commit/screenshots/*"
echo " AND"
echo " $BASE/do-not-commit/dom-captures/*"
echo "* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * "
