<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use LaravelAuditor\Context\ContextRegistry;
use Throwable;

/**
 * Dumps a named context collector as structured JSON.
 *
 * Agents without MCP can still collect deterministic Laravel facts.
 */
class AuditorContextCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'auditor:context
        {collector? : Collector name (project_info, routes, models, ...)}
        {--list : List the available collectors}
        {--output= : Write JSON to a file instead of stdout}';

    /**
     * The command description.
     */
    protected $description = 'Dump structured Laravel context for an audit.';

    public function __construct(
        private readonly ContextRegistry $context,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $list = (bool) $this->option('list');
        $collector = $this->argument('collector');

        if ($list || ! is_string($collector) || $collector === '') {
            return $this->listCollectors();
        }

        try {
            $data = $this->context->get($collector)->collect();
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            $this->components->info('Available: '.implode(', ', $this->context->names()));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error('Failed to collect ['.$collector.']: '.$e->getMessage());

            return self::FAILURE;
        }

        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents($output, $json.PHP_EOL);
            $this->components->info("Context written to [{$output}].");

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }

    private function listCollectors(): int
    {
        $this->table(
            ['Collector', 'Description'],
            array_map(
                fn (string $name): array => [$name, $this->context->get($name)->description()],
                $this->context->names(),
            ),
        );

        return self::SUCCESS;
    }
}
