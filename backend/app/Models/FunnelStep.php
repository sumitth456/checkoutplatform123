<?php



namespace App\Models;



use App\Modules\Order\Enums\FunnelStepType;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Support\Str;



class FunnelStep extends Model

{

    protected $fillable = [

        'uuid',

        'funnel_id',

        'step_order',

        'name',

        'type',

        'product_id',

        'variant_id',

        'next_step_id',

        'is_required',

        'is_active',

        'settings',

        'metadata',

    ];



    protected function casts(): array

    {

        return [

            'type' => FunnelStepType::class,

            'is_required' => 'boolean',

            'is_active' => 'boolean',

            'settings' => 'array',

            'metadata' => 'array',

        ];

    }



    protected static function booted(): void

    {

        static::creating(function (FunnelStep $step): void {

            if (blank($step->uuid)) {

                $step->uuid = (string) Str::uuid();

            }

        });

    }



    public function funnel(): BelongsTo

    {

        return $this->belongsTo(Funnel::class);

    }



    public function product(): BelongsTo

    {

        return $this->belongsTo(Product::class);

    }



    public function variant(): BelongsTo

    {

        return $this->belongsTo(Variant::class);

    }



    public function nextStep(): BelongsTo

    {

        return $this->belongsTo(self::class, 'next_step_id');

    }

}

