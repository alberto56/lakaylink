// // remove

// // js/drupal-commerce.js
// (function ($, Drupal) {
//     Drupal.behaviors.electroCommerce = {
//       attach: function (context, settings) {
//         // Initialize Electro's product slider (only once)
//         $('.product-slider', context).once('electroSlider').each(function() {
//           // Your slider initialization code
//         });
  
//         // Custom AJAX for add-to-cart side effects
//         $('.add-to-cart-button', context).once('electroCart').on('click', function() {
//           // Show modal or update cart count
//         });
//       }
//     };
//   })(jQuery, Drupal);