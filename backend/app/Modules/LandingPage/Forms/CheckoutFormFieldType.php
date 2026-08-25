<?php

namespace App\Modules\LandingPage\Forms;

enum CheckoutFormFieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Tel = 'tel';
    case Number = 'number';
    case Textarea = 'textarea';
    case Select = 'select';
    case Hidden = 'hidden';
    case PaymentToken = 'payment_token';
    case SectionHeading = 'section_heading';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
