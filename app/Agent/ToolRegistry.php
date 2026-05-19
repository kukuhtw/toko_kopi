<?php

declare(strict_types=1);

namespace App\Agent;

final class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, ToolInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function describeAll(): array
    {
        $descriptions = [];
        foreach ($this->tools as $tool) {
            $descriptions[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'mutating' => $tool->isMutating(),
                'input_schema' => $tool->getInputSchema(),
            ];
        }

        return $descriptions;
    }
}
