<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'key', 'label', 'is_system',
        'subject', 'body', 'default_cc', 'default_bcc',
        'draft_subject', 'draft_body', 'draft_default_cc', 'draft_default_bcc',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public static function forKey(string $key): self
    {
        return static::where('key', $key)->firstOrFail();
    }

    public function isModified(): bool
    {
        return $this->draft_subject !== $this->subject
            || $this->draft_body !== $this->body
            || $this->draft_default_cc !== $this->default_cc
            || $this->draft_default_bcc !== $this->default_bcc;
    }

    public function publish(): void
    {
        $this->update([
            'subject' => $this->draft_subject,
            'body' => $this->draft_body,
            'default_cc' => $this->draft_default_cc,
            'default_bcc' => $this->draft_default_bcc,
        ]);
    }

    public function resetDraft(): void
    {
        $this->update([
            'draft_subject' => $this->subject,
            'draft_body' => $this->body,
            'draft_default_cc' => $this->default_cc,
            'draft_default_bcc' => $this->default_bcc,
        ]);
    }

    public function ccAddresses(): array
    {
        return $this->parseAddressList($this->default_cc);
    }

    public function bccAddresses(): array
    {
        return $this->parseAddressList($this->default_bcc);
    }

    private function parseAddressList(?string $list): array
    {
        if (blank($list)) {
            return [];
        }

        return collect(explode(',', $list))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->values()
            ->all();
    }
}
