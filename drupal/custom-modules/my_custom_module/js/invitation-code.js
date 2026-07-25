(function (Drupal, once) {
  Drupal.behaviors.copyCode = {
    attach: function (context) {
      once('copy-code', '.copy-code', context).forEach(function (button) {
        button.addEventListener('click', function () {
          var text = document.getElementById(button.dataset.target).textContent;

          // Clipboard API (still async, but handled with Promise)
          navigator.clipboard.writeText(text).then(function () {

            var sendMessage = button.closest('.card').querySelector('.send-this-code');
            if (sendMessage) {
              sendMessage.classList.remove('hidden');
            }

            button.innerHTML = '<i class="bi bi-clipboard-check text-success"></i>';

            setTimeout(function () {
              button.innerHTML = '<i class="bi bi-clipboard copy-code"></i>';
            }, 1500);

          }).catch(function (err) {
            console.error('Copy failed:', err);
          });
        });
      });
    }
  };

})(Drupal, once);
