<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Atannex\Intermidiate\Intermidiate;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'view-model:clear', description: 'Remove the ViewModel discovery cache')]
final class ClearCommand extends Command
{
    protected $signature = 'view-model:clear';

    protected $description = 'Remove the ViewModel discovery cache';

    public function handle(Intermidiate $intermidiate): int
    {
        $intermidiate->clearCache();

        $this->components->info('ViewModel discovery cache cleared.');

        return self::SUCCESS;
    }
}
