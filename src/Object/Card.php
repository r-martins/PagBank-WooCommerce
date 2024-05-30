<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Card
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Card implements JsonSerializable
{
    protected string $id;
    protected string $encrypted;
    protected string $network_token;
    protected int $exp_month;
    protected int $exp_year;
    protected string $security_code;
    protected bool $store;
    protected Holder $holder;
    protected TokenData $token_data;
    protected AuthenticationMethod $authentication_method;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getEncrypted(): string
    {
        return $this->encrypted;
    }

    /**
     * @param string $encrypted
     */
    public function setEncrypted(string $encrypted): void
    {
        $this->encrypted = $encrypted;
    }

    /**
     * @return string
     */
    public function getNetworkToken(): string
    {
        return $this->network_token;
    }

    /**
     * @param string $network_token
     */
    public function setNetworkToken(string $network_token): void
    {
        $this->network_token = $network_token;
    }

    /**
     * @return int
     */
    public function getExpMonth(): int
    {
        return $this->exp_month;
    }

    /**
     * @param int $exp_month
     */
    public function setExpMonth(int $exp_month): void
    {
        $this->exp_month = $exp_month;
    }

    /**
     * @return int
     */
    public function getExpYear(): int
    {
        return $this->exp_year;
    }

    /**
     * @param int $exp_year
     */
    public function setExpYear(int $exp_year): void
    {
        $this->exp_year = $exp_year;
    }

    /**
     * @return string
     */
    public function getSecurityCode(): string
    {
        return $this->security_code;
    }

    /**
     * @param string $security_code
     */
    public function setSecurityCode(string $security_code): void
    {
        $this->security_code = $security_code;
    }

    /**
     * @return bool
     */
    public function isStore(): bool
    {
        return $this->store;
    }

    /**
     * @param bool $store
     */
    public function setStore(bool $store): void
    {
        $this->store = $store;
    }

    /**
     * @return Holder
     */
    public function getHolder(): Holder
    {
        return $this->holder;
    }

    /**
     * @param Holder $holder
     */
    public function setHolder(Holder $holder): void
    {
        $this->holder = $holder;
    }

    /**
     * @return TokenData
     */
    public function getTokenData(): TokenData
    {
        return $this->token_data;
    }

    /**
     * @param TokenData $token_data
     */
    public function setTokenData(TokenData $token_data): void
    {
        $this->token_data = $token_data;
    }

    /**
     * @return AuthenticationMethod
     */
    public function getAuthenticationMethod(): AuthenticationMethod
    {
        return $this->authentication_method;
    }

    /**
     * @param AuthenticationMethod $authentication_method
     */
    public function setAuthenticationMethod(AuthenticationMethod $authentication_method): void
    {
        $this->authentication_method = $authentication_method;
    }
}
