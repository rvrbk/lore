<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('lore:import-sqlite {path : Path to the SQLite database to copy from}')]
#[Description('Copy every row from a SQLite database into the (already migrated, empty) default database')]
class ImportSqlite extends Command
{
    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("No such file: {$path}");

            return self::FAILURE;
        }

        config(['database.connections.import_source' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => false]]);
        $source = DB::connection('import_source');
        $target = DB::connection();

        if ($target->getDriverName() === 'sqlite') {
            $this->error('The default connection is SQLite; point DB_CONNECTION at the target database first.');

            return self::FAILURE;
        }

        $tables = $this->orderedTables($source->getSchemaBuilder());
        $filled = $tables->filter(fn (string $table) => $target->table($table)->exists());
        if ($filled->isNotEmpty()) {
            $this->error('Target tables already contain rows: '.$filled->implode(', ').'. Refusing to import.');

            return self::FAILURE;
        }

        $target->transaction(function () use ($tables, $source, $target): void {
            foreach ($tables as $table) {
                $booleans = collect(Schema::getColumns($table))->where('type_name', 'bool')->pluck('name')->all();
                $copied = 0;

                $source->table($table)->orderByRaw('rowid')->chunk(500, function (Collection $rows) use ($table, $booleans, $target, &$copied): void {
                    $target->table($table)->insert($rows->map(function (object $row) use ($booleans): array {
                        $values = (array) $row;
                        foreach ($booleans as $column) {
                            $values[$column] = $values[$column] === null ? null : (bool) $values[$column];
                        }

                        return $values;
                    })->all());
                    $copied += $rows->count();
                });

                if ($target->getDriverName() === 'pgsql' && collect(Schema::getColumns($table))->contains(fn (array $c) => $c['name'] === 'id' && $c['auto_increment'])) {
                    $target->statement("select setval(pg_get_serial_sequence('\"{$table}\"', 'id'), coalesce(max(id), 1), max(id) is not null) from \"{$table}\"");
                }

                $this->line(str_pad($table, 20).$copied.' rows');
            }
        });

        $this->info('Import finished.');

        return self::SUCCESS;
    }

    /**
     * Tables in an order that satisfies foreign keys (parents first), skipping the migrations table.
     *
     * @return Collection<int, string>
     */
    private function orderedTables(Builder $schema): Collection
    {
        $dependencies = collect($schema->getTables())->pluck('name')
            ->reject(fn (string $name) => in_array($name, ['migrations', 'sqlite_sequence'], true))
            ->mapWithKeys(fn (string $name) => [$name => collect($schema->getForeignKeys($name))->pluck('foreign_table')->reject(fn ($t) => $t === $name)->unique()->all()]);

        $ordered = collect();
        while ($dependencies->isNotEmpty()) {
            $ready = $dependencies->filter(fn (array $needs) => collect($needs)->every(fn (string $t) => $ordered->contains($t) || ! $dependencies->has($t)));
            if ($ready->isEmpty()) {
                $ready = $dependencies->take(1);
            }
            $ordered = $ordered->merge($ready->keys());
            $dependencies = $dependencies->except($ready->keys()->all());
        }

        return $ordered->values();
    }
}
