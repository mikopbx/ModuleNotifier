<?php

declare(strict_types=1);

namespace Modules\ModuleNotifier\Lib;

final class IncomingNotificationGate
{
    private CdrNumberFilter $numberFilter;

    public function __construct(string $rawNumbers)
    {
        $this->numberFilter = new CdrNumberFilter($rawNumbers);
    }

    public function dispatch(callable $notification): void
    {
        if ($this->numberFilter->isActive()) {
            return;
        }

        $notification();
    }
}
