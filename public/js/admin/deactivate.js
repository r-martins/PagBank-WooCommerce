jQuery(document).ready(function($) {
    console.debug('PagBank: Deactivate script loaded');

    // var feedbackModal = $('<div id="pagbank-feedback-modal" title="Send feedback" style="display:none;"><p>Please enter your feedback:</p><textarea id="feedback-text" style="width:100%;"></textarea></div>').appendTo('body');
    var feedbackModal = $(pagbankConnect.feedbackModalHtml).appendTo('body');

    // Inicializa o modal
    feedbackModal.dialog({
        autoOpen: false,
        modal: true,
        buttons: {
            "Send": function() {
                // Aqui você pode adicionar o código para enviar o feedback
                console.log($('#feedback-text').val());
                $(this).dialog("close");
                // Quando o feedback for enviado, você pode redirecionar para a URL de desativação
                // window.location.href = deactivateUrl;
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        },
        open: function() {
            $(this).attr('style', '');
        }
    });
    
    $('#the-list').on('click', '#deactivate-pagbank-connect', function(e) {
        window.pagbank_deactivate_event = e;
        e.preventDefault();
        window.pagbank_deactivate_url = $(this).attr('href');
        feedbackModal.dialog('open');
    });
    
    $('.pagbank-feedback-footer .button-deactivate').on('click', function(e) {
       //serialize form and send an ajax request
         e.preventDefault();
        var selectedReason = jQuery('input[name="selected-reason"]:checked').val();
        if (!selectedReason) {
            window.location.href = window.pagbank_deactivate_url;
            return true;
        }
        var feedbackData = jQuery('#pagbank-feedback-form').serialize()
            $.ajax({
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
    
});