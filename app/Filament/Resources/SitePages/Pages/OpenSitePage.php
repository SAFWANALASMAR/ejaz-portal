<?php

namespace App\Filament\Resources\SitePages\Pages;

use App\Actions\Content\PageEditor\EnsureEditablePage;
use App\Filament\Resources\SitePages\SitePageResource;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * وجهة زر «تعديل هذه الصفحة» في الموقع: مفتاح الصفحة من السجل (EditablePages)، فيُنشأ سجلها إن لم يُفتح بعد، ثم تُفتح شاشتها.
 * ومفتاح ليس في السجل 404، ومن لا صلاحية له 403.
 */
class OpenSitePage extends Page
{
    protected static string $resource = SitePageResource::class;

    protected string $view = 'filament.site-pages.open';

    public function mount(string $key): void
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            $page = app(EnsureEditablePage::class)->handle($actor, $key);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->redirect(SitePageResource::getUrl('edit', ['record' => $page]));
    }
}
