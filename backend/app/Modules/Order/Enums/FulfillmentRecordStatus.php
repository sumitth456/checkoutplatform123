<?php

namespace App\Modules\Order\Enums;

enum FulfillmentRecordStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case OnHold = 'on_hold';
    case Scheduled = 'scheduled';
    case Packed = 'packed';
    case Processing = 'processing';
    case ReadyToShip = 'ready_to_ship';
    case Submitted = 'submitted';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case RmaRequested = 'rma_requested';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case ReshipRequested = 'reship_requested';
    case Reshipped = 'reshipped';
    case SplitShipment = 'split_shipment';
    case PartialShipment = 'partial_shipment';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }

    /** @return list<self> */
    public static function activePipeline(): array
    {
        return [
            self::Pending,
            self::Queued,
            self::OnHold,
            self::Scheduled,
            self::Packed,
            self::Processing,
            self::ReadyToShip,
            self::Submitted,
            self::Shipped,
            self::InTransit,
        ];
    }
}
