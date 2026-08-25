<?php



namespace App\Filament\Admin\Resources\Roles\Schemas;



use App\Models\Permission;

use Filament\Forms\Components\CheckboxList;

use Filament\Forms\Components\Textarea;

use Filament\Forms\Components\TextInput;

use Filament\Schemas\Components\Section;

use Filament\Schemas\Schema;



class RoleForm

{

    public static function configure(Schema $schema): Schema

    {

        return $schema

            ->components([

                Section::make('Role')

                    ->schema([

                        TextInput::make('name')

                            ->required()

                            ->maxLength(255)

                            ->unique(ignoreRecord: true),

                        Textarea::make('description')

                            ->rows(2)

                            ->maxLength(1000),

                    ]),

                Section::make('Permissions')

                    ->schema([

                        CheckboxList::make('permissions')

                            ->relationship('permissions', 'name')

                            ->options(

                                Permission::query()

                                    ->orderBy('group')

                                    ->orderBy('name')

                                    ->get()

                                    ->mapWithKeys(fn (Permission $permission): array => [

                                        $permission->id => "{$permission->group} · {$permission->name}",

                                    ])

                                    ->all()

                            )

                            ->columns(2)

                            ->searchable()

                            ->bulkToggleable(),

                    ]),

            ]);

    }

}

