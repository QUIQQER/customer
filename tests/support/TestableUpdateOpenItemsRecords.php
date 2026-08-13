<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use QUI\ERP\Customer\Console\UpdateOpenItemsRecords;

final class TestableUpdateOpenItemsRecords extends UpdateOpenItemsRecords
{
    /** @var list<string> */
    public array $output = [];
    public bool $success = false;
    public ?string $failure = null;

    public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
    {
        $this->output[] = $msg;
    }

    protected function exitSuccess(): void
    {
        $this->success = true;
    }

    protected function exitFail(string $msg): void
    {
        $this->failure = $msg;
    }
}
