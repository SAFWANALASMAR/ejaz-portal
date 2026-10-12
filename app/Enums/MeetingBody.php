<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * الجهة صاحبة الاجتماع (requirements/13 §2): الجمعية العمومية، أو مجلس الإدارة، أو لجنة باسمها.
 */
enum MeetingBody: string implements HasLabel
{
    case GeneralAssembly = 'general_assembly';
    case Board = 'board';
    case Committee = 'committee';

    public function getLabel(): string
    {
        return __('registers.bodies.'.$this->value);
    }

    /**
     * الجهات التي لها سجل عضوية في هذه الوحدة. وعضوية الجمعية العمومية في سجلها (assembly_memberships).
     *
     * @return list<self>
     */
    public static function membershipBodies(): array
    {
        return [self::Board, self::Committee];
    }
}
