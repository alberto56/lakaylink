(function (Drupal, once) {

  Drupal.behaviors.copyCode = {
    attach(context) {

      once('copy-code', '.copy-code', context).forEach((button) => {

        button.addEventListener('click', async () => {
          // copy token
          const text = document.getElementById(button.dataset.target).textContent;
          // copy token to clipboard
          await navigator.clipboard.writeText(text);

          // Show the "Send this code" message.
          const sendMessage = button.closest('.card').querySelector('.send-this-code');
          if (sendMessage) {
            sendMessage.classList.remove('hidden');
          }

          button.innerHTML = '<i class="bi bi-clipboard-check text-success"></i>';

          setTimeout(() => {
            button.innerHTML = '<i class="bi bi-clipboard copy-code"></i>';
          }, 1500);

        });
      });
    }
  };

})(Drupal, once);
