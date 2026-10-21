<?php

namespace App\Enums;

enum CompetencyLevel: int
{
    case Awareness = 1;
    case Basic = 2;
    case Proficient = 3;
    case Expert = 4;

    public function label(): string
    {
        return match ($this) {
            self::Awareness => 'Awareness',
            self::Basic => 'Basic',
            self::Proficient => 'Proficient',
            self::Expert => 'Expert',
        };
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $level): array => ['value' => $level->value, 'label' => $level->label()],
            self::cases(),
        );
    }

    public static function labelFor(?int $level): ?string
    {
        return $level === null ? null : self::tryFrom($level)?->label();
    }
}
