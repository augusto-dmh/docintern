<?php

namespace App\Models;

use App\Policies\ClientPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[UsePolicy(ClientPolicy::class)]
class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'company',
        'notes',
    ];

    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }
}
