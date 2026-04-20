<?php

namespace Drupal\my_custom_module\Drush\Commands;


use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

class ImportProductsCommand extends DrushCommands {

  #[CLI\Command(name: 'my-custom-module:import')]
  #[CLI\Argument(name: 'store_id', description: 'Store ID')]
  public function import($store_id) {

    // Ensure module file is loaded (important!)
    \Drupal::moduleHandler()->loadInclude('my_custom_module', 'module');

    // Direct function call
    my_custom_module_import($store_id);

    $this->output()->writeln("Done: $store_id");
  }


  #[CLI\Command(name: 'my-custom-module:generate-grocery')]
  public function generateGrocery() {
    $file = fopen('grocery_50k.csv', 'w');

    $headers = [
      'product_id','product_name','category','sub_category','product_type',
      'pack_type','brand','variation_sku','variant_name',
      'quantity','unit','price','currency',
      'stock','weight','unit_type',
      'origin','expiry_days','storage_type',
      'image_url','status'
    ];

    fputcsv($file, $headers);

    $categories = [
      'Fruits' => ['Apple','Banana','Orange','Mango','Grapes'],
      'Vegetables' => ['Tomato','Onion','Potato','Carrot','Spinach'],
      'Staples' => ['Rice','Wheat','Dal','Sugar','Salt'],
      'Dairy' => ['Milk','Butter','Cheese','Yogurt'],
      'Snacks' => ['Biscuits','Chips','Namkeen','Cookies'],
      'Spices' => ['Turmeric','Chili','Cumin','Pepper']
    ];

    $brands = ['Local Farm','Amul','Nestle','Tata','Organic India'];

    $units = ['kg','g','ml','pcs'];

    for ($i = 1; $i <= 50000; $i++) {

      $category = array_rand($categories);
      $item = $categories[$category][array_rand($categories[$category])];

      $is_packaged = (rand(0,1) ? 'packed' : 'unpacked');

      $unit = ($category == 'Dairy' || $category == 'Staples') ? 'kg' : 'pcs';

      $quantity = ($unit == 'kg') ? rand(1,5) : rand(1,12);

      $sku = strtoupper(substr($item,0,3)) . "-$i";

      fputcsv($file, [
        "P$i",
        $item,
        $category,
        $category,
        "grocery",
        $is_packaged,
        $brands[array_rand($brands)],
        $sku,
        "$item $quantity$unit",
        $quantity,
        $unit,
        rand(10,500),
        "INR",
        rand(50,1000),
        $quantity,
        $unit,
        "India",
        rand(3,365),
        (rand(0,1) ? 'dry' : 'cold'),
        "https://via.placeholder.com/300",
        rand(0,1)
      ]);
    }

    fclose($file);

    echo "Grocery 50K dataset generated\n";
  }
}