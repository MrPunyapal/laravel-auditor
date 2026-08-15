<?php

declare(strict_types=1);

namespace LaravelAuditor\Support\Agents;

use Illuminate\Filesystem\Filesystem;

/**
 * Detects which supported agents are used in the current project.
 *
 * Detection is based on the marker files and directories each agent leaves
 * behind, so projects get sensible defaults when the installer runs
 * non-interactively.
 */
final class AgentDetector
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return list<string>
     */
    public function detect(string $basePath): array
    {
        $found = [];

        foreach (AgentRegistry::all() as $agent) {
            if ($this->detected($agent, $basePath)) {
                $found[] = $agent->name;
            }
        }

        return $found;
    }

    private function detected(Agent $agent, string $basePath): bool
    {
        foreach ($agent->detectFiles as $file) {
            if ($this->files->exists($basePath.DIRECTORY_SEPARATOR.$file)) {
                return true;
            }
        }

        foreach ($agent->detectPaths as $path) {
            if ($this->files->isDirectory($basePath.DIRECTORY_SEPARATOR.$path)) {
                return true;
            }
        }

        return false;
    }
}
