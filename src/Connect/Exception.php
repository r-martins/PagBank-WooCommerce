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
        if ($parameterName === 'customer.tax_id') {
            return $parameterName . ' - ' . esc_html(__('CPF/CNPJ', 'pagbank-connect'));
        } elseif ($parameterName === 'charges[0].payment_method.boleto.due_date') {
            return $parameterName . ' - ' . esc_html(__('Data de vencimento do boleto', 'pagbank-connect'));
        } elseif (strpos($parameterName, 'locality') !== false) {
            return $parameterName . ' - ' . esc_html(__('Bairro', 'pagbank-connect'));
        } elseif (strpos($parameterName, 'address.number') !== false) {
            return $parameterName . ' - ' . esc_html(__('Número do Endereço', 'pagbank-connect'));
        } elseif (strpos($parameterName, 'address.city') !== false) {
            return $parameterName . ' - ' . esc_html(__('Cidade do Endereço', 'pagbank-connect'));
        } elseif (strpos($parameterName, 'address.region') !== false) {
            return $parameterName . ' - ' . esc_html(__('Estado do Endereço', 'pagbank-connect'));
        } elseif ($parameterName === 'charges[0].payment_method.authentication_method.id') {
            return esc_html(__('Autenticação 3D - Recarregue e tente novamente', 'pagbank-connect'));
        } elseif ($parameterName === 'charges[0].payment_method.card.encrypted') {
            return esc_html(__('Criptografia do cartão', 'pagbank-connect'));
        } elseif ($parameterName === 'customer.name') {
            return esc_html(__('Nome do Cliente', 'pagbank-connect'));
        } elseif ($parameterName === 'customer.phones[0].number') {
            return esc_html(__('Telefone', 'pagbank-connect'));
        } elseif ($parameterName === 'customer.email') {
            return esc_html(__('E-mail do Cliente', 'pagbank-connect'));
        } elseif ($parameterName === 'customer.phone.number') {
            return esc_html(__('Telefone do Cliente', 'pagbank-connect'));
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
