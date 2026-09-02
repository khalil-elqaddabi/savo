<?php

namespace App\Services\AI;

use Carbon\CarbonImmutable;

/**
 * A controlled, server-side financial tool exposed to the AI assistant.
 *
 * Tools never accept a user id from the model: the authenticated user id is
 * always injected at call time. Each tool returns only the minimal structured
 * data required to answer the relevant question, computed by the deterministic
 * finance engine services.
 */
interface FinancialTool
{
    /**
     * Unique tool name used in tool-calling protocols.
     */
    public function name(): string;

    /**
     * Human-readable description of what the tool returns.
     */
    public function description(): string;

    /**
     * JSON-schema definition of the tool's arguments (may be empty object).
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Execute the tool for the given authenticated user.
     *
     * @return array<string, mixed>
     */
    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array;
}
