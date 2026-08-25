<?php

namespace App\Modules\Email\Enums;

enum EmailProviderType: string
{
    case Smtp = 'smtp';
    case Mailjet = 'mailjet';
    case Klaviyo = 'klaviyo';
    case SendGrid = 'sendgrid';
    case Ses = 'ses';
    case Mailgun = 'mailgun';
    case Postmark = 'postmark';

    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP',
            self::Mailjet => 'Mailjet',
            self::Klaviyo => 'Klaviyo',
            self::SendGrid => 'SendGrid',
            self::Ses => 'Amazon SES',
            self::Mailgun => 'Mailgun',
            self::Postmark => 'Postmark',
        };
    }
}
