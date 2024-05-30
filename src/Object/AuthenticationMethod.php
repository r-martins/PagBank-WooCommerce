<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class AuthenticationMethod
 * Objeto contendo os dados adicionais de autenticação vínculados à uma transação.
 * Obrigatório para o método de pagamento com cartão de débito. ⚠️
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class AuthenticationMethod implements JsonSerializable
{
    /*Indica o método de autenticação utilizado na cobrança. ⚠️ Condicional para Token de Bandeira ELO. ⚠️
    - THREEDS se o método de autenticação utilizado for 3DS.
    - INAPP se o método de autenticação utilizado for InApp. */
    protected string $type;

    /*Identificador do método de autenticação utilizado.*/
    protected string $id;

    /*Identificador único gerado em cenário de sucesso de autenticação do cliente.*/
    protected string $cavv;

    /*Indicador E-Commerce retornado quando ocorre uma autenticação. Corresponde ao resultado da autenticação.
    * (required)
    */
    protected string $eci;

    /*Identificador de uma transação de um MPI - Recomendado para a bandeira VISA. ⚠️ Condicional para 3DS. ⚠️*/
    protected string $xid;

    /*Versão do protocolo 3DS utilizado na autenticação.*/
    protected string $version;

    /*ID da transação gerada pelo servidor de diretório durante uma autenticação - Recomendado para a bandeira MASTERCARD. ⚠️ Condicional para 3DS. ⚠️*/
    protected string $dstrans_id;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
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
    public function getCavv(): string
    {
        return $this->cavv;
    }

    /**
     * @param string $cavv
     */
    public function setCavv(string $cavv): void
    {
        $this->cavv = $cavv;
    }

    /**
     * @return string
     */
    public function getEci(): string
    {
        return $this->eci;
    }

    /**
     * @param string $eci
     */
    public function setEci(string $eci): void
    {
        $this->eci = $eci;
    }

    /**
     * @return string
     */
    public function getXid(): string
    {
        return $this->xid;
    }

    /**
     * @param string $xid
     */
    public function setXid(string $xid): void
    {
        $this->xid = $xid;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getDstransId(): string
    {
        return $this->dstrans_id;
    }

    /**
     * @param string $dstrans_id
     */
    public function setDstransId(string $dstrans_id): void
    {
        $this->dstrans_id = $dstrans_id;
    }

}
