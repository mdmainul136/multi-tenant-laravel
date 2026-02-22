<?php

namespace App\Modules\Tracking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Services\RetryQueueService;
use App\Modules\Tracking\Services\ConsentManagementService;
use App\Modules\Tracking\Services\ChannelHealthService;
use App\Modules\Tracking\Services\AttributionService;
use App\Modules\Tracking\Services\TagManagementService;
use App\Modules\Tracking\Services\DestinationService;
use Illuminate\Http\Request;

/**
 * Infrastructure Controller.
 *
 * Endpoints for:
 *   - DLQ/Retry management
 *   - Consent management
 *   - Channel health dashboard
 *   - Attribution reports
 *   - Tag management (CRUD + evaluate)
 *   - Supported destinations list
 */
class InfrastructureController extends Controller
{
    public function __construct(
        private RetryQueueService $retryQueue,
        private ConsentManagementService $consent,
        private ChannelHealthService $channelHealth,
        private AttributionService $attribution,
        private TagManagementService $tagManager,
    ) {}

    // ═══════════════════════════════════════════════════
    // DLQ / Retry
    // ═══════════════════════════════════════════════════

    public function dlqStats(int $id)
    {
        return response()->json(['success' => true, 'data' => $this->retryQueue->getStats($id)]);
    }

    public function dlqRetry(int $id)
    {
        $batch = $this->retryQueue->getRetryBatch(50);
        return response()->json(['success' => true, 'data' => ['pending' => count($batch)]]);
    }

    public function dlqPurge()
    {
        $purged = $this->retryQueue->purge();
        return response()->json(['success' => true, 'data' => ['purged' => $purged]]);
    }

    // ═══════════════════════════════════════════════════
    // Consent
    // ═══════════════════════════════════════════════════

    public function recordConsent(Request $request, int $id)
    {
        $validated = $request->validate([
            'visitor_id'    => 'required|string|max:150',
            'analytics'     => 'boolean',
            'marketing'     => 'boolean',
            'functional'    => 'boolean',
            'personalization' => 'boolean',
        ]);

        $visitorId = $validated['visitor_id'];
        unset($validated['visitor_id']);

        $result = $this->consent->recordConsent($id, $visitorId, $validated, [
            'source' => $request->input('source', 'api'),
            'ip'     => $request->ip(),
        ]);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function getConsent(int $id, string $visitorId)
    {
        $consent = $this->consent->getConsent($id, $visitorId);
        return response()->json(['success' => true, 'data' => $consent]);
    }

    public function consentStats(int $id)
    {
        return response()->json(['success' => true, 'data' => $this->consent->getConsentStats($id)]);
    }

    public function consentBanner(int $id)
    {
        return response()->json(['success' => true, 'data' => $this->consent->getBannerConfig($id)]);
    }

    public function revokeConsent(Request $request, int $id)
    {
        $visitorId = $request->input('visitor_id');
        $result = $this->consent->revokeConsent($id, $visitorId);
        return response()->json(['success' => $result]);
    }

    // ═══════════════════════════════════════════════════
    // Channel Health
    // ═══════════════════════════════════════════════════

    public function channelHealth(Request $request, int $id)
    {
        $days = $request->input('days', 7);
        return response()->json(['success' => true, 'data' => $this->channelHealth->getDashboard($id, $days)]);
    }

    public function channelAlerts(int $id)
    {
        return response()->json(['success' => true, 'data' => $this->channelHealth->getAlerts($id)]);
    }

    // ═══════════════════════════════════════════════════
    // Attribution
    // ═══════════════════════════════════════════════════

    public function attribution(Request $request, int $id)
    {
        $model = $request->input('model', 'last_touch');
        $days  = $request->input('days', 30);
        return response()->json(['success' => true, 'data' => $this->attribution->getAttribution($id, $model, $days)]);
    }

    public function conversionPaths(Request $request, int $id)
    {
        $days = $request->input('days', 30);
        return response()->json(['success' => true, 'data' => $this->attribution->getConversionPaths($id, $days)]);
    }

    // ═══════════════════════════════════════════════════
    // Tag Management
    // ═══════════════════════════════════════════════════

    public function listTags(int $id)
    {
        return response()->json(['success' => true, 'data' => $this->tagManager->listTags($id)]);
    }

    public function createTag(Request $request, int $id)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:150',
            'type'             => 'string|in:pixel,script,conversion,custom',
            'destination_type' => 'nullable|string',
            'config'           => 'array',
            'triggers'         => 'array',
            'variables'        => 'array',
            'is_active'        => 'boolean',
            'priority'         => 'integer|min:0|max:100',
        ]);

        $tag = $this->tagManager->createTag($id, $validated);
        return response()->json(['success' => true, 'data' => $tag], 201);
    }

    public function updateTag(Request $request, int $tagId)
    {
        $tag = $this->tagManager->updateTag($tagId, $request->all());
        return response()->json(['success' => true, 'data' => $tag]);
    }

    public function deleteTag(int $tagId)
    {
        $result = $this->tagManager->deleteTag($tagId);
        return response()->json(['success' => $result]);
    }

    // ═══════════════════════════════════════════════════
    // Supported Destinations
    // ═══════════════════════════════════════════════════

    public function destinations()
    {
        return response()->json([
            'success' => true,
            'data'    => DestinationService::supportedDestinations(),
        ]);
    }
}
