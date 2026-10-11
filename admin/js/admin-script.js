jQuery(function($) {
  $(document).on('click', '.editinline', function() {
    const $row = $(this).closest('tr');
    const postId = String($row.attr('id') || '').replace('post-', '');
    const rawDate = $row.find('.expiration-hidden').attr('data-date') || '';
    const productType = $('#woocommerce_inline_' + postId).find('.product_type').text().trim();

    setTimeout(function() {
      const $input = $('#edit-' + postId).find('input[name="expiration_date"]');
      const $fieldset = $input.closest('.peep-quick-edit-fieldset');

      if (!$input.length) {
        return;
      }

      if (productType === 'simple' || productType === 'external') {
        $fieldset.prop('hidden', false).show();
        $input.val(rawDate);
      } else {
        $fieldset.prop('hidden', true).hide();
        $input.val('');
      }
    }, 100);
  });
});
