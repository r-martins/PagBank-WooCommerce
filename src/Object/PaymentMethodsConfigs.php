<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class PaymentMethodsConfigs
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 * @link https://developer.pagbank.com.br/reference/criar-checkout
 */
class PaymentMethodsConfigs implements JsonSerializable
{
    /**
     * @var string CREDIT_CARD, PIX, BOLETO, DEBIT_CARD
     */
    private string $type;
    /**
     * @var array of PaymentMethodConfigOptions
     */
    private array $config_options;


    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

	public function getType(): string
	{
		return $this->type;
	}

	public function setType(string $type): void
	{
		$this->type = $type;
	}

    public function getConfigOptions(): array
    {
        return $this->config_options;
    }

    public function setConfigOptions(array $configOptions): void
    {
        $this->config_options = $configOptions;
    }

}
