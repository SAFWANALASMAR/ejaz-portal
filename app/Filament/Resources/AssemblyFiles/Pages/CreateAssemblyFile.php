<?php

namespace App\Filament\Resources\AssemblyFiles\Pages;

use App\Actions\Assembly\SaveAssemblyFile;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AssemblyFiles\AssemblyFileResource;
use App\Models\AssemblyFile;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAssemblyFile extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = AssemblyFileResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $file = new AssemblyFile;

        return $this->saveThroughAction($file, ['file'], fn (): AssemblyFile => app(SaveAssemblyFile::class)->handle($actor, $file, $data));
    }
}
