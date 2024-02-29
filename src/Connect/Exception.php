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
            $msg = isset($error['code']) ? $error['code'] . ' - ' : '';
            $msg .= $this->errors[$error['code']] ?? 'Erro desconhecido';
            $friendlyParamName = $this->getFriendlyParameterName($error['parameter_name'] ?? '');
            $msg .= isset($error['parameter_name']) ? ' (' . $friendlyParamName . ')' : '';
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
        switch ($parameterName){
            case 'customer.tax_id':
                return $parameterName . ' - ' . esc_html( __('CPF/CNPJ', 'pagbank-connect') );
            case 'charges[0].payment_method.boleto.due_date':
                return $parameterName . ' - ' . esc_html( __('Data de vencimento do boleto', 'pagbank-connect') );
            case strpos($parameterName, 'locality') !== false:
                return $parameterName . ' - ' .esc_html(  __('Bairro', 'pagbank-connect') );
            case strpos($parameterName, 'address.number') !== false:
                return $parameterName . ' - ' . esc_html( __('Número do Endereço', 'pagbank-connect') );
            case strpos($parameterName, 'address.city') !== false:
                return $parameterName . ' - ' . esc_html( __('Cidade do Endereço', 'pagbank-connect') );
            case strpos($parameterName, 'address.region') !== false:
                return $parameterName . ' - ' . esc_html( __('Estado do Endereço', 'pagbank-connect') );
            case 'charges[0].payment_method.authentication_method.id':
                return 'authentication_method.id - ' . esc_html( __('Autenticação 3D - Recarregue e tente novamente', 'pagbank-connect') );
            case 'customer.name':
                return esc_html( __('Nome do Cliente', 'pagbank-connect') );
            case 'customer.email':
                return esc_html( __('E-mail do Cliente', 'pagbank-connect') );
        }
        
        return $parameterName;
    }

}
