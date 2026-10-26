(function ($, Drupal, once) {
    'use strict';

    Drupal.behaviors.currencyConverter = {
      attach: function (context) {

        // Make the API request only once per page.
        if (!once('currency-api-request', 'html').length) {
          return;
        }

        $.ajax({
          url: '/api/get-ip-to-currency-rate',
          method: 'GET',
          dataType: 'json',
          cache: false
        }).done(function (data) {
          const currency = String(
            data['client-currency'] || 'USD'
          ).toUpperCase();

          const rates = data.rates || {};

          // USD visitors see the original prices.
          if (currency === 'USD') {
            return;
          }

          // Only support the currencies we want to display.
          if (!['EUR', 'CAD'].includes(currency)) {
            return;
          }

          const rateKey = 'usd-' + currency.toLowerCase();
          const rate = Number(rates[rateKey]);

          // Do not convert without a valid exchange rate.
          if (!Number.isFinite(rate) || rate <= 0) {
            console.warn('Missing exchange rate:', rateKey);
            return;
          }

          const formatCurrency = function (amount, code) {
            return new Intl.NumberFormat(
              code === 'EUR' ? 'de-DE' : 'en-CA',
              {
                style: 'currency',
                currency: code,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              }
            ).format(amount);
          };

          // Match existing standalone USD price text.
          const priceRegex =
            /^\s*\$\s*([\d,]+(?:\.\d{1,2})?)\s*$/;

          // Find existing text nodes without changing Twig templates.
          const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            {
              acceptNode: function (node) {
                const parent = node.parentElement;

                if (!parent) {
                  return NodeFilter.FILTER_REJECT;
                }

                // Skip scripts, styles, form fields and our own notes.
                if (parent.closest(
                  'script, style, noscript, textarea, input, select, ' +
                  '[contenteditable="true"], .currency-usd-note'
                )) {
                  return NodeFilter.FILTER_REJECT;
                }

                // Process only text that consists of a USD price.
                return priceRegex.test(node.nodeValue)
                  ? NodeFilter.FILTER_ACCEPT
                  : NodeFilter.FILTER_REJECT;
              }
            }
          );

          const priceNodes = [];
          while (walker.nextNode()) {
            priceNodes.push(walker.currentNode);
          }

          priceNodes.forEach(function (node) {
            const match = node.nodeValue.match(priceRegex);

            if (!match || !node.parentNode) {
              return;
            }

            const usdAmount = Number(
              match[1].replace(/,/g, '')
            );

            if (!Number.isFinite(usdAmount)) {
              return;
            }

            const convertedAmount = usdAmount * rate;
            const parent = node.parentNode;

            // Display the visitor's local currency.
            node.nodeValue = formatCurrency(
              convertedAmount,
              currency
            );

            // Show the original USD amount in smaller text.
            const note = document.createElement('small');
            note.className = 'currency-usd-note text-muted';

            if (currency === 'EUR') {
            note.textContent = Drupal.t(
                'You will be charged in US dollars: @amount',
                {
                '@amount': 'US$' + usdAmount.toFixed(2)
                }
            );
            } else if (currency === 'CAD') {
            note.textContent = Drupal.t(
                'US dollar amount: @amount',
                {
                '@amount': 'US$' + usdAmount.toFixed(2)
                }
            );
            }

            note.style.display = 'block';
            parent.insertBefore(note, node.nextSibling);

          });

        }).fail(function () {
          // If the API fails, leave the original USD prices unchanged.
          console.warn('Currency API request failed.');
        });
      }
    };

  })(jQuery, Drupal, once);
