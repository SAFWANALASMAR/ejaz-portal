<?php

namespace App\Filament\Pages;

use App\Actions\Stats\RequestStatsAnalysis;
use App\Enums\StatsAnalysisStatus;
use App\Jobs\GenerateStatsAnalysis;
use App\Models\AssistantSettings;
use App\Models\StatsAnalysis;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * تحليل الإحصائيات بالذكاء الاصطناعي (إضافة صفوان، 19 أكتوبر 2026): زر «تحليل» لفترة، وآخر التقارير بنصها والأرقام
 * التي بناها الخادم بجانبها. لمن له stats.view متى شُغّل التحليل من إعدادات المساعد.
 */
class StatsAnalysisPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'stats-analysis';

    protected string $view = 'filament.pages.stats-analysis';

    public const SHOWN = 5;

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('stats.view') ?? false) && RequestStatsAnalysis::available(AssistantSettings::current());
    }

    public static function getNavigationLabel(): string
    {
        return __('analysis.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('analysis.title');
    }

    public function getSubheading(): ?string
    {
        return __('analysis.subheading');
    }

    protected function getHeaderActions(): array
    {
        $today = now((string) config('app.display_timezone'));

        return [
            Action::make('analyze')
                ->label(__('analysis.analyze'))
                ->icon(Heroicon::OutlinedSparkles)
                ->modalHeading(__('analysis.analyze_heading'))
                ->modalDescription(__('analysis.analyze_hint'))
                ->schema([
                    DatePicker::make('from')->label(__('analysis.from'))->required()->native(false)->default($today->copy()->subDays(29)->toDateString())->maxDate($today->toDateString()),
                    DatePicker::make('to')->label(__('analysis.to'))->required()->native(false)->default($today->toDateString())->maxDate($today->toDateString()),
                ])
                ->action(function (array $data, Action $action): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        app(RequestStatsAnalysis::class)->handle($user, [
                            'from' => substr((string) $data['from'], 0, 10),
                            'to' => substr((string) $data['to'], 0, 10),
                        ]);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                        $action->halt();
                    }

                    Notification::make()->success()->title(__('analysis.queued'))->send();
                }),
        ];
    }

    /**
     * آخر التقارير، وهل يُستطلع لتقرير حديث في الطابور. والمعلّق منذ أكثر من مهلته لا يُستطلع له، فلا تستطلع الصفحة بلا
     * نهاية، ويعلّمه «تعذّر» أمر مجدول لا العرض (ejaz:fail-stale-analyses). وفي بيانات القالب لا في دوال عامة يناديها
     * المتصفح.
     *
     * @return array{analyses: Collection<int, StatsAnalysis>, polling: bool}
     */
    protected function getViewData(): array
    {
        return [
            'analyses' => StatsAnalysis::query()->latest('id')->limit(self::SHOWN)->get(),
            'polling' => StatsAnalysis::query()->where('status', StatsAnalysisStatus::Queued->value)
                ->where('updated_at', '>=', now()->subMinutes(GenerateStatsAnalysis::STALE_MINUTES))->exists(),
        ];
    }
}
