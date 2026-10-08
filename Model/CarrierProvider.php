<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model;

use AhsanHafeez\ShipmentTracking\Model\Carrier\Carrier;
use AhsanHafeez\ShipmentTracking\Model\Carrier\CarrierParser;

/**
 * Parsed carrier list per store, cached for the request (an order page may render many tracks).
 */
class CarrierProvider
{
    /** @var array<int, array{carriers: array<string, Carrier>, errors: string[]}> */
    private array $cache = [];

    public function __construct(
        private readonly Config $config,
        private readonly CarrierParser $parser
    ) {
    }

    /**
     * @return array<string, Carrier>
     */
    public function getCarriers(?int $storeId = null): array
    {
        return $this->load($storeId)['carriers'];
    }

    /**
     * @return string[]
     */
    public function getConfigErrors(?int $storeId = null): array
    {
        return $this->load($storeId)['errors'];
    }

    /**
     * @return array{carriers: array<string, Carrier>, errors: string[]}
     */
    private function load(?int $storeId): array
    {
        $key = (int) $storeId;

        return $this->cache[$key] ??= $this->parser->parse($this->config->getCarriersConfig($storeId));
    }
}
