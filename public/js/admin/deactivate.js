jQuery(document).ready(function($) {
    console.debug('PagBank: Deactivate script loaded');

    // var feedbackModal = jQuery('<div id="pagbank-feedback-modal" title="Send feedback" style="display:none;"><p>Please enter your feedback:</p><textarea id="feedback-text" style="width:100%;"></textarea></div>').appendTo('body');
    var feedbackModal = jQuery(pagbankConnect.feedbackModalHtml).appendTo('body');

    // Inicializa o modal
    feedbackModal.dialog({
        autoOpen: false,
        modal: true,
        buttons: {
            "Send": function() {
                // Aqui você pode adicionar o código para enviar o feedback
                console.log(jQuery('#feedback-text').val());
                jQuery(this).dialog("close");
                // Quando o feedback for enviado, você pode redirecionar para a URL de desativação
                // window.location.href = deactivateUrl;
            },
            "Cancel": function() {
                jQuery(this).dialog("close");
            }
        },
        open: function() {
            jQuery(this).attr('style', '');
        }
    });
    
    jQuery('#the-list').on('click', '#deactivate-pagbank-connect', function(e) {
        window.pagbank_deactivate_event = e;
        e.preventDefault();
        window.pagbank_deactivate_url = jQuery(this).attr('href');
        feedbackModal.dialog('open');
    });
    
    jQuery('.pagbank-feedback-footer .button-deactivate').on('click', function(e) {
       //serialize form and send an ajax request
         e.preventDefault();
        var selectedReason = jQuery('input[name="selected-reason"]:checked').val();
        var comment = jQuery('#pagbank-feedback-form textarea[name="comment"]').val();
        if (!selectedReason && !comment) {
            window.location.href = window.pagbank_deactivate_url;
            return true;
        }
        var feedbackData = jQuery('#pagbank-feedback-form').serialize()
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'ps_deactivate_feedback',
                    feedback: feedbackData,
                    nonce: pagbankFeedbackFormNonce
                },
                success: function(response) {
                    console.log(response);
                }
            })
            .always(function() {
                feedbackModal.dialog('close');
                window.location.href = window.pagbank_deactivate_url;
            })   ;
         
    });

    jQuery('.pagbank-feedback-footer .button-close').on('click', function(e) {
        feedbackModal.dialog('close');
    });
    
});