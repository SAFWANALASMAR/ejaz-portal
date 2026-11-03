<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع برامج المكتبة (قرار صفوان، 31 أكتوبر 19:33 — docs/decisions/2026-11-02-reading-program.md): قيمة كل نوع هي معرّف نوع
 * البرنامج (program_types.slug) الذي تنشئه ProgramTypesSeeder، فيُعرف البرنامج من نوعه بلا عمود جديد. ولكل نوع أقسام المكتبة
 * التي يُختار منها عنصره.
 */
enum LibraryProgramKind: string implements HasLabel
{
    case Reading = 'book-reading';
    case Listening = 'audio-listening';
    case Watching = 'video-watching';

    public function getLabel(): string
    {
        return __('programs.library.kinds.'.$this->value);
    }

    /**
     * @return list<LibrarySection>
     */
    public function sections(): array
    {
        return match ($this) {
            self::Reading => [LibrarySection::Books, LibrarySection::Research],
            self::Listening => [LibrarySection::Audio],
            self::Watching => [LibrarySection::Videos],
        };
    }

    /**
     * نص زر البدء وزر الإتمام في صفحة البرنامج.
     */
    public function startLabel(): string
    {
        return __('programs.library.start.'.$this->value);
    }

    public function completeLabel(): string
    {
        return __('programs.library.complete.'.$this->value);
    }
}
