<?php

namespace App\Modules\Marketing\Services;

use App\Models\Ecommerce\Customer;
use App\Models\Marketing\MarketingAudience;
use Illuminate\Database\Eloquent\Builder;

class AudienceService
{
    /**
     * Get customers belonging to a specific audience.
     */
    public function getAudienceCustomers(MarketingAudience $audience): Builder
    {
        if ($audience->type === 'static') {
            // Static audiences might use a pivot table, for now we assume 'rules' contains customer IDs
            $ids = $audience->rules['customer_ids'] ?? [];
            return Customer::whereIn('id', $ids);
        }

        // Dynamic Audience Logic
        $query = Customer::query();
        $rules = $audience->rules ?? [];

        foreach ($rules as $field => $condition) {
            $this->applyRule($query, $field, $condition);
        }

        return $query;
    }

    /**
     * Apply a single rule to the customer query.
     */
    private function applyRule(Builder $query, string $field, $condition): void
    {
        $operator = $condition['operator'] ?? '=';
        $value    = $condition['value'] ?? null;

        switch ($field) {
            case 'total_spent':
                // Logic assuming we have an orders relationship or total_spent column
                $query->where('total_spent', $operator, $value);
                break;
            case 'last_login':
                $query->where('last_login_at', $operator, $value);
                break;
            case 'country':
                $query->where('country', $operator, $value);
                break;
            case 'points':
                $query->whereHas('points', function($q) use ($operator, $value) {
                    $q->where('points_balance', $operator, $value);
                });
                break;
            // Add more segments as needed (tags, newsletter subscription, etc.)
        }
    }
}
