jQuery(document).ready(function ($) {
    let changed = false;

    // Enable drag-and-drop sorting
    $('#pp-sortable').sortable({
        handle: '.pp-handle',
        placeholder: 'pp-placeholder',
        update: function () {
            changed = true;
            $('#pp-save-status').text('Unsaved changes...');
        }
    });

    // Save order button click
    $('#pp-save-order').on('click', function () {
        const $btn = $(this);
        const order = [];

        $('#pp-sortable .pp-item').each(function () {
            order.push($(this).data('id'));
        });

        console.log('Saving order...', order); // Debugging output

        $btn.prop('disabled', true).text('Saving...');
        $('#pp-save-status').text('');

        $.ajax({
            url: pp_vars.ajax_url, //  from wp_localize_script
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'pp_save_order',
                order: order,
                nonce: pp_vars.nonce //  same nonce name
            },
            success: function (res) {
                console.log('Response:', res); // Debugging
                if (res.success) {
                    changed = false;
                    $('#pp-save-status').text(res.data.message).fadeOut(3000, function () {
                        $(this).text('').show();
                    });
                } else {
                    $('#pp-save-status').text('Error: ' + res.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                $('#pp-save-status').text('AJAX error: ' + error);
            },
            complete: function () {
                $btn.prop('disabled', false).text('Save order');
            }
        });
    });

    // Warn if leaving with unsaved changes
    window.addEventListener('beforeunload', function (e) {
        if (changed) {
            const msg = 'You have unsaved changes. Are you sure you want to leave?';
            (e || window.event).returnValue = msg;
            return msg;
        }
    });
});
