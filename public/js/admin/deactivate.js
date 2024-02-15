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
        }
    });
    
    $('#the-list').on('click', '#deactivate-pagbank-connect', function(e) {
        e.preventDefault();
        var deactivateUrl = $(this).attr('href');
        feedbackModal.dialog('open');
    });
    
});