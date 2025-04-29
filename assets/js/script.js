jQuery(function($) {
  const expiryData = window.product_expiration_data || {};

  function hideAllExpirationDivs() {
    $('.expiration-date[data-variation-id]').hide();
  }

  function showExpirationForVariation(variationId) {
    hideAllExpirationDivs();
    const $expirationDiv = $('.expiration-date[data-variation-id="' + variationId + '"]');
    if ($expirationDiv.length) {
      $expirationDiv.show();
    }
  }

  $(document).on('found_variation', '.variations_form', function(event, variation) {
    if (variation && variation.variation_id) {
      showExpirationForVariation(variation.variation_id);
    } else {
      hideAllExpirationDivs();
    }
  });

  $(document).on('reset_data', '.variations_form', function() {
    hideAllExpirationDivs();
  });
});