<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Atannex\Intermidiate\Intermidiate;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'presenter:cache', description: 'Cache the discovered Presenter bindings')]
final class CacheCommand extends Command
{
    protected $signature = 'presenter:cache';

    protected $description = 'Cache the discovered Presenter bindings';

    public function handle(Intermidiate $intermidiate): int
    {
        $found = $intermidiate->cache();

        $this->components->info(sprintf('Cached %d Presenter %s.', count($found), count($found) === 1 ? 'binding' : 'bindings'));

        return self::SUCCESS;
    }
}
