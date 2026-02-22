<?php

namespace App\Modules\POS\Actions;

use App\Models\POS\PosSession;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\POS\PosPayment;
use App\Models\POS\PosProduct;
use App\Models\Ecommerce\Customer;
use App\Models\Ecommerce\LoyaltyProgram;
use App\Models\Branch;
use App\Modules\POS\DTOs\CheckoutDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessCheckoutAction
{
    public function __construct(
        private GenerateZatcaQrAction $zatcaAction
    ) {}

    /**
     * Process a POS Checkout.
     */
    public function execute(CheckoutDTO $dto): array
    {
        // Prevent duplicate processing of offline sales
        if ($dto->offline_id && PosSale::where('offline_id', $dto->offline_id)->exists()) {
            $existing = PosSale::where('offline_id', $dto->offline_id)->first();
            return [
                'success' => true,
                'sale_id' => $existing->id,
                'sale_number' => $existing->sale_number,
                'duplicate' => true
            ];
        }

        return DB::transaction(function () use ($dto) {
            // 1. Validate Session
            $session = PosSession::findOrFail($dto->session_id);
            if ($session->status !== 'open') {
                throw new \Exception("Cannot checkout. POS Session is closed.");
            }

            // 2. Resolve Branch & Warehouse (Defaults from session or user)
            $branchId = $dto->branch_id ?? $session->branch_id ?? auth()->user()->branch_id;
            $branch = $branchId ? Branch::find($branchId) : null;
            $warehouseId = $dto->warehouse_id ?? $session->warehouse_id;

            // 3. Validate Customer & Points
            $customer = $dto->customer_id ? Customer::find($dto->customer_id) : null;
            $loyalty  = LoyaltyProgram::where('is_active', true)->first();

            // 4. Calculate Totals
            $subtotal = 0;
            foreach ($dto->items as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }

            $discount = 0; 
            $tax      = 0; // In production, calculate based on branch VAT rules
            $total    = $subtotal - $discount + $tax;

            // 5. Create Sale Record
            $sale = PosSale::create([
                'sale_number'    => 'POS-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                'session_id'     => $session->id,
                'branch_id'      => $branchId,
                'warehouse_id'   => $warehouseId,
                'customer_id'    => $customer?->id,
                'customer_name'  => $customer?->name ?? 'Guest',
                'customer_phone' => $customer?->phone ?? null,
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'discount'       => $discount,
                'total'          => $total,
                'payment_status' => 'paid',
                'sold_by'        => auth()->id(),
                'notes'          => $dto->notes,
                'offline_id'     => $dto->offline_id,
            ]);

            // 6. Generate Saudi ZATCA QR if applicable
            if ($branch && $branch->country === 'Saudi Arabia' && $branch->vat_number) {
                try {
                    $qr = $this->zatcaAction->execute(
                        $branch->name,
                        $branch->vat_number,
                        $sale->created_at->toIso8601String(),
                        $total,
                        $tax
                    );
                    $sale->update(['zatca_qr' => $qr]);
                } catch (\Exception $e) {
                    Log::warning("[ZATCA] QR Generation failed for sale {$sale->id}: " . $e->getMessage());
                }
            }

            // 7. Create Sale Items & Update Stock
            foreach ($dto->items as $itemData) {
                $product = PosProduct::lockForUpdate()->findOrFail($itemData['id']);
                
                if ($product->stock_quantity < $itemData['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                PosSaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'quantity'     => $itemData['quantity'],
                    'unit_price'   => $itemData['price'],
                    'subtotal'     => $itemData['price'] * $itemData['quantity'],
                ]);

                // Decrement Stock
                $product->decrement('stock_quantity', $itemData['quantity']);
            }

            // 8. Process Payments
            $totalPaid = 0;
            $cashReceived = 0;
            foreach ($dto->payments as $pay) {
                PosPayment::create([
                    'sale_id'        => $sale->id,
                    'payment_method' => $pay['method'],
                    'amount'         => $pay['amount'],
                    'transaction_id' => $pay['transaction_id'] ?? null,
                ]);

                $totalPaid += $pay['amount'];
                if ($pay['method'] === 'cash') {
                    $cashReceived += $pay['amount'];
                    $session->increment('cash_transactions_total', $pay['amount']);
                } elseif ($pay['method'] === 'card') {
                    $session->increment('card_transactions_total', $pay['amount']);
                }
            }

            // 9. Handle Change Amount
            $changeAmount = 0;
            if ($totalPaid > $total) {
                $changeAmount = $totalPaid - $total;
                $sale->update([
                    'cash_received' => $cashReceived,
                    'change_amount' => $changeAmount,
                ]);
                $session->decrement('cash_transactions_total', $changeAmount);
            }

            // 10. Loyalty Points Logic
            if ($customer && $loyalty) {
                if ($dto->points_count > 0) {
                    $customer->points()->firstOrCreate([])->redeemPoints($dto->points_count, "Sale {$sale->sale_number}", $sale->id);
                    $sale->increment('points_redeemed', $dto->points_count);
                }

                $pointsEarned = $loyalty->calculateEarnedPoints($total);
                if ($pointsEarned > 0) {
                    $customer->points()->firstOrCreate([])->addPoints($pointsEarned, 'earn', "Sale {$sale->sale_number}", 'pos_sale', $sale->id);
                    $sale->increment('points_earned', $pointsEarned);
                }
            }

            return [
                'success' => true,
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'change' => $changeAmount,
                'points_earned' => $pointsEarned ?? 0,
                'zatca_qr' => $sale->zatca_qr
            ];
        });
    }
}
