<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Atannex\Intermidiate\Intermidiate;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'presenter:clear', description: 'Remove the Presenter discovery cache')]
final class ClearCommand extends Command
{
    protected $signature = 'presenter:clear';

    protected $description = 'Remove the Presenter discovery cache';

    public function handle(Intermidiate $intermidiate): int
    {
        $intermidiate->clearCache();

        $this->components->info('Presenter discovery cache cleared.');

        return self::SUCCESS;
    }
}
