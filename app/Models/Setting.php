<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'site_name', 'logo_path',
        'footer_copyright', 'footer_address', 'footer_phone', 'footer_email',
        'social_facebook', 'social_twitter', 'social_linkedin', 'social_instagram', 'social_youtube',
    ];

    /**
     * The social platforms the footer can render, in display order. The value is
     * the settings column holding that platform's URL.
     */
    public const SOCIAL_PLATFORMS = [
        'facebook' => 'social_facebook',
        'twitter' => 'social_twitter',
        'linkedin' => 'social_linkedin',
        'instagram' => 'social_instagram',
        'youtube' => 'social_youtube',
    ];

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], ['site_name' => config('app.name')]);
    }

    /**
     * The copyright line with a `{year}` placeholder resolved to the current year.
     */
    public function copyrightText(): ?string
    {
        if (blank($this->footer_copyright)) {
            return null;
        }

        return str_replace('{year}', (string) now()->year, $this->footer_copyright);
    }

    /**
     * Configured social links as `[platform => url]`, omitting any that are blank.
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $links = [];

        foreach (self::SOCIAL_PLATFORMS as $platform => $column) {
            if (filled($this->{$column})) {
                $links[$platform] = $this->{$column};
            }
        }

        return $links;
    }
}
