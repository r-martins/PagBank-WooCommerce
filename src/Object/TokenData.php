<?php

namespace RM_PagBank\Object;

/**
 * Class TokenData
 * Objeto contendo os dados adicionais de Tokenização de Bandeira.
 * ⚠ Deve ser enviado quando um Cartão de Crédito ou Débito Tokenizado pelas bandeiras Visa ou Mastercard é utilizado. ⚠
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class TokenData implements \JsonSerializable
{
    /*Identificador de quem gerou o Token de Bandeira (Token Requestor)*/
    protected string $requestor_id;
    
    /*Tipo de carteira que armazenou o Token de Bandeira. (APPLE_PAY, GOOGLE_PAY, SAMSUNG_PAY, MERCHANT_TOKENIZATION_PROGRAM)*/
    protected string $wallet;
    
    /*Criptograma gerado pela bandeira*/
    protected string $cryptogram;
    
    /*Identificador do domínio de origem da transação, comumente caracterizado em um formato de domínio reverso. Exemplo: br.com.pagseguro*/
    protected string $ecommerce_domain;
    protected int $assurance_level;

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
    
    /**
     * @return string
     */
    public function getRequestorId(): string
    {
        return $this->requestor_id;
    }

    /**
     * @param string $requestor_id
     */
    public function setRequestorId(string $requestor_id): void
    {
        $this->requestor_id = $requestor_id;
    }

    /**
     * @return string
     */
    public function getWallet(): string
    {
        return $this->wallet;
    }

    /**
     * @param string $wallet
     */
    public function setWallet(string $wallet): void
    {
        $this->wallet = $wallet;
    }

    /**
     * @return string
     */
    public function getCryptogram(): string
    {
        return $this->cryptogram;
    }

    /**
     * @param string $cryptogram
     */
    public function setCryptogram(string $cryptogram): void
    {
        $this->cryptogram = $cryptogram;
    }

    /**
     * @return string
     */
    public function getEcommerceDomain(): string
    {
        return $this->ecommerce_domain;
    }

    /**
     * @param string $ecommerce_domain
     */
    public function setEcommerceDomain(string $ecommerce_domain): void
    {
        $this->ecommerce_domain = $ecommerce_domain;
    }

    /**
     * @return string
     */
    public function getAssuranceLevel(): int
    {
        return $this->assurance_level;
    }

    /**
     * @param string $assurance_level
     */
    public function setAssuranceLevel(int $assurance_level): void
    {
        $this->assurance_level = $assurance_level;
    }
    
}