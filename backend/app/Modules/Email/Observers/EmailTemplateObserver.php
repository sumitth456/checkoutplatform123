<?php

namespace App\Modules\Email\Observers;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use Illuminate\Support\Facades\Auth;

class EmailTemplateObserver
{
    public function updating(EmailTemplate $template): void
    {
        if (! $template->isDirty(['subject', 'html_body', 'plain_text_body'])) {
            return;
        }

        EmailTemplateVersion::query()->create([
            'email_template_id' => $template->id,
            'version' => $template->version,
            'subject' => $template->getOriginal('subject'),
            'html_body' => $template->getOriginal('html_body'),
            'plain_text_body' => $template->getOriginal('plain_text_body'),
            'created_by' => Auth::id(),
        ]);

        $template->version = ($template->version ?? 1) + 1;
    }
}
