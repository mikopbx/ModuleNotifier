<?php

declare(strict_types=1);

namespace Modules\ModuleNotifier\Lib;

final class CdrNumberFilter
{
    /** @var array<string, bool> */
    private array $numbers = [];

    public function __construct(string $rawNumbers)
    {
        $tokens = preg_split('/\s+/', trim($rawNumbers)) ?: [];
        foreach ($tokens as $token) {
            $number = self::normalize($token);
            if ($number !== '') {
                $this->numbers[$number] = true;
            }
        }
    }

    public function allows(array $cdrGroup): bool
    {
        if ($this->numbers === []) {
            return true;
        }

        foreach ($cdrGroup['rows'] ?? [] as $row) {
            foreach (['src_num', 'dst_num'] as $field) {
                $number = self::normalize((string)($row[$field] ?? ''));
                if ($number !== '' && isset($this->numbers[$number])) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function normalize(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }
}
