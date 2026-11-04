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

        // بتدرج الهوية وحلقة داخلية #8EC5F0 خافتة، والحروف عريضة واضحة (ملاحظات صفوان على #166). والحلقة الخارجية من سمة اللوحة
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
            .'<stop offset="0" stop-color="#3E8ACA"/><stop offset=".55" stop-color="#2E6898"/><stop offset="1" stop-color="#142E45"/>'
            .'</linearGradient></defs>'
            .'<rect width="64" height="64" fill="url(#g)"/>'
            .'<circle cx="32" cy="32" r="29" fill="none" stroke="#8EC5F0" stroke-opacity=".55" stroke-width="1.5"/>'
            .'<text x="50%" y="50%" dy=".36em" text-anchor="middle" fill="#FFFFFF" font-family="Tahoma, \'Segoe UI\', Arial, sans-serif" font-weight="700" font-size="24">'
            .htmlspecialchars($initials, ENT_QUOTES | ENT_XML1, 'UTF-8')
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
