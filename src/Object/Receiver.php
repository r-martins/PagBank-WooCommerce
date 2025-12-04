<?php

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Receiver
 * 
 * Represents a receiver in the split payment structure
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Receiver implements JsonSerializable
{
    protected array $account;
    protected array $amount;
    protected string $reason;
    protected string $type;
    protected array $configurations;

    const TYPE_PRIMARY = 'PRIMARY';
    const TYPE_SECONDARY = 'SECONDARY';

    public function __construct()
    {
        $this->account = [];
        $this->amount = [];
        $this->configurations = [];
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $data = [
            'account' => $this->account,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'type' => $this->type,
        ];
        
        // Only add configurations if not empty
        if (!empty($this->configurations)) {
            $data['configurations'] = $this->configurations;
        }
        
        return $data;
    }

    /**
     * @return array
     */
    public function getAccount(): array
    {
        return $this->account;
    }

    /**
     * @param array $account
     */
    public function setAccount(array $account): void
    {
        $this->account = $account;
    }

    /**
     * @return array
     */
    public function getAmount(): array
    {
        return $this->amount;
    }

    /**
     * @param array $amount
     */
    public function setAmount(array $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @param string $reason
     */
    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return array
     */
    public function getConfigurations(): array
    {
        return $this->configurations;
    }

    /**
     * @param array $configurations
     */
    public function setConfigurations(array $configurations): void
    {
        $this->configurations = $configurations;
    }

    /**
     * Set custody configuration
     *
     * @param bool $apply
     * @param string|null $scheduled_date
     */
    public function setCustody(bool $apply, ?string $scheduled_date = null): void
    {
        $this->configurations['custody'] = [
            'apply' => $apply
        ];

        if ($apply && $scheduled_date) {
            $this->configurations['custody']['release'] = [
                'scheduled' => $scheduled_date
            ];
        }
    }

    /**
     * Set chargeback configuration
     *
     * @param float $percentage
     */
    public function setChargeback(float $percentage): void
    {
        $this->configurations['chargeback'] = [
            'charge_transfer' => [
                'percentage' => $percentage
            ]
        ];
    }

    /**
     * Set liable configuration
     *
     * @param bool $liable
     */
    public function setLiable(bool $liable): void
    {
        $this->configurations['liable'] = $liable;
    }

    /**
     * Set statement configuration
     *
     * @param bool $show_full_value
     */
    public function setStatement(bool $show_full_value): void
    {
        $this->configurations['statement'] = [
            'amount' => [
                'show_full_value' => $show_full_value
            ]
        ];
    }
}


