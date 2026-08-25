<?php

namespace App\Modules\Email\Services;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Model;

final class EmailTemplateRenderer
{
    public function __construct(
        private readonly EmailVariableResolver $variables,
    ) {}

    /**
     * @return array{subject: string, html_body: string, plain_text_body: string}
     */
    public function render(EmailTemplate $template, ?Model $context = null): array
    {
        $map = $this->variables->resolve($context);

        return [
            'subject' => $this->replace($template->subject, $map),
            'html_body' => $this->replace($template->html_body, $map),
            'plain_text_body' => $this->replace(
                $template->plain_text_body ?: $this->htmlToText($template->html_body),
                $map,
            ),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     */
    public function replace(string $content, array $variables): string
    {
        $search = [];
        $replace = [];

        foreach ($variables as $key => $value) {
            $search[] = '{{'.$key.'}}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $content);
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
