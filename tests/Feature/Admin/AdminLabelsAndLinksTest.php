<?php

use App\Enums\RoleName;
use App\Filament\Resources\Albums\Pages\ListAlbums;
use App\Filament\Resources\BoardMembers\Pages\ListBoardMembers;
use App\Filament\Resources\Initiatives\Pages\ListInitiatives;
use App\Filament\Resources\LibraryItems\Pages\ListLibraryItems;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Resources\NewsArticles\NewsArticleResource;
use App\Filament\Resources\NewsArticles\Pages\ListNewsArticles;
use App\Filament\Resources\Openings\Pages\CreateOpening;
use App\Filament\Resources\Partners\Pages\ListPartners;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Models\NewsArticle;
use App\Models\ProgramType;
use Filament\Tables\Columns\ImageColumn;
use Livewire\Livewire;

/*
| ملاحظات التجربة الشاملة (18 أكتوبر 2026) في لوحة الإدارة: زر إضافة الحقول في المكرّر بالعربية (الخلل 6)،
| ولا رابط بلا اسم في عمود الصورة بالجداول (الخلل 9).
*/

it('labels the add button of the form fields repeater in Arabic', function (string $page, RoleName $role) {
    ProgramType::factory()->create();
    $this->actingAs(userWithRole($role));

    Livewire::test($page)
        ->assertSee(__('services.openings.fields.add_field'))
        ->assertDontSee('form fields');
})->with([
    'programs' => [CreateProgram::class, RoleName::ProgramManager],
    'openings' => [CreateOpening::class, RoleName::Secretary],
]);

it('leaves the image cell of the news table unlinked, so no link is left without a name', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));
    $article = NewsArticle::factory()->create(['title' => 'خبر للاختبار']);

    Livewire::test(ListNewsArticles::class)
        ->assertTableColumnExists('image', fn (ImageColumn $column): bool => $column->isClickDisabled())
        ->assertTableColumnExists('title', fn ($column): bool => ! $column->isClickDisabled());

    $html = (string) $this->get(NewsArticleResource::getUrl('index'))->assertOk()->getContent();
    $edit = preg_quote(NewsArticleResource::getUrl('edit', ['record' => $article]), '#');

    // كل رابط إلى صفحة التعديل فيه نص يقرؤه قارئ الشاشة
    preg_match_all('#<a\b[^>]*href="'.$edit.'"[^>]*>(.*?)</a>#su', $html, $links);

    expect($links[1])->not->toBeEmpty()
        ->each(fn ($content) => $content->and(trim(strip_tags($content->value)))->not->toBe(''));
});

it('leaves the image cells of the other admin tables unlinked too', function (string $page, string $column) {
    $this->actingAs(userWithRole(RoleName::SuperAdmin));

    Livewire::test($page)->assertTableColumnExists($column, fn (ImageColumn $image): bool => $image->isClickDisabled());
})->with([
    'albums' => [ListAlbums::class, 'cover'],
    'board members' => [ListBoardMembers::class, 'photo'],
    'initiatives' => [ListInitiatives::class, 'image'],
    'library' => [ListLibraryItems::class, 'thumbnail'],
    'media library' => [ListMediaAssets::class, 'web_path'],
    'partners' => [ListPartners::class, 'logo'],
]);
