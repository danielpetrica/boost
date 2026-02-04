<?php

declare(strict_types=1);

namespace Laravel\Boost\Install;

use Laravel\Boost\Contracts\SupportsMcp;
use RuntimeException;

class McpWriter
{
    public const SUCCESS = 0;

    public function __construct(protected SupportsMcp $agent)
    {
        //
    }

    public function write(?Sail $sail = null, ?Herd $herd = null): int
    {
        $this->installBoostMcp($sail);

        $this->installThirdPartyMcpServers();

        if ($herd instanceof Herd) {
            $this->installHerdMcp($herd);
        }

        return self::SUCCESS;
    }

    protected function installBoostMcp(?Sail $sail): void
    {
        $mcp = $this->buildBoostMcpCommand($sail);

        if (! $this->agent->installMcp($mcp['key'], $mcp['command'], $mcp['args'])) {
            throw new RuntimeException('Failed to install Boost MCP: could not write configuration');
        }
    }

    /**
     * @return array{key: string, command: string, args: array<int, string>}
     */
    protected function buildBoostMcpCommand(?Sail $sail): array
    {
        if ($sail instanceof Sail) {
            return $sail->buildMcpCommand('laravel-boost');
        }

        if ($this->isRunningInsideWsl()) {
            return [
                'key' => 'laravel-boost',
                'command' => 'wsl.exe',
                'args' => [$this->agent->getPhpPath(true), $this->agent->getArtisanPath(true), 'boost:mcp'],
            ];
        }

        return [
            'key' => 'laravel-boost',
            'command' => $this->agent->getPhpPath(),
            'args' => [$this->agent->getArtisanPath(), 'boost:mcp'],
        ];
    }

    private function isRunningInsideWsl(): bool
    {
        return ! empty(getenv('WSL_DISTRO_NAME')) || ! empty(getenv('IS_WSL'));
    }

    protected function installHerdMcp(Herd $herd): void
    {
        $installed = $this->agent->installMcp(
            key: 'herd',
            command: $this->agent->getPhpPath(),
            args: [$herd->mcpPath()],
            env: ['SITE_PATH' => base_path()]
        );

        if (! $installed) {
            throw new RuntimeException('Failed to install Herd MCP: could not write configuration');
        }
    }

    protected function installThirdPartyMcpServers(): void
    {
        $composer = new McpComposer();
        $servers = $composer->collect();

        foreach ($servers as $key => $config) {
            $command = $config['command'] ?? null;
            $args = $config['args'] ?? [];
            $env = $config['env'] ?? [];

            if (! is_string($command) || $command === '') {
                // Skip invalid definitions
                continue;
            }

            $installed = $this->agent->installMcp(
                key: $key,
                command: $command,
                args: is_array($args) ? $args : [],
                env: is_array($env) ? $env : []
            );

            if (! $installed) {
                throw new RuntimeException("Failed to install MCP server '{$key}': could not write configuration");
            }
        }
    }
}
