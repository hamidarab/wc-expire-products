jQuery(function($) {
    $('.editinline').on('click', function() {
        var $row = $(this).closest('tr');
        var post_id = $row.attr('id').replace('post-', '');
        var raw_date = $row.find('.expiration-hidden').data('date');

        setTimeout(function() {
            var $input = $('#edit-' + post_id).find('input[name="expiration_date"]');
            if ($input.length && raw_date) {
                $input.val(raw_date);
            } else {
                console.warn('⚠️ Could not find input or date missing.');
            }
        }, 100);
    });
});

