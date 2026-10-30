<?php

namespace App\Policies;

use App\Models\EvidenceGallery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * معارض الشواهد (صلاحيات evidence_galleries.*). إصدار الرابط وتجديده تعديلٌ، وإلغاؤه صلاحية مستقلة.
 *
 * وفوقها أدوار تصنيف الأرشيف: معرض فيه عنصر من تصنيف لا يطّلع عليه الحساب لا يفتحه ولا يعدّله ولا يصدر رابطه ولا
 * يحذفه، فلا ينشر أحد برابط ما لا يراه هو (ArchiveItemPolicy). والإلغاء يبقى له، لأنه يقلّل الانكشاف ولا يزيده.
 */
class EvidenceGalleryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'evidence_galleries';
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allows($user, 'view') && $this->seesArchive($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allows($user, 'update') && $this->seesArchive($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allows($user, 'delete') && $this->seesArchive($user, $record);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->delete($user, $record);
    }

    public function revoke(User $user, Model $record): bool
    {
        return $this->allows($user, 'revoke');
    }

    private function seesArchive(User $user, Model $record): bool
    {
        /** @var EvidenceGallery $record */
        return ! $record->hasArchiveHiddenFrom($user);
    }
}
