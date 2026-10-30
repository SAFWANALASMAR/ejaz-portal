<?php

namespace App\Filament\Resources\EvidenceGalleries\Pages;

use App\Actions\Evidence\SaveEvidenceGallery;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\EvidenceGalleries\EvidenceGalleryResource;
use App\Models\EvidenceGallery;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * إنشاء المعرض، ثم صفحة تعديله ومنها يُصدر الرابط.
 */
class CreateEvidenceGallery extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = EvidenceGalleryResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $gallery = new EvidenceGallery;

        return $this->saveThroughAction($gallery, [], fn (): EvidenceGallery => app(SaveEvidenceGallery::class)->handle($actor, $gallery, EvidenceGalleryResource::toAction($data)));
    }

    protected function getRedirectUrl(): string
    {
        return EvidenceGalleryResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
