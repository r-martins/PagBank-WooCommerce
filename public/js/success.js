//adds copy text from .pix-code to clipboard function on .copy-btn click
jQuery(document).ready(function($) {
    jQuery('.copy-btn').click(function() {
        var copyText = jQuery('.pix-code').val();
        copyToClipboard(copyText, function(){
            var originalText = jQuery('.copy-btn').text();
            jQuery('.copy-btn').text('Copiado!');
            setTimeout(function() {
                jQuery('.copy-btn').text(originalText);
            }, 2000);
        });
    });
});

async function copyToClipboard(textToCopy, successCallback) {
    // Navigator clipboard api needs a secure context (https)
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(textToCopy)
            .then(successCallback);
    } else {
        // Use the 'out of viewport hidden text area' trick
        const textArea = document.createElement("textarea");
        textArea.value = textToCopy;

        // Move textarea out of the viewport so it's not visible
        textArea.style.position = "absolute";
        textArea.style.left = "-999999px";

        document.body.prepend(textArea);
        textArea.select();

        try {
            document.execCommand('copy');
        } catch (error) {
            console.error(error);
        } finally {
            textArea.remove();
            successCallback();
        }
    }
}
