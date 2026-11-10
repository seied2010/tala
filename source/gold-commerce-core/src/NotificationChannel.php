<?php
declare(strict_types=1);
namespace GoldCommerce;
/** Adapter for a selected official SMS/messaging service; never store credentials in payload. */
interface NotificationChannel {
    /** Must enforce provider idempotency or return an explicit unknown result on timeout. */
    public function send(string $recipient,string $subject,string $body,string $idempotencyKey):string;
}
