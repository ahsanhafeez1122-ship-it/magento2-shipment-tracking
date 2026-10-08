<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Test\Unit\Model\Status;

use AhsanHafeez\ShipmentTracking\Model\Status\DeliveryStatus as S;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DeliveryStatusTest extends TestCase
{
    public function testMapsCarrierWording(): void
    {
        self::assertSame(S::OUT_FOR_DELIVERY, S::fromRaw('OUT_FOR_DELIVERY'));
        self::assertSame(S::OUT_FOR_DELIVERY, S::fromRaw('Out for delivery'));
        self::assertSame(S::DELIVERED, S::fromRaw('Delivered to neighbour'));
        self::assertSame(S::EXCEPTION, S::fromRaw('failed-attempt'));
        self::assertSame(S::IN_TRANSIT, S::fromRaw('in_transit'));
        self::assertNull(S::fromRaw('Something new'));
    }

    public function testFirstStatusAlwaysApplies(): void
    {
        self::assertTrue(S::shouldReplace(null, null, S::IN_TRANSIT, $this->at('09:00')));
    }

    public function testStatusMovesForward(): void
    {
        self::assertTrue(S::shouldReplace(S::IN_TRANSIT, $this->at('09:00'), S::OUT_FOR_DELIVERY, $this->at('10:00')));
    }

    public function testLateArrivingOlderEventDoesNotGoBackwards(): void
    {
        self::assertFalse(S::shouldReplace(S::OUT_FOR_DELIVERY, $this->at('10:00'), S::IN_TRANSIT, $this->at('09:00')));
    }

    public function testDeliveredIsFinal(): void
    {
        self::assertFalse(S::shouldReplace(S::DELIVERED, $this->at('12:00'), S::EXCEPTION, $this->at('13:00')));
    }

    public function testRecoveryAfterProblemUsesEventTime(): void
    {
        self::assertTrue(S::shouldReplace(S::EXCEPTION, $this->at('09:00'), S::IN_TRANSIT, $this->at('11:00')));
        self::assertFalse(S::shouldReplace(S::EXCEPTION, $this->at('11:00'), S::IN_TRANSIT, $this->at('09:00')));
    }

    public function testHumanLabels(): void
    {
        self::assertSame('Out for delivery', S::label(S::OUT_FOR_DELIVERY));
        self::assertSame('Some status', S::label('some_status'));
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-07 ' . $time . ':00');
    }
}
