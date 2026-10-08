<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Controller\Webhook;

use AhsanHafeez\ShipmentTracking\Model\Config;
use AhsanHafeez\ShipmentTracking\Model\Webhook\InvalidPayloadException;
use AhsanHafeez\ShipmentTracking\Model\Webhook\PayloadParser;
use AhsanHafeez\ShipmentTracking\Model\Webhook\SignatureVerifier;
use AhsanHafeez\ShipmentTracking\Service\EventProcessor;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * POST /shipment-tracking/webhook/receive
 *
 * Authenticated by HMAC signature instead of a form key, so CSRF validation is switched off here.
 * Responds 200 for duplicates and unknown tracks too, so the provider stops retrying them.
 */
class Receive implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly Config $config,
        private readonly SignatureVerifier $verifier,
        private readonly PayloadParser $parser,
        private readonly EventProcessor $processor,
        private readonly JsonFactory $jsonFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        if (!$this->config->isWebhookEnabled()) {
            return $this->respond(404, ['error' => 'Webhook is disabled.']);
        }

        $body = (string) $this->request->getContent();
        $signature = (string) $this->request->getHeader('X-Tracking-Signature');

        if (!$this->verifier->verify($signature, $body, $this->config->getWebhookSecret(), time(), $this->config->getWebhookTolerance())) {
            return $this->respond(401, ['error' => 'Invalid or expired signature.']);
        }

        try {
            $event = $this->parser->parse($body);
        } catch (InvalidPayloadException $e) {
            return $this->respond(422, ['error' => $e->getMessage()]);
        }

        try {
            $result = $this->processor->process($event);
        } catch (Throwable $e) {
            $this->logger->error('Shipment tracking webhook failed', ['event_id' => $event->externalId, 'exception' => $e]);
            return $this->respond(500, ['error' => 'Could not store the event. Please retry.']);
        }

        if ($result === EventProcessor::RESULT_UNKNOWN_TRACK) {
            $this->logger->info('Shipment tracking webhook for unknown number', ['number' => $event->trackingNumber]);
        }

        return $this->respond(200, ['result' => $result, 'event_id' => $event->externalId]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param array<string, string> $data
     */
    private function respond(int $code, array $data): Json
    {
        return $this->jsonFactory->create()->setHttpResponseCode($code)->setData($data);
    }
}
