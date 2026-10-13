<?php

namespace RM_PagBank\Partner;

/**
 * Feature flags by partner (avoids scattered if CONVD).
 */
class Capabilities
{
    /** @var PartnerDetector */
    private $detector;

    /** @var bool|null */
    private $threeDsAvailable;

    public function __construct(PartnerDetector $detector = null)
    {
        $this->detector = $detector ?: PartnerDetector::fromSettings();
        $this->threeDsAvailable = null;
    }

    public function detector(): PartnerDetector
    {
        return $this->detector;
    }

    public function hasHostedCheckout(): bool
    {
        return $this->detector->isPagBank();
    }

    public function hasSplit(): bool
    {
        return $this->detector->isPagBank();
    }

    public function forceNotificationProxy(): bool
    {
        return $this->detector->isVindi();
    }

    public function usesGatewayToken(): bool
    {
        return $this->detector->isVindi();
    }

    public function usesEncryptedCard(): bool
    {
        return $this->detector->isPagBank();
    }

    /**
     * PagBank: always can attempt 3DS via SDK.
     * Vindi: only if credit_card_3ds is active (cached).
     */
    public function has3ds(): bool
    {
        if ($this->detector->isPagBank()) {
            return true;
        }
        if ($this->threeDsAvailable !== null) {
            return $this->threeDsAvailable;
        }
        $this->threeDsAvailable = $this->probeVindi3ds();
        return $this->threeDsAvailable;
    }

    public function hasRecurring(): bool
    {
        // Both partners: recurring UI exists; Vindi needs re-token after migration.
        return true;
    }

    private function probeVindi3ds(): bool
    {
        $cacheKey = 'pagbank_vindi_3ds_' . $this->detector->fingerprint();
        $cached = get_transient($cacheKey);
        if ($cached === '1' || $cached === '0') {
            return $cached === '1';
        }

        try {
            $api = new \RM_PagBank\Helpers\Api();
            $api->setConnectKey($this->detector->getConnectKey());
            $resp = $api->get('payment_methods', ['query' => 'code:credit_card_3ds'], 30);
            $available = !empty($resp['credit_card_3ds']['available']);
            set_transient($cacheKey, $available ? '1' : '0', HOUR_IN_SECONDS);
            return $available;
        } catch (\Exception $e) {
            set_transient($cacheKey, '0', 15 * MINUTE_IN_SECONDS);
            return false;
        }
    }
}
