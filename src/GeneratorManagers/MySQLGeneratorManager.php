<?php

namespace LaravelMigrationGenerator\GeneratorManagers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use LaravelMigrationGenerator\Definitions\TableDefinition;
use LaravelMigrationGenerator\Generators\MySQL\ViewGenerator;
use LaravelMigrationGenerator\Generators\MySQL\TableGenerator;
use LaravelMigrationGenerator\GeneratorManagers\Interfaces\GeneratorManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class MySQLGeneratorManager extends BaseGeneratorManager implements GeneratorManagerInterface
{
    public static function driver(): string
    {
        return 'mysql';
    }

    public function init()
    {
        $tableSelectors = \config('laravel-migration-generator.table_selector');

        // we will let only this implementation for now
        $skipLike = $tableSelectors['skip_like'];

        $query = DB::query()
          ->select('table_name', 'Table_type')
          ->from('information_schema.tables')
          ->where('table_schema', $this->schema);

        foreach($skipLike as $skipLikeTable) {
            $query->whereRaw("lower(TABLE_NAME) NOT LIKE '$skipLikeTable'");
        }

        $tablesToGenerate = $query->get()->toArray();
        
        foreach ($tablesToGenerate as $table) {

            $tableData = (array) $table;
            $table = $tableData[array_key_first($tableData)];

            $tableType = $tableData['Table_type'];
            if ($tableType === 'BASE TABLE') {
                $this->addTableDefinition(TableGenerator::init($table)->definition());
            } elseif ($tableType === 'VIEW') {
                $this->addViewDefinition(ViewGenerator::init($table)->definition());
            }
        }
        (new ConsoleOutput())->writeln("Loaded " . count($tablesToGenerate) . " tables.");
    }

    public function addTableDefinition(TableDefinition $tableDefinition): BaseGeneratorManager
    {
        $prefix = config('database.connections.' . DB::getDefaultConnection() . '.prefix', '');
        if (! empty($prefix) && Str::startsWith($tableDefinition->getTableName(), $prefix)) {
            $tableDefinition->setTableName(Str::replaceFirst($prefix, '', $tableDefinition->getTableName()));
        }

        return parent::addTableDefinition($tableDefinition);
    }
}
