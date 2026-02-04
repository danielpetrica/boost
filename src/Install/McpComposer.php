<?php

declare(strict_types=1);

namespace Laravel\Boost\Install;

use Illuminate\Support\Arr;
use Laravel\Boost\Support\Composer;

/**
 * Discovers and parses third-party MCP definitions from packages.
 *
 * Expected locations per package:
 *   resources/boost/mcp/mcp.json
 *
 * Supported formats inside mcp.json:
 *   1) Object map:
 *      { "servers": { "key": {"command":"...","args":[],"env":{}} } }
 *   2) Array with key property:
 *      { "servers": [ {"key":"name","command":"...","args":[],"env":{}} ] }
 */
class McpComposer
{
    /**
     * @return array<string, array<string,mixed>> Map of server key => config
     */
    public function collect(): array
    {
        $servers = [];

        foreach (Composer::packagesDirectoriesWithBoostMcp() as $package => $path) {
            $file = $path.DIRECTORY_SEPARATOR.'mcp.json';

            if (! file_exists($file)) {
                continue;
            }

            $data = json_decode((string) file_get_contents($file), true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
                continue;
            }

            $packageServers = $data['servers'] ?? null;

            if ($packageServers === null) {
                continue;
            }

            // Normalize to map of key => config
            if (is_array($packageServers)) {
                // Case A: object-map already
                $isAssoc = Arr::isAssoc($packageServers);

                if ($isAssoc) {
                    foreach ($packageServers as $key => $config) {
                        if (! is_string($key) || ! is_array($config)) {
                            continue;
                        }

                        $servers[$key] = $this->filterConfig($config);
                    }
                } else {
                    // Case B: list of entries with 'key'
                    foreach ($packageServers as $entry) {
                        if (! is_array($entry)) {
                            continue;
                        }

                        $key = $entry['key'] ?? null;
                        if (! is_string($key) || $key === '') {
                            continue;
                        }

                        unset($entry['key']);
                        $servers[$key] = $this->filterConfig($entry);
                    }
                }
            }
        }

        return $servers;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    protected function filterConfig(array $config): array
    {
        // Only keep known keys and drop empties
        $allowed = [
            'command' => null,
            'args' => null,
            'env' => null,
        ];

        $filtered = [];

        foreach ($allowed as $k => $_) {
            if (array_key_exists($k, $config)) {
                $value = $config[$k];

                if ($k === 'args' && is_array($value)) {
                    $value = array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
                }

                if ($value !== null && $value !== [] && $value !== '') {
                    $filtered[$k] = $value;
                }
            }
        }

        return $filtered;
    }
}
