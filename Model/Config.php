<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_ENABLED = 'shipment_tracking/general/enabled';
    private const XML_CARRIERS = 'shipment_tracking/general/carriers';
    private const XML_WEBHOOK_ENABLED = 'shipment_tracking/webhook/enabled';
    private const XML_WEBHOOK_SECRET = 'shipment_tracking/webhook/secret';
    private const XML_WEBHOOK_TOLERANCE = 'shipment_tracking/webhook/tolerance_seconds';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCarriersConfig(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_CARRIERS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isWebhookEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_WEBHOOK_ENABLED);
    }

    public function getWebhookSecret(): string
    {
        $stored = (string) $this->scopeConfig->getValue(self::XML_WEBHOOK_SECRET);

        return $stored === '' ? '' : $this->encryptor->decrypt($stored);
    }

    public function getWebhookTolerance(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_WEBHOOK_TOLERANCE));
    }
}
