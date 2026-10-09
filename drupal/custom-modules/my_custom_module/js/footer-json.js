(function (Drupal, drupalSettings, once) {
    'use strict';

    Drupal.behaviors.footerJson = {
      attach: function (context) {
        once('footer-json', 'body', context).forEach(function () {
          const config = drupalSettings.footerJson;

          if (!config || !config.url) {
            console.warn('Footer JSON URL is not configured.');
            return;
          }

          loadFooterJson(config.url);
        });
      }
    };

    async function loadFooterJson(url) {
      try {
        const response = await fetch(url, {
          method: 'GET',
          headers: {
            'Accept': 'application/json'
          },
          credentials: 'omit'
        });

        if (!response.ok) {
          throw new Error(
            `Footer JSON request failed: ${response.status}`
          );
        }

        const data = await response.json();

        renderFooterMenus(data);

      }
      catch (error) {

        console.error(
          'Unable to load footer JSON:',
          error
        );

      }

    }

    function renderFooterMenus(data) {
      if (!data || typeof data !== 'object') {
        return;
      }

      const language = getCurrentLanguage();

      const menuNames = [
        'customer_service',
        'information',
        'extras'
      ];

      menuNames.forEach(function (menuName) {
        const items = data[menuName];

        if (!Array.isArray(items)) {
          return;
        }

        renderMenu(menuName, items, language);
      });
    }

    function getCurrentLanguage() {
      const htmlLanguage = document.documentElement.lang || 'en';

      return htmlLanguage.toLowerCase().split('-')[0];
    }

    function renderMenu(menuName, items, language) {
      const menu = document.querySelector(
        '[data-footer-menu="' + menuName + '"]'
      );

      if (!menu) {
        console.warn('Footer menu not found:', menuName);
        return;
      }

      /*
       * Find the existing Drupal footer block.
       */
      const footerItem = menu.querySelector('.footer-item');

      if (!footerItem) {
        console.warn('Footer item container not found:', menuName);
        return;
      }

      /*
       * Preserve the existing heading.
       */
      const heading = footerItem.querySelector('h4');

      items.forEach(function (item) {
        if (!item || !item.path || !item.title) {
          return;
        }

        const path = getLocalizedValue(item.path, language);
        const title = getLocalizedValue(item.title, language);

        if (!path || !title) {
          return;
        }

        const link = document.createElement('a');
        const icon = document.createElement('i');

        link.href = path;
        link.className = 'footer-json-link';

        icon.className = 'fas fa-angle-right me-2';
        icon.setAttribute('aria-hidden', 'true');

        link.appendChild(icon);
        link.appendChild(document.createTextNode(title));

        /*
        * Append new links after all existing menu links.
        */
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
