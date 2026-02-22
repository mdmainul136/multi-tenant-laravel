<?php

namespace App\Modules\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\MarketingAudience;
use App\Models\Marketing\MarketingTemplate;
use App\Modules\Marketing\Actions\StoreCampaignAction;
use App\Modules\Marketing\Actions\ExecuteCampaignAction;
use App\Modules\Marketing\DTOs\CampaignDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    /**
     * List all campaigns.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => Campaign::with(['audience', 'variants.template'])->orderByDesc('created_at')->get()
        ]);
    }

    /**
     * Create a new campaign.
     */
    public function store(Request $request, StoreCampaignAction $action): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'audience_id' => 'required|exists:marketing_audiences,id',
            'channel'     => 'required|in:email,sms,whatsapp',
            'is_ab_test'  => 'boolean',
            'variants'    => 'required|array|min:1',
            'variants.*.template_id' => 'required|exists:marketing_templates,id',
            'variants.*.ratio'       => 'required|numeric|min:0|max:100',
        ]);

        try {
            $dto = CampaignDTO::fromRequest($request->all());
            $campaign = $action->execute($dto);
            return response()->json(['success' => true, 'data' => $campaign->load('variants')], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Manually trigger a campaign.
     */
    public function send(int $id, ExecuteCampaignAction $action): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        
        if ($campaign->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Campaign already completed'], 422);
        }

        $action->execute($campaign);

        return response()->json(['success' => true, 'message' => 'Campaign execution started']);
    }

    /**
     * Get campaign analytics.
     */
    public function analytics(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        
        $stats = $campaign->logs()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'success' => true,
            'data'    => [
                'campaign' => $campaign->name,
                'stats'    => $stats,
                'total'    => $campaign->logs()->count()
            ]
        ]);
    }
}
