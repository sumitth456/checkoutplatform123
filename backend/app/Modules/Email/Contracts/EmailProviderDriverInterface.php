<?php

namespace App\Modules\Email\Contracts;

use App\Models\EmailProvider;
use App\Modules\Email\Data\EmailSendRequest;
use App\Modules\Email\Data\EmailSendResult;

interface EmailProviderDriverInterface
{
    public function send(EmailProvider $provider, EmailSendRequest $request): EmailSendResult;

    public function testConnection(EmailProvider $provider): EmailSendResult;
}
