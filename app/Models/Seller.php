<?php

namespace App\Models;

use App\Services\SellerCodeGenerator;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Seller extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable;

    public const PLACEHOLDER = 'To be Added';

    protected $fillable = [
        'company_name', 'contact_person', 'phone', 'email', 'password',
        'business_address', 'gst_number', 'status', 'created_by',
        'rejection_reason', 'email_verified_at', 'approved_at', 'approved_by',
        'seller_code', 'manufacturing_activity', 'availability_hours', 'password_set_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'password_set_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Seller $seller) {
            $seller->seller_code ??= app(SellerCodeGenerator::class)->generate();
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function customAttributes(): MorphMany
    {
        return $this->morphMany(CustomAttribute::class, 'attributable');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'seller' && $this->isApproved();
    }

    public function getFilamentName(): string
    {
        return $this->contact_person ?: $this->company_name;
    }
}
