<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class TransactionalEmails extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 43;

    protected static ?string $navigationLabel = 'Transactional Emails';

    protected static ?string $title = 'Transactional Emails';

    protected static ?string $slug = 'transactional-emails';

    protected string $view = 'filament.admin.pages.transactional-emails';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'email_templates.manage');
    }

    public function mount(): void
    {
        $this->redirect(EmailTemplateResource::getUrl('index', [
            'tableFilters' => [
                'category' => ['value' => 'transactional'],
            ],
        ]));
    }
}
