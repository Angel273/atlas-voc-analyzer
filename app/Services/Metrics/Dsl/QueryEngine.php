<?php

namespace App\Services\Metrics\Dsl;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class QueryEngine
{
    public function __construct(
        protected QueryDslValidator $validator,
        protected QueryPlanner $planner
    ) {}

    /**
     * Execute a Query DSL payload safely through Validator, Authorization, and Planner.
     */
    public function execute(array $dsl, ?User $user = null): array
    {
        // 1. Authorization check
        if ($user && !$user->hasPermission('data.view') && !$user->hasPermission('dashboard.view')) {
            throw new AuthorizationException("User unauthorized to query analytical data.");
        }

        // 2. Validate DSL with strict allowlist
        $validDsl = $this->validator->validate($dsl);

        // 3. Plan & Build Query
        $query = $this->planner->buildQuery($validDsl);

        // 4. Execute
        $records = $query->get()->map(function ($row) {
            return (array) $row;
        })->toArray();

        return [
            'success' => true,
            'dsl' => $validDsl,
            'count' => count($records),
            'data' => $records,
        ];
    }
}
