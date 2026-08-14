<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use DateTimeZone;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Support\ApplicationPaths;
use Throwable;

/**
 * Collects context about jobs, events, listeners, and scheduled commands.
 */
final class JobsEventsSchedulesCollector implements ContextCollector
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Dispatcher $events,
        private readonly ApplicationPaths $paths,
    ) {}

    public function name(): string
    {
        return 'jobs_events_schedules';
    }

    public function description(): string
    {
        return 'List queued jobs, events/listeners, and scheduled commands.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'jobs' => $this->filesIn($this->paths->directories('Jobs')),
            'events' => $this->filesIn($this->paths->directories('Events')),
            'listeners' => $this->filesIn($this->paths->directories('Listeners')),
            'registered_events' => $this->registeredEvents(),
            'scheduled_commands' => $this->scheduledCommands(),
        ];
    }

    /**
     * @param  list<string>  $directories
     * @return list<string>
     */
    private function filesIn(array $directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            if (! $this->files->isDirectory($directory)) {
                continue;
            }

            foreach ($this->files->allFiles($directory) as $file) {
                $files[] = $file->getRelativePathname();
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * @return array<string, list<string>>
     */
    private function registeredEvents(): array
    {
        $events = [];

        foreach ($this->events->getRawListeners() as $event => $listeners) {
            $resolved = [];

            foreach ((array) $listeners as $listener) {
                if (is_string($listener)) {
                    $resolved[] = $listener;
                } elseif (is_array($listener) && is_string($listener[0] ?? null)) {
                    $resolved[] = $listener[0].'@'.($listener[1] ?? 'handle');
                } else {
                    $resolved[] = 'closure';
                }
            }

            $events[(string) $event] = $resolved;
        }

        ksort($events);

        return $events;
    }

    /**
     * @return list<array{expression: string, command: string, timezone: string|null}>
     */
    private function scheduledCommands(): array
    {
        $commands = [];

        $scheduler = $this->scheduler();

        if ($scheduler === null) {
            return $commands;
        }

        foreach ($scheduler->events() as $event) {
            $expression = (string) $event->expression;

            $command = $event->command;

            if ($command === null) {
                $command = 'closure';
            } else {
                $command = trim((string) preg_replace('/\s+--[^\s]+/', '', $command));
            }

            $timezone = $event->timezone;

            $commands[] = [
                'expression' => $expression,
                'command' => $command,
                'timezone' => $timezone instanceof DateTimeZone ? $timezone->getName() : $timezone,
            ];
        }

        return $commands;
    }

    private function scheduler(): ?Schedule
    {
        try {
            return app(Schedule::class);
        } catch (Throwable) {
            // The schedule may be unavailable outside a full console context.
        }

        return null;
    }
}
