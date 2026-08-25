<?php

namespace App\Modules\Email\Data;

readonly class EmailSendRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $toEmail,
        public ?string $toName,
        public string $subject,
        public string $htmlBody,
        public ?string $plainTextBody = null,
        public ?string $replyToEmail = null,
        public ?string $replyToName = null,
        public array $metadata = [],
    ) {}
}
