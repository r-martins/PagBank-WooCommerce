<?php

namespace RM_PagBank\Connect;

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
            $original_error_messages[] = @$error['code'].' - '.@$error['description'].' ('.@$error['parameter_name']
                .')';
            $msg = isset($error['code']) ? $error['code'] . ' - ' : '';
            $msg .= $this->errors[$error['code']] ?? 'Erro desconhecido';
            $msg .= isset($error['parameter_name']) ? ' (' . $error['parameter_name'] . ')' : '';
            $message[] = $msg;
        }

        Functions::log('Erro Connect: ' . implode(', ', $original_error_messages), 'error');
        $message = implode("<br/>\n", $message);
        parent::__construct($message, $code, $previous);
    }

}
