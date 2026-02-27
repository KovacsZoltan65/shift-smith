<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyUser extends Model
{
    use HasFactory;

    protected $table = 'company_user';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'company_id' => 'int',
        'user_id' => 'int',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
