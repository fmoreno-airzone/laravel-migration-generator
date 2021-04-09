<?php

namespace LaravelMigrationGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use LaravelMigrationGenerator\GeneratorManagers\MySQLGeneratorManager;
use LaravelMigrationGenerator\GeneratorManagers\GeneratorManagerInterface;

class MigrationsGenerationCommand extends Command
{
    protected $signature = 'migrate:generate {path} {--table=} {--connection=default}';

    public function getConnection()
    {
        $connection = $this->option('connection');

        if ($connection === 'default') {
            $connection = Config::get('database.default');
        }

        if (! Config::has('database.connections.' . $connection)) {
            throw new \Exception('Could not find connection `' . $connection . '` in your config.');
        }

        return $connection;
    }

    public function handle()
    {
        $basePath = $this->argument('path');

        try {
            $connection = $this->getConnection();
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info('Using connection ' . $connection);
        DB::setDefaultConnection($connection);

        $driver = Config::get('database.connections.' . $connection)['driver'];

        $manager = $this->resolveGeneratorManager($driver);
        if ($manager === false) {
            $this->error('The `' . $driver . '` driver is not supported at this time.');

            return 1;
        }

        $singleTableName = $this->option('table');

        $manager->handle($basePath, $singleTableName);
    }

    /**
     * @param string $driver
     * @return false|GeneratorManagerInterface
     */
    protected function resolveGeneratorManager(string $driver)
    {
        $supported = [
            'mysql' => MySQLGeneratorManager::class
        ];

        if (! isset($supported[$driver])) {
            return false;
        }

        return new $supported[$driver]();
    }
}