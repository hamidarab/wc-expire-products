jQuery(function($) {
  function hideAllExpirationDivs() {
    $('.expiration-date[data-variation-id]').prop('hidden', true).hide();
  }

  function showExpirationForVariation(variationId) {
    hideAllExpirationDivs();
    const $expirationDiv = $('.expiration-date[data-variation-id="' + variationId + '"]');
    if ($expirationDiv.length) {
      $expirationDiv.prop('hidden', false).show();
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
