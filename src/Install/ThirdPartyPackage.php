<?php

declare(strict_types=1);

namespace Laravel\Boost\Install;

use Illuminate\Support\Collection;
use Laravel\Boost\Support\Composer;

class ThirdPartyPackage
{
    public function __construct(
        public readonly string $name,
        public readonly bool $hasGuidelines,
        public readonly bool $hasSkills,
        public readonly bool $hasMcp = false,
    ) {
        //
    }

    /**
     * Discover all third-party packages with boost features.
     *
     * @return Collection<string, ThirdPartyPackage>
     */
    public static function discover(): Collection
    {
        $withGuidelines = Composer::packagesDirectoriesWithBoostGuidelines();
        $withSkills = Composer::packagesDirectoriesWithBoostSkills();
        $withMcp = Composer::packagesDirectoriesWithBoostMcp();

        $allPackageNames = array_unique(array_merge(
            array_keys($withGuidelines),
            array_keys($withSkills),
            array_keys($withMcp)
        ));

        return collect($allPackageNames)
            ->mapWithKeys(fn (string $name): array => [
                $name => new self(
                    name: $name,
                    hasGuidelines: isset($withGuidelines[$name]),
                    hasSkills: isset($withSkills[$name]),
                    hasMcp: isset($withMcp[$name]),
                ),
            ]);
    }

    public function featureLabel(): string
    {
        return match (true) {
            $this->hasGuidelines && $this->hasSkills && $this->hasMcp => 'guidelines, skills, mcp',
            $this->hasGuidelines && $this->hasSkills => 'guidelines, skills',
            $this->hasGuidelines && $this->hasMcp => 'guidelines, mcp',
            $this->hasSkills && $this->hasMcp => 'skills, mcp',
            $this->hasGuidelines => 'guidelines',
            $this->hasSkills => 'skills',
            $this->hasMcp => 'mcp',
            default => '',
        };
    }

    public function displayLabel(): string
    {
        return "{$this->name} ({$this->featureLabel()})";
    }
}
