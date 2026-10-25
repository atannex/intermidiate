<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Support\Member;
use Atannex\Intermidiate\Support\Schema;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'presenter:list', description: 'List Presenters, the views they are bound to and the variables they export')]
final class ListCommand extends Command
{
    protected $signature = 'presenter:list
        {class? : Show one Presenter in full}
        {--json : Print JSON}';

    protected $description = 'List Presenters, the views they are bound to and the variables they export';

    public function handle(Intermidiate $intermidiate): int
    {
        $bindings = $intermidiate->bindings();

        foreach ($intermidiate->discovered() as $class => $views) {
            $bindings[$class] = array_values(array_unique([...($bindings[$class] ?? []), ...$views]));
        }

        if (($class = $this->argument('class')) !== null) {
            $class = $this->qualify((string) $class, array_keys($bindings));

            if ($class === null) {
                $this->components->error("No Presenter matches \"{$this->argument('class')}\".");

                return self::FAILURE;
            }

            $bindings = [$class => $bindings[$class] ?? Schema::of($class)->views];
        }

        $rows = [];

        foreach ($bindings as $class => $views) {
            $rows[$class] = [
                'views' => $views,
                'members' => array_map(fn (Member $member) => $member->describe(), Schema::of($class)->members),
            ];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->components->info('No Presenters are bound to views yet. Add #[ForView(\'name\')] or config intermidiate.bindings.');

            return self::SUCCESS;
        }

        foreach ($rows as $class => $row) {
            $this->newLine();
            $this->components->twoColumnDetail("<fg=green;options=bold>{$class}</>", implode(', ', $row['views']) ?: '<fg=gray>not bound</>');

            foreach ($row['members'] as $key => $type) {
                $this->components->twoColumnDetail('  $'.$key, "<fg=gray>{$type}</>");
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $known
     * @return class-string<Presenter>|null
     */
    private function qualify(string $class, array $known): ?string
    {
        if (is_subclass_of($class, Presenter::class)) {
            return $class;
        }

        foreach ($known as $candidate) {
            if (class_basename($candidate) === $class) {
                return $candidate;
            }
        }

        $guess = $this->laravel->getNamespace().'Presenters\\'.$class;

        return is_subclass_of($guess, Presenter::class) ? $guess : null;
    }
}
