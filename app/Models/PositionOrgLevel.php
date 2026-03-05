<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionOrgLevel extends Model
{
    use HasFactory;

    protected $table = 'position_org_levels';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'position_key',
        'position_label',
        'org_level',
        'active',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'company_id' => 'int',
        'active' => 'bool',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

