jQuery(document).ready(function($) {
    $('input[name^=\"redirect_manual\"]').on('input', function() {
        let row = $(this).closest('tr');
        if ($(this).val().trim() !== '') {
            row.find('select[name^=\"redirect_select\"]').prop('disabled', true);
        } else {
            row.find('select[name^=\"redirect_select\"]').prop('disabled', false);
        }
    });
});
