//add listener to the buttons in .ps-connect-buttons-container and display the fieldsets based on the button clicked
jQuery(document).ready(function($) {
    $(document).on('click', '.ps-connect-buttons-container button', function(e) {
        // debugger
        let methodName = e.target.id.replace('btn-pagseguro-', '')
        
        //disable all fieldsets with .ps-connect-method
        $('.ps_connect_method').hide()
        $('.ps_connect_method').attr('disabled', true)
        
        //enable the fieldset with the id of the button clicked
        $('#ps-connect-payment-' + methodName).show()
        $('#ps-connect-payment-' + methodName).attr('disabled', false)
        
        $('.ps-connect-buttons-container button').removeClass('active')
        $(this).addClass('active')
        
    })
})