<?php

namespace RM_PagBank\Connect;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;
use Throwable;

/**
 * Class Exception
 * Deals with common exceptions from the API and bring friendly messages. Also logs the errors.
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect
 */
class Exception extends \Exception
{
    public array $errors = [
        '40001' =>	'Parâmetro obrigatório. Algum dado obrigatório não foi informado.',
        '40002' =>	'Parâmetro inválido. Algum dado foi informado com formato inválido ou o conjunto de dados não cumpriu todos os requisitos de negócio.',
        '42001' =>	'Falha na criação de conta. A conta já existe no PagBank. Para ter acesso aos dados dessa conta ou criar pagamentos em nome do dono da conta, é necessário solicitar permissão via API Connect.',
        '42002' =>	'Falha na criação de conta. O processo de criação foi iniciado por outro canal diferente da API. O usuário precisa acessar o email para finalizar a criação de conta.',
		'UNAUTHORIZED' => 'Não autorizado. Lojista: verifique se a sua Connect Key está correta e é válida.',
    ];

	/**
	 * @param array          $error_messages
	 * @param 	             $code
	 * @param Throwable|null $previous
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 * */
	public function __construct(array $error_messages, $code = 0, Throwable $previous = null)
    {
        $message = [];
        $original_error_messages = [];
        foreach ($error_messages as $error) {
            $original_error_messages[] = ($error['code'] ?? '').' - '.($error['description'] ?? '' ).' ('.($error['parameter_name'] ?? '')
                .')';
            $msg = array_key_exists(($error['code'] ?? ''), $this->errors) 
                ? $this->getFriendlyMsgWithErrorCode($error) 
                : $this->getFriendlyMessageWithoutErrorCode($error);

            if (isset($error['parameter_name'])){
                $friendlyParamName = $this->getFriendlyParameterName($error['parameter_name']);
                $msg .= ' (' . $friendlyParamName . ')';
            }

            $message[] = $msg;
        }

        Functions::log('Erro Connect: ' . implode(', ', $original_error_messages), 'error');
        $message = implode("<br/>\n", $message);
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns a friendly name for the parameter that is missing or invalid
     * @param string $parameterName
     *
     * @return string
     */
    public function getFriendlyParameterName(string $parameterName): string
    {
        // Exact matches that don't include parameter name prefix
        $exactMatchesNoPrefix = [
            'charges[0].payment_method.authentication_method.id' => __('Autenticação 3D - Recarregue e tente novamente', 'pagbank-connect'),
            'charges[0].payment_method.card.encrypted' => __('Criptografia do cartão', 'pagbank-connect'),
            ''
        ];

        if (isset($exactMatchesNoPrefix[$parameterName])) {
            return esc_html($exactMatchesNoPrefix[$parameterName]);
        }

        // Exact matches - using array for better performance and readability
        $exactMatches = [
            'customer.tax_id' => __('CPF/CNPJ', 'pagbank-connect'),
            'charges[0].payment_method.boleto.due_date' => __('Data de vencimento do boleto', 'pagbank-connect'),
            'customer.name' => __('Nome do Cliente', 'pagbank-connect'),
            'customer.phones[0].number' => __('Telefone', 'pagbank-connect'),
            'customer.email' => __('E-mail do Cliente', 'pagbank-connect'),
            'customer.phone.number' => __('Telefone do Cliente', 'pagbank-connect'),
        ];

        if (isset($exactMatches[$parameterName])) {
            return $parameterName . ' - ' . esc_html($exactMatches[$parameterName]);
        }

        // Pattern matches - checked in order of specificity
        $patternMatches = [
            'locality' => __('Bairro', 'pagbank-connect'),
            'address.number' => __('Número do Endereço', 'pagbank-connect'),
            'address.city' => __('Cidade do Endereço', 'pagbank-connect'),
            'address.region' => __('Estado do Endereço', 'pagbank-connect'),
        ];

        foreach ($patternMatches as $pattern => $friendlyName) {
            if (strpos($parameterName, $pattern) !== false) {
                return $parameterName . ' - ' . esc_html($friendlyName);
            }
        }

        return $parameterName;
    }


    /**
     * Get friendly msg when error code is available
     * @param $error
     *
     * @return string|void
     */
    public function getFriendlyMsgWithErrorCode($error)
    {
        if (isset($this->errors[$error['code']])) {
            $msg = $this->getFriendlyMsg($error);
            return $error['code'] . ' - ' . $msg;
        }
    }
    public function getFriendlyMessageWithoutErrorCode($error)
    {
        if (!isset($error['message']) && isset($error['description'])) {
            return $this->getFriendlyMsg($error);
        }
        switch ($error['message']) {
            case 'CARD_CANNOT_BE_STORED':
                return __(
                    'Cartão não pode ser armazenado. Tente novamente com outro cartão ou verifique se as informações '
                    .'digitadas estão corretas.',
                    'pagbank-connect'
                );
                break;
            case 'encrypted_is_invalid':
                return __(
                    'Cripografia do cartão inválida. Tente novamente com outro cartão ou verifique se as informações '
                    .'digitadas estão corretas.',
                    'pagbank-connect'
                );
                break;
            default:
                return $error['message'] ?? 'Erro desconhecido.';
        }    
    }
    
    public function getFriendlyMsg($error)
    {
        if(isset($error['description'])){
            switch ($error['description']) {
                case 'CARD_CANNOT_BE_STORED':
                    return __(
                        'Cartão não pode ser armazenado. Tente novamente com outro cartão ou verifique se as informações '
                        .'digitadas estão corretas.',
                        'pagbank-connect'
                    );
                    break;
                case 'buyer email must not be equals to merchant email':
                    return __(
                        'O e-mail do comprador não pode ser igual ao e-mail do lojista.',
                        'pagbank-connect'
                    );
                    break;
                case 'must not be blank':
                    return __(
                        'Valor obrigatório.',
                        'pagbank-connect'
                    );
                    break;
                case 'invalid_parameter':
                    return __(
                        'Valor inválido.',
                        'pagbank-connect'
                    );
                    break;
                case 'must be a valid region code by ISO 3166-2:BR':
                    return __(
                        'Valor de estado inválido.',
                        'pagbank-connect'
                    );
                    break;
                case 'must not contains any of the characters [!, @, #, $, %, ¨, *, (, ), ", ”, \, |, {, }, [, ], <, >, ;]':
                    return __(
                        'Valor não pode conter caracteres especiais.',
                        'pagbank-connect'
                    );
                    break;
                case 'must be a valid CPF or CNPJ':
                    return __(
                        'CPF ou CNPJ inválido.',
                        'pagbank-connect'
                    );
                    break;
                case 'Parameter value has an invalid value, see documentation.':
                    return __(
                        'Valor inválido. Veja documentação.',
                        'pagbank-connect'
                    );
                    break;
                case 'must be between 10000000 and 999999999':
                    return __(
                        'Valor deve estar entre 10000000 e 999999999.',
                        'pagbank-connect'
                    );
                    break;
                case 'Field has an invalid value. Please check the documentation.':
                    return __('
                        Campo com valor inválido. Por favor, verifique a documentação.',
                        'pagbank-connect'
                    );
                case 'Field cannot be empty.':
                    return __(
                        'O campo não pode estar vazio.',
                        'pagbank-connect'
                    );
                case 'The option field or value field are invalids. Please check the documentation.':
                    return __(
                        'Os campos de opção ou valor são inválidos. Por favor, verifique a documentação.',
                        'pagbank-connect'
                    );
                case 'The payment method is not valid to be configured.':
                    return __(
                        'O método de pagamento não é válido para ser configurado.',
                        'pagbank-connect'
                    );
                case 'Field shipping has an invalid configuration. Please check the documentation.':
                    return __(
                        'O campo de frete possui uma configuração inválida. Por favor, verifique a documentação.',
                        'pagbank-connect'
                    );
                case 'There are some syntax errors in the request payload. Please check the documentation.':
                    return __(
                        'Há alguns erros de sintaxe na solicitação. Por favor, verifique a documentação e os logs.',
                        'pagbank-connect'
                    );
                case 'This receiver is already responsible for paying the chargeback. You can inform another receiver as responsible for being the chargeback debtor.':
                    return __(
                        'Este recebedor já está responsável por pagar o chargeback. Você pode informar outro receptor como responsável por ser o devedor do chargeback.',
                        'pagbank-connect'
                    );
                case 'Receivers amount must be informed.':
                    return __(
                        'O valor pago aos recebedores deve ser informado.',
                        'pagbank-connect'
                    );
                case '3DS AUTHENTICATION IS NOT VALID METHOD FOR SPLIT TRANSACTIONS WITH LIABLE CONFIGURATION':
                    return __(
                        'A autenticação 3D não é um método válido para transações com configuração de responsabilidade do receptor (liable).',
                        'pagbank-connect'
                    );
                default:
                    return $this->errors[$error['code']] ?? $error['description'];
            }
            return __(
                'Erro desconhecido.',
                'pagbank-connect'
            );
        }
    }

}
