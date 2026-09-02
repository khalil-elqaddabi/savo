<?php

namespace App\Services\AI;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Registry of the controlled financial tools available to the AI assistant.
 *
 * Security model:
 *  - Tools are looked up by name against a fixed whitelist.
 *  - Execution always injects the authenticated user id; no tool accepts an
 *    arbitrary user id from the model.
 *  - Unknown tool names and unexpected invocations are rejected.
 */
class FinancialToolRegistry
{
    /** @var array<string, FinancialTool> */
    private array $tools = [];

    public function __construct(iterable $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return array<string, FinancialTool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * JSON-schema tool definitions for the provider's function-calling protocol.
     *
     * @return array<int, array<string, mixed>>
     */
    public function schemas(): array
    {
        return collect($this->tools)
            ->map(fn (FinancialTool $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->parameters(),
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Execute a named tool for the given authenticated user.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the tool name is not whitelisted
     */
    public function execute(string $name, int $userId, array $arguments = [], ?CarbonImmutable $reference = null): array
    {
        if (! $this->has($name)) {
            throw new RuntimeException("Unknown tool requested: {$name}");
        }

        return $this->tools[$name]->run($userId, $reference, $arguments);
    }
}
