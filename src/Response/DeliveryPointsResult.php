<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/**
 * A `GetDeliveryPoints` eredménye.
 *
 * Ha `changed` hamis, a szerver nem küldött adatot (a helyi cache érvényes),
 * és a `points` üres. A `lastUpdateTime`-ot a következő kéréshez kell
 * eltenni. A pontok a dokumentáció `DeliveryPoint` alakjában, nyers
 * tömbként jönnek (`Id`, `DeliveryPointType` 1=ParcelShop / 2=ParcelLocker /
 * 3=Depot, `CodHandler`, `Address`, `DeliveryPointOpeningList`, …).
 */
final readonly class DeliveryPointsResult
{
    /** @param list<array<mixed>> $points */
    public function __construct(
        public bool $changed,
        public ?\DateTimeImmutable $lastUpdateTime,
        public array $points,
    ) {
    }
}
