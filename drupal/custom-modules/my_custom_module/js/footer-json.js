(function (Drupal, drupalSettings, once) {
    'use strict';

    Drupal.behaviors.footerJson = {
      attach: function (context) {
        once('footer-json', 'body', context).forEach(function () {
          var config = drupalSettings.footerJson;

          if (!config || !config.url) {
            console.warn('Footer JSON URL is not configured.');
            return;
          }

          loadFooterJson(config.url);
        });
      }
    };

    function loadFooterJson(url) {
      fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json'
        },
        credentials: 'omit'
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error(
              'Footer JSON request failed: ' + response.status
            );
          }

          return response.json();
        })
        .then(function (data) {
          renderFooterMenus(data);
        })
        .catch(function (error) {
          console.error('Unable to load footer JSON:', error);
        });
    }

    function renderFooterMenus(data) {
      if (!data || typeof data !== 'object' || Array.isArray(data)) {
        return;
      }

      var language = getCurrentLanguage();

      var menuNames = [
        'customer_service',
        'information',
        'extras'
      ];

      menuNames.forEach(function (menuName) {
        var items = data[menuName];

        if (!Array.isArray(items)) {
          return;
        }

        renderMenu(menuName, items, language);
      });
    }

    function getCurrentLanguage() {
      var htmlLanguage = document.documentElement.lang || 'en';

      return htmlLanguage.toLowerCase().split('-')[0];
    }

    function renderMenu(menuName, items, language) {
      var menu = document.querySelector(
        '[data-footer-menu="' + menuName + '"]'
      );

      if (!menu) {
        console.warn('Footer menu not found:', menuName);
        return;
      }

      /*
       * Find the existing Drupal footer block.
       */
      var footerItem = menu.querySelector('.footer-item');

      if (!footerItem) {
        console.warn('Footer item container not found:', menuName);
        return;
      }

      /*
       * Preserve the existing heading.
       */
      var heading = footerItem.querySelector('h4');

      /*
       * Append links after the existing footer content.
       */
      items.forEach(function (item) {
        if (!item || !item.path || !item.title) {
          return;
        }

        var path = getLocalizedValue(item.path, language);
        var title = getLocalizedValue(item.title, language);

        if (!path || !title) {
          return;
        }

        var link = document.createElement('a');
        var icon = document.createElement('i');

        link.href = path;
        link.className = 'footer-json-link';

        icon.className = 'fas fa-angle-right me-2';
        icon.setAttribute('aria-hidden', 'true');

        link.appendChild(icon);
        link.appendChild(document.createTextNode(title));

        footerItem.appendChild(link);
      });
    }

    function getLocalizedValue(values, language) {
      if (typeof values === 'string') {
        return values;
      }

      if (!values || typeof values !== 'object') {
        return '';
      }

      return values[language] || values.en || '';
    }

  })(Drupal, drupalSettings, once);
