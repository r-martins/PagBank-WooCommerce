<?php

namespace RM_PagBank\Partner;

/**
 * Detects payment partner from Connect Key prefix.
 *
 * Vindi keys (new): fixed length 40 for prod and sandbox (same as PagBank CON… keys).
 * Legacy shorter CONVD… keys remain valid.
 */
class PartnerDetector
{
    /** @var string */
    private $partner;

    /** @var bool */
    private $sandbox;

    /** @var string|null */
    private $templateCode;

    /** @var string */
    private $connectKey;

    public function __construct(string $connectKey = '')
    {
        $this->connectKey = trim($connectKey);
        $this->sandbox = false;
        $this->templateCode = null;
        $this->partner = Partner::PAGBANK;
        $this->parse();
    }

    public static function fromKey(string $connectKey): self
    {
        return new self($connectKey);
    }

    public static function fromSettings(): self
    {
        $gateway = new \RM_PagBank\Connect\Gateway();
        $key = (string) $gateway->get_option('connect_key', '');
        return new self($key);
    }

    private function parse(): void
    {
        $key = strtoupper($this->connectKey);
        if ($key === '') {
            return;
        }

        if (strpos($key, 'CONVDSANDBOX') === 0 || strpos($key, 'CONVD') === 0) {
            $this->partner = Partner::VINDI;
            $this->sandbox = strpos($key, 'CONVDSANDBOX') === 0;
            if (preg_match('/^CONVD(?:SANDBOX)?(30|14|FLX)/', $key, $m)) {
                $map = ['30' => '30dias', '14' => '14dias', 'FLX' => 'flex'];
                $this->templateCode = $map[$m[1]] ?? null;
            }
            return;
        }

        $this->partner = Partner::PAGBANK;
        $this->sandbox = strpos($key, 'CONSANDBOX') === 0;
    }

    public function getPartner(): string
    {
        return $this->partner;
    }

    public function isVindi(): bool
    {
        return $this->partner === Partner::VINDI;
    }

    public function isPagBank(): bool
    {
        return $this->partner === Partner::PAGBANK;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /** @return string|null */
    public function getTemplateCode()
    {
        return $this->templateCode;
    }

    public function getConnectKey(): string
    {
        return $this->connectKey;
    }

    /** Last 4 chars for fingerprint / notices (never log full key). */
    public function fingerprint(): string
    {
        if (strlen($this->connectKey) < 4) {
            return '';
        }
        return substr($this->connectKey, -4);
    }
}
