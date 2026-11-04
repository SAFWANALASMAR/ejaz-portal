<?php

namespace App\Rules;

use App\Enums\SocialPlatform;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رابط المنشور الأصلي: https خارجي آمن (SafeUrl)، ومضيفه نطاق المنصة المختارة أو نطاق فرعي منه (SocialPlatform::hosts)،
 * بحروف النطاق اللاتينية وحدها كقاعدة رابط المتجر (StoreUrl)، ولا صفحة تحويل من المنصة إلى خارجها. وبلا منصة صحيحة يُرفض.
 *
 * ويُسوّى الرابط قبل فحصه وحفظه، فلا يتكرر المنشور بنسخه من التطبيق مرة أخرى: المضيف بأحرف صغيرة بلا www. ولا m.، وتويتر
 * إكس، بلا الشرطة الأخيرة ولا ما بعد # ولا معاملات التتبع، وتبقى معاملات تعريف المنشور وحدها (v في يوتيوب، ومعرّفا فيسبوك).
 */
class SocialPostUrl implements ValidationRule
{
    public const MAX_LENGTH = 500;

    /**
     * معاملات الاستعلام التي تعرّف المنشور، وما سواها تتبع يُسقط.
     */
    private const KEPT_QUERY = ['v', 'id', 'story_fbid', 'fbid'];

    /**
     * صفحات التحويل من المنصة إلى موقع آخر (l.facebook.com/l.php، youtube.com/redirect، linkedin.com/redir/…).
     */
    private const REDIRECT_HOSTS = '/^(l|lm)\./';

    private const REDIRECT_PATHS = '#^/(redirect|redir/|l\.php|linkredirect|safety/go|flx/warn|attribution_link)#i';

    public function __construct(private ?SocialPlatform $platform) {}

    public static function normalize(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        // ما لا يُفهم أو فيه بيانات مستخدم يبقى كما هو، فيرفضه الفحص
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return $url;
        }

        $host = (string) preg_replace('/^(www|m|mobile)\./', '', strtolower($parts['host']));
        $host = $host === 'twitter.com' ? 'x.com' : $host;

        parse_str($parts['query'] ?? '', $query);
        $query = array_intersect_key($query, array_flip(self::KEPT_QUERY));
        ksort($query);

        return strtolower($parts['scheme']).'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '')
            .rtrim($parts['path'] ?? '', '/')
            .($query === [] ? '' : '?'.http_build_query($query));
    }

    public static function matches(mixed $url, ?SocialPlatform $platform): bool
    {
        if ($platform === null || ! is_string($url) || strlen($url) > self::MAX_LENGTH || ! str_starts_with($url, 'https://') || ! SafeUrl::isSafe($url, allowInternal: false)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // لا ترميز نسبة ولا حروف خارج النطاق، فلا يُلتف على المقارنة (مثل evil.com%2F.x.com)
        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) !== 1) {
            return false;
        }

        // لا منفذ: رابط المنشور على منفذ المنصة المعتاد وحده
        if (parse_url($url, PHP_URL_PORT) !== null) {
            return false;
        }

        // صفحة التحويل تُعرف بمسارها بعد فك الترميز ودمج الشرطات، فلا يُلتف عليها بـ //redirect أو %72edirect
        $path = (string) preg_replace('#/+#', '/', rawurldecode((string) parse_url($url, PHP_URL_PATH)));

        if (preg_match(self::REDIRECT_HOSTS, $host) === 1 || preg_match(self::REDIRECT_PATHS, $path) === 1) {
            return false;
        }

        foreach ($platform->hosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::matches(is_string($value) ? self::normalize($value) : $value, $this->platform)) {
            $fail(__('social_posts.validation.url', ['hosts' => implode('، ', $this->platform?->hosts() ?? [])]));
        }
    }
}
