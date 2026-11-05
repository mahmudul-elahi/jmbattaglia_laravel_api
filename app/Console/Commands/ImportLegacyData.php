<?php

namespace App\Console\Commands;

use App\Services\LegacyImportService;
use Illuminate\Console\Command;

class ImportLegacyData extends Command
{
    protected $signature = 'portal:import {file : JSON export keyed by Prisma model name} {--dry-run : Validate and roll back all writes}';

    protected $description = 'Import a source export into integer-ID tables without replaying credentials or remote writes';

    public function handle(LegacyImportService $importer): int
    {
        $file = $this->argument('file');
        if (! is_file($file) || ! is_readable($file)) {
            $this->error('Import file is not readable.');

            return self::FAILURE;
        }
        try {
            $input = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                throw new \InvalidArgumentException('Expected a JSON object keyed by Prisma model name.');
            }
            $counts = $importer->import($input, (bool) $this->option('dry-run'));
            $this->table(['Entity', 'Imported'], collect($counts)->map(fn ($count, $entity) => [$entity, $count])->values()->all());

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Import failed. All changes were rolled back. Verify source references and the target schema.');

            return self::FAILURE;
        }
    }
}
