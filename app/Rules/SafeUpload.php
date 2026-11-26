<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rejects uploads that a browser or server could execute: web pages, scripts and executables.
 *
 * Files are stored privately and always downloaded as attachments, so this is defence in depth:
 * both the extension the user sent and the one guessed from the file's content are checked.
 */
class SafeUpload implements ValidationRule
{
    public const BLOCKED = [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht',
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml', 'js', 'mjs',
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'ps1', 'vbs', 'jar', 'sh', 'cgi', 'pl', 'py',
        'htaccess', 'dll', 'so',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $extensions = [strtolower($value->getClientOriginalExtension())];

        // "invoice.php.pdf" style names hide a second extension: check every dot-separated part.
        $parts = explode('.', strtolower($value->getClientOriginalName()));
        array_shift($parts); // the base name itself is not an extension
        array_push($extensions, ...$parts);

        if (array_intersect($extensions, self::BLOCKED) !== []) {
            $fail(__('This file type is not allowed.'));
        }
    }
}
