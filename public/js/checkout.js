//add listener to the buttons in .ps-connect-buttons-container and display the fieldsets based on the button clicked
jQuery(document).ready(function($) {

    jQuery(document).on('click', '.ps-connect-buttons-container button', function(e) {
        let methodName = jQuery(this).attr('id').replace('btn-pagseguro-', '')

        //disable all fieldsets with .ps-connect-method
        jQuery('.ps_connect_method').hide()
        jQuery('.ps_connect_method').attr('disabled', true)

        //enable the fieldset with the id of the button clicked
        jQuery('#ps-connect-payment-' + methodName).show()
        jQuery('#ps-connect-payment-' + methodName).removeAttr('disabled')

        jQuery('.ps-connect-buttons-container button').removeClass('active')
        jQuery(this).addClass('active')

    })
    
    //global function to translate and parse error messages
    window.pagBankParseErrorMessage = function(errorMessage) {
        const codes = {
            '40001': 'Parâmetro obrigatório',
            '40002': 'Parâmetro inválido',
            '40003': 'Parâmetro desconhecido ou não esperado',
            '40004': 'Limite de uso da API excedido',
            '40005': 'Método não permitido',
        };
    
        const descriptions = {
            "must match the regex: ^\\p{L}+['.-]?(?:\\s+\\p{L}+['.-]?)+$": 'parece inválido ou fora do padrão permitido',
            'cannot be blank': 'não pode estar em branco',
            'size must be between 8 and 9': 'deve ter entre 8 e 9 caracteres',
            'must be numeric': 'deve ser numérico',
            'must be greater than or equal to 100': 'deve ser maior ou igual a 100',
            'must be between 1 and 24': 'deve ser entre 1 e 24',
            'only ISO 3166-1 alpha-3 values are accepted': 'deve ser um código ISO 3166-1 alpha-3',
            'either paymentMethod.card.id or paymentMethod.card.encrypted should be informed': 'deve ser informado o cartão de crédito criptografado ou o id do cartão',
            'must be an integer number': 'deve ser um número inteiro',
            'card holder name must contain a first and last name': 'o nome do titular do cartão deve conter um primeiro e último nome',
            'must be a well-formed email address': 'deve ser um endereço de e-mail válido',
        };
    
        const parameters = {
            'amount.value': 'valor do pedido',
            'customer.name': 'nome do cliente',
            'customer.phones[0].number': 'número de telefone do cliente',
            'customer.phones[0].area': 'DDD do telefone do cliente',
            'billingAddress.complement': 'complemento/bairro do endereço de cobrança',
            'paymentMethod.installments': 'parcelas',
            'billingAddress.country': 'país de cobrança',
            'paymentMethod.card': 'cartão de crédito',
            'paymentMethod.card.encrypted': 'cartão de crédito criptografado',
            'customer.email': 'e-mail',
        };

        // Get the code, description, and parameterName from the errorMessage object
        const { code, description, parameterName } = errorMessage;

        // Look up the translations
        const codeTranslation = codes[code] || code;
        const descriptionTranslation = descriptions[description] || description;
        const parameterTranslation = parameters[parameterName] || parameterName;

        // Concatenate the translations into a single string
        return `${codeTranslation}: ${parameterTranslation} - ${descriptionTranslation}`;
    }
})
