# Country based price display.
---

If the IP is in the usa, then we have to show only usd. If the IP is in europe then we have to show Eur and then any place we see a price, both in including backend and frontend. we have to display in smaller, "you will be charged in US dollars with the USD amount". if the IP is in canada, then show cad in large and usd in small.

Installed Ip2Country and Commerce Exchanger module .

1. Ip2Country module looks up the country associated with a visitor's IPv4 address.

  configure ip2country at /admin/config/people/ip2country

  Note: When this module is initially enabled, it fills the IP-Country database. Initial downloads will take time, WAIT for it to complete to ensure your table is full. Automatic database updates require Drupal cron.

  we have to setup a cron to automatically update Database . Default period is 1 week.


2. Commerce Exchanger provides Currency exchange rates Plugin-based module for handling external providers.

  I have configured Exchange rates entities at /admin/commerce/config/exchange-rates

  create Exchange rates at /admin/commerce/config/exchange-rates/add .

  I have created currencylayer. For that we required api key.

  create account in https://currencylayer.com/signup/free/monthly . Once you login you will
  see Your API Key in https://currencylayer.com/dashboard .

  copy api key and update at .env file CURRENCY_LAYER_API_KEY .

  I have selected Commerce Exchanger rates scheduled to update everyday while creating exchange rate.

  Commerce Exchanger writes rates into commerce_exchanger_latest_rates database tables.


I have written custom code drupal/custom-modules/my_custom_module/src/Controller/IpCurrencyRateController.php

which Determines a visitor's country using their IP address, maps the country to a supported display currency, and retrieves the latest exchange rate from the Commerce Exchanger database table.

USD is the default store currency. CAD and EUR are supported display currencies; all other currencies fall back to USD.

The response is marked as non-cacheable because the result depends on  the visitor's IP address.


 ex 1:- Indian ip address we should get USD
   /api/get-ip-to-currency-rate?SIMULATE-IP=152.57.129.130
   {"rates":{},"date":null,"client-currency":"USD"}

 ex 2:- French ip address we should get USD
   /api/get-ip-to-currency-rate?SIMULATE-IP=176.31.84.249
   {
     "rates": {"usd-eur":0.89267},
     "date":"2026-10-25-18:55:29 UTC",
     "client-currency":"EUR"
   }


you can verify at /api/get-ip-to-currency-rate?SIMULATE-IP=176.31.84.249

drupal/custom-themes/my_custom_theme/js/country-based-price.js get the api /api/get-ip-to-currency-rate response and its finds the price $XXX in the page and replace the respective converted values.
