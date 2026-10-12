<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * صورة الحساب في لوحة الإدارة: الحرفان الأولان من الاسم في SVG يُولَّد محلياً (data:)، بدل
 * المزود الافتراضي ui-avatars.com الذي تصله أحرف أسماء المشرفين وهو خارج المملكة، ولا تسمح به
 * سياسة أمان المحتوى (docs/security/2026-10-10-security-audit.md).
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => mb_substr((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $segment), 0, 1))
            ->filter()
            ->take(2)
            ->join(' ');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#2E6898"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#FFFFFF" font-family="sans-serif" font-size="26">'
            .htmlspecialchars($initials, ENT_QUOTES | ENT_XML1, 'UTF-8')
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
