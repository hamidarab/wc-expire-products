jQuery(function($) {
  $('.editinline').on('click', function() {
    var $row = $(this).closest('tr');
    var post_id = $row.attr('id').replace('post-', '');
    var raw_date = $row.find('.expiration-hidden').data('date');
    var product_type = $('#woocommerce_inline_' + post_id).find('.product_type').text().trim();

    setTimeout(function() {
      var $input = $('#edit-' + post_id).find('input[name="expiration_date"]');

      if (product_type === 'simple') {
        if ($input.length) {
          $input.closest('.inline-edit-col-right').show();
          if (raw_date) {
              $input.val(raw_date);
          }
        }
      } else {
        if ($input.length) {
          $input.closest('.inline-edit-col-right').hide();
        }
      }
    }, 100);
  });
});