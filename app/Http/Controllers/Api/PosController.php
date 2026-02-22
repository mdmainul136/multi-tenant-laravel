<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\POS\PosPayment;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosController extends Controller
{
    /**
     * Sync an offline order to the server
     */
    public function syncOrder(Request $request)
    {
        try {
            $tenantId = $request->input('token_tenant_id');
            $tenant = Tenant::where('tenant_id', $tenantId)->firstOrFail();
            
            $orderData = $request->all();
            
            // Check if order already exists (idempotency via tempId)
            $existing = PosSale::where('offline_id', $orderData['tempId'])->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order already synced',
                    'zatca_qr' => $existing->zatca_qr
                ]);
            }

            return DB::transaction(function () use ($orderData, $tenant) {
                // 1. Create Sale Record
                $sale = PosSale::create([
                    'sale_number' => PosSale::generateSaleNumber(), // Assuming a helper exists or we add one
                    'offline_id' => $orderData['tempId'],
                    'tenant_id' => $tenant->id,
                    'branch_id' => $orderData['branchId'] ?? null,
                    'sold_by' => $orderData['staffId'] ?? null,
                    'subtotal' => $orderData['subtotal'],
                    'tax' => $orderData['tax'],
                    'discount' => $orderData['discount'],
                    'total' => $orderData['total'],
                    'payment_method' => $orderData['paymentMethod'],
                    'status' => 'completed',
                    'created_at' => date('Y-m-d H:i:s', $orderData['createdAt'] / 1000),
                ]);

                // 2. Create Sale Items
                foreach ($orderData['items'] as $item) {
                    PosSaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['id'],
                        'quantity' => $item['qty'],
                        'unit_price' => $item['price'],
                        'total_price' => $item['price'] * $item['qty'],
                    ]);
                    
                    // TODO: Decrement inventory if using centralized inventory
                    // $this->decrementInventory($item['id'], $item['qty'], $orderData['branchId']);
                }

                // 3. Handle Split Payments or single payment
                if ($orderData['paymentMethod'] === 'split' && isset($orderData['paymentDetails'])) {
                    foreach ($orderData['paymentDetails'] as $method => $amount) {
                        PosPayment::create([
                            'sale_id' => $sale->id,
                            'payment_method' => $method,
                            'amount' => $amount,
                            'status' => 'completed',
                        ]);
                    }
                } else {
                    PosPayment::create([
                        'sale_id' => $sale->id,
                        'payment_method' => $orderData['paymentMethod'],
                        'amount' => $orderData['total'],
                        'status' => 'completed',
                    ]);
                }

                // 4. Generate ZATCA QR Code (Saudi Requirement)
                $zatcaQr = $this->generateZatcaQr($sale, $tenant);
                $sale->update(['zatca_qr' => $zatcaQr]);

                return response()->json([
                    'success' => true,
                    'zatca_qr' => $zatcaQr
                ]);
            });

        } catch (\Exception $e) {
            Log::error('POS Sync Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error during sync'
            ], 500);
        }
    }

    /**
     * Get branch-specific products and stock
     */
    public function getInventory(Request $request)
    {
        $branchId = $request->query('branch_id');
        // Logic to return products with stock for this branch
        // For now, returning global products or a placeholder
        return response()->json(['success' => true, 'data' => []]);
    }

    /**
     * Placeholder for ZATCA QR generation
     */
    private function generateZatcaQr($sale, $tenant)
    {
        // TLV encoding logic for ZATCA Saudi Arabia Phase 1
        // 1. Seller Name
        // 2. VAT Number
        // 3. Timestamp
        // 4. Total with VAT
        // 5. VAT Amount
        
        $sellerName = $tenant->tenant_name;
        $vatNumber = $tenant->vat_number ?? '300000000000003'; // Placeholder
        $timestamp = $sale->created_at->toIso8601String();
        $totalWithVat = (string)$sale->total;
        $vatAmount = (string)$sale->tax;

        // Simple base64 placeholder for now
        return base64_encode($sellerName . $vatNumber . $timestamp . $totalWithVat . $vatAmount);
    }
}
