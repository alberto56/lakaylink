/**
 * @file
 * Electro Commerce Theme JavaScript
 * Drupal 11 Commerce Bootstrap 5 Subtheme
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Back-to-top button behavior.
   */
  Drupal.behaviors.electroBackToTop = {
    attach: function (context, settings) {
      once('back-to-top', '.back-to-top', context).forEach(function (btn) {
        var $btn = $(btn);

        $(window).on('scroll.backToTop', function () {
          if ($(window).scrollTop() > 300) {
            $btn.addClass('show');
          } else {
            $btn.removeClass('show');
          }
        });

        $btn.on('click', function (e) {
          e.preventDefault();
          $('html, body').animate({ scrollTop: 0 }, 600);
        });
      });
    }
  };

  /**
   * Hero Slider using Bootstrap Carousel.
   */
  Drupal.behaviors.electroHeroSlider = {
    attach: function (context, settings) {
      once('hero-carousel', '#heroCarousel', context).forEach(function (el) {
        var carousel = new bootstrap.Carousel(el, {
          interval: 5000,
          ride: 'carousel',
          wrap: true
        });
      });
    }
  };

  /**
   * Product List Slick/Carousel initialization.
   * Falls back to Bootstrap carousel if slick not loaded.
   */
  Drupal.behaviors.electroProductList = {
    attach: function (context, settings) {
      once('product-list-carousel', '.product-list-carousel', context).forEach(function (el) {
        if (typeof $.fn.slick !== 'undefined') {
          $(el).slick({
            slidesToShow: 3,
            slidesToScroll: 1,
            autoplay: true,
            autoplaySpeed: 4000,
            arrows: true,
            prevArrow: '<button class="carousel-nav-btn slick-prev" aria-label="' + Drupal.t('Previous') + '"><i class="fas fa-chevron-left"></i></button>',
            nextArrow: '<button class="carousel-nav-btn slick-next" aria-label="' + Drupal.t('Next') + '"><i class="fas fa-chevron-right"></i></button>',
            responsive: [
              {
                breakpoint: 1200,
                settings: { slidesToShow: 2 }
              },
              {
                breakpoint: 768,
                settings: { slidesToShow: 1 }
              }
            ]
          });
        }
      });
    }
  };

  /**
   * Sticky navbar on scroll.
   */
  Drupal.behaviors.electroStickyNav = {
    attach: function (context, settings) {
      once('sticky-nav', '#navbar', context).forEach(function (el) {
        var $nav = $(el);
        var navOffset = $nav.offset().top;

        $(window).on('scroll.stickyNav', function () {
          if ($(window).scrollTop() >= navOffset) {
            $nav.addClass('sticky-top shadow');
          } else {
            $nav.removeClass('sticky-top shadow');
          }
        });
      });
    }
  };

  /**
   * Product card hover: show add-to-cart area.
   */
  Drupal.behaviors.electroProductHover = {
    attach: function (context, settings) {
      once('product-hover', '.product-item', context).forEach(function (item) {
        // CSS handles this via :hover, JS only needed for touch devices.
        item.addEventListener('touchstart', function () {
          this.classList.toggle('is-hovered');
        });
      });
    }
  };

  /**
   * Page load spinner removal.
   */
  Drupal.behaviors.electroSpinner = {
    attach: function (context, settings) {
      once('spinner', '#page-spinner', context).forEach(function (spinner) {
        setTimeout(function () {
          spinner.style.opacity = '0';
          setTimeout(function () {
            spinner.style.display = 'none';
          }, 300);
        }, 400);
      });
    }
  };

  /**
   * Animate elements on scroll (simple implementation, no WOW.js dependency).
   */
  Drupal.behaviors.electroScrollAnimate = {
    attach: function (context, settings) {
      once('scroll-animate', '[data-animate]', context).forEach(function (el) {
        var observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              var animClass = entry.target.dataset.animate || 'fadeInUp';
              entry.target.classList.add('animate__animated', 'animate__' + animClass);
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.15 });

        observer.observe(el);
      });
    }
  };

})(jQuery, Drupal, once);
