<?php

namespace App\Models\Marketing;

use App\Models\TenantBaseModel;
use App\Models\Ecommerce\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignLog extends TenantBaseModel
{
    protected $table = 'marketing_campaign_logs';

    protected $fillable = [
        'campaign_id',
        'variant_id',
        'customer_id',
        'recipient',
        'status',
        'external_id',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CampaignVariant::class, 'variant_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
