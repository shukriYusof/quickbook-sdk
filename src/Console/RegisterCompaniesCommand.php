<?php

namespace QuickBooks\SDK\Console;

use Illuminate\Console\Command;

class RegisterCompaniesCommand extends Command
{
    protected $signature = 'qb:register {model? : Source model class} {--tenant= : Tenant ID} {--id= : Register a single record by ID} {--label= : Label column name}';

    protected $description = 'Register source models into quickbooks_companies';

    public function handle(): int
    {
        $modelClass = $this->argument('model') ?: config('quickbooks.company_model.model');

        if (!$modelClass || !class_exists($modelClass)) {
            $this->error('Source model class not found.');
            return self::FAILURE;
        }

        $bridgeClass = config('quickbooks.bridge_model');
        if (!$bridgeClass || !class_exists($bridgeClass)) {
            $this->error('Bridge model class not found.');
            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');
        $labelColumn = $this->option('label') ?: config('quickbooks.company_model.label_column', 'name');
        $idFilter = $this->option('id');

        $model = new $modelClass();
        $query = $model->newQuery();

        if ($idFilter !== null) {
            $query->where($model->getKeyName(), $idFilter);
        }

        $conditions = config('quickbooks.company_model.conditions', []);
        if (is_array($conditions)) {
            foreach ($conditions as $column => $value) {
                $query->where($column, $value);
            }
        }

        $count = 0;
        foreach ($query->cursor() as $record) {
            $bridgeClass::registerSource($record, $tenantId, $labelColumn);
            $count++;
        }

        $this->info("Registered {$count} source record(s).");

        return self::SUCCESS;
    }
}
