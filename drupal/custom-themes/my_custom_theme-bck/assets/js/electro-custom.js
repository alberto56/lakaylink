// (function ($, Drupal) {
//     Drupal.behaviors.electroBootstrap = {
//       attach: function (context, settings) {
        
//         // Initialize WOW.js for animations
//         if (typeof WOW !== 'undefined') {
//           var wow = new WOW();
//           wow.init();
//         }
        
//         // Initialize Owl Carousel for Hero
//         if ($.fn.owlCarousel) {
//           $('.header-carousel', context).once('heroCarousel').each(function() {
//             $(this).owlCarousel({
//               items: 1,
//               loop: true,
//               autoplay: true,
//               autoplayTimeout: 5000,
//               nav: true,
//               dots: false,
//               navText: ['<i class="bi bi-arrow-left"></i>', '<i class="bi bi-arrow-right"></i>']
//             });
//           });
          
//           // Product list carousel
//           $('.productList-carousel', context).once('productCarousel').each(function() {
//             $(this).owlCarousel({
//               items: 3,
//               loop: true,
//               margin: 25,
//               nav: true,
//               dots: false,
//               responsive: {
//                 0: { items: 1 },
//                 768: { items: 2 },
//                 992: { items: 3 }
//               }
//             });
//           });
//         }
        
//         // Back to top button
//         $(window).scroll(function() {
//           if ($(this).scrollTop() > 300) {
//             $('.back-to-top').fadeIn('slow');
//           } else {
//             $('.back-to-top').fadeOut('slow');
//           }
//         });
        
//         $('.back-to-top').click(function(e) {
//           e.preventDefault();
//           $('html, body').animate({scrollTop: 0}, 500);
//         });
        
//         // Hide spinner on load
//         $('#spinner').addClass('show');
//         $(window).on('load', function() {
//           setTimeout(function() {
//             $('#spinner').removeClass('show');
//           }, 500);
//         });
        
//       }
//     };
//   })(jQuery, Drupal);