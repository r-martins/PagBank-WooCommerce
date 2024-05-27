jQuery(document).ready(function($) {
    
    //dismiss pix notice
    $(document).on('click', '.pagbank-pix-notice .notice-dismiss', function() {
        $.post(script_data.ajaxurl, { action: script_data.action });
    });
});