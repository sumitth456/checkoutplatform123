<?php

namespace App\Modules\Email\Data;

readonly class EmailSendResult
{
    public function __construct(
        public bool $success,
        public ?string $messageId = null,
        public ?array $response = null,
        public ?string $error = null,
    ) {}
}
