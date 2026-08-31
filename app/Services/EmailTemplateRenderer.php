<?php

namespace App\Services;

use App\Models\Setting;

class EmailTemplateRenderer
{
    public function render(string $template, array $tokens = [], bool $escapeHtml = true): string
    {
        $tokens = array_merge(
            ['site_name' => Setting::current()->site_name],
            $tokens,
        );

        $template = preg_replace_callback(
            '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
            function (array $matches) use ($tokens) {
                $value = $tokens[$matches[1]] ?? null;

                return filled($value) ? $matches[2] : '';
            },
            $template
        );

        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            function (array $matches) use ($tokens, $escapeHtml) {
                $key = $matches[1];

                if (! array_key_exists($key, $tokens)) {
                    return $matches[0];
                }

                $value = $tokens[$key];

                if (! is_scalar($value) && $value !== null) {
                    return $matches[0];
                }

                $value = (string) $value;

                if (str_ends_with($key, '_html') || ! $escapeHtml) {
                    return $value;
                }

                return e($value);
            },
            $template
        );
    }
}
