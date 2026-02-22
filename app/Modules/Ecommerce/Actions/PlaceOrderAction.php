<?php

namespace App\Modules\Ecommerce\Actions;

use App\Models\Ecommerce\Order;
use App\Models\Ecommerce\OrderItem;
use App\Models\Ecommerce\Product;
use App\Modules\Ecommerce\DTOs\OrderDTO;
use Illuminate\Support\Facades\DB;

class PlaceOrderAction
{
    public function execute(OrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            $subtotal = 0;
            $orderItems = [];
            $hasIorProduct = false;
            
            // Services
            $iorPricer = app(\App\Modules\CrossBorderIOR\Services\ProductPricingCalculator::class);
            $walletService = app(\App\Modules\Ecommerce\Services\WalletService::class);

            foreach ($dto->items as $item) {
                $product = Product::find($item['product_id']);
                $productType = $item['product_type'] ?? ($product ? $product->product_type : 'local');
                
                // If product is foreign (either existing catalog item or ad-hoc source)
                if ($productType === 'foreign' || $productType === 'international') {
                    $hasIorProduct = true;
                    
                    // Use metadata from item if product doesn't exist (ad-hoc)
                    $cost = $product ? (float)($product->cost ?? 0) : (float)($item['unit_price'] * 0.7); // 70% cost fallback
                    $weight = $product ? (float)($product->weight ?? 0.5) : 0.5;
                    $name = $product ? $product->name : ($item['name'] ?? 'Imported Product');
                    
                    // Use IOR pricing calculation
                    $pricing = $iorPricer->calculate(
                        usdPrice: $cost * $item['quantity'],
                        weightKg: $weight * $item['quantity'],
                        productTitle: $name,
                        shippingMethod: $item['shipping_method'] ?? $dto->shipping_method ?? 'air'
                    );
                    
                    $priceToCharge = $pricing['estimated_price_bdt'];
                    $productName = $name;
                    $sku = $product ? $product->sku : 'IOR-AD-HOC';
                } else {
                    if (!$product) {
                        throw new \Exception("Product not found: " . ($item['product_id'] ?? 'Unknown'));
                    }
                    // Standard Local Product
                    if ($product->stock_quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product: {$product->name}");
                    }
                    $priceToCharge = $product->getCurrentPrice(); // Automatically applies Flash Sale if active
                    $product->decrement('stock_quantity', $item['quantity']);
                    $productName = $product->name;
                    $sku = $product->sku;
                }

                $itemSubtotal = $priceToCharge * $item['quantity'];
                $subtotal += $itemSubtotal;

                $orderItems[] = [
                    'product_id' => $product ? $product->id : null,
                    'product_name' => $productName,
                    'sku' => $sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $priceToCharge,
                    'subtotal' => $itemSubtotal,
                    // Store extra metadata in a json field if we had one, 
                    // otherwise it's just in the name/price for now.
                ];
            }

            $order = Order::create([
                'order_number' => ($hasIorProduct ? 'IOR-' : 'ORD-') . strtoupper(uniqid()),
                'customer_id' => $dto->customer_id,
                'order_type' => $hasIorProduct ? 'cross_border' : 'local',
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $dto->payment_method,
                'subtotal' => $dto->subtotal ?? $subtotal,
                'shipping' => $dto->shipping_cost ?? 0,
                'tax' => $dto->tax_amount ?? 0,
                'discount' => $dto->discount_amount ?? 0,
                'total' => $dto->total_amount ?? ($subtotal + ($dto->shipping_cost ?? 0) + ($dto->tax_amount ?? 0) - ($dto->discount_amount ?? 0)),
                'currency' => $hasIorProduct ? 'BDT' : 'USD',
                'shipping_address' => $dto->shipping_address,
                'billing_address' => $dto->shipping_address, // Default to same
                'customer_note' => $dto->notes,
                // These fields might need to be added to ec_orders table if they don't exist
                // For now, we'll store them in shipping_address or similar if needed, 
                // but better to add proper columns.
            ]);

            // If it's a guest order, we might want to store guest details somewhere.
            // Many systems store guest data in the order table itself or a separate guest table.
            if (!$dto->customer_id && $dto->guest_email) {
                // Handle guest-specific logic if needed
                $order->update(['customer_note' => ($order->customer_note ? $order->customer_note . "\n" : "") . "Guest Email: " . $dto->guest_email]);
            }

            foreach ($orderItems as $orderItem) {
                $orderItem['order_id'] = $order->id;
                OrderItem::create($orderItem);
            }

            // Handle Wallet Payment
            if ($dto->payment_method === 'wallet') {
                $walletService->withdraw(
                    $dto->customer_id, 
                    $subtotal, 
                    "Payment for Order #{$order->order_number}",
                    ['order_id' => $order->id]
                );
                $order->update(['payment_status' => 'paid', 'status' => 'processing']);
            }

            // Award Loyalty Points if customer exists
            if ($dto->customer_id) {
                app(\App\Services\LoyaltyService::class)->awardPointsForOrder($order);
            }

            // WhatsApp Notification
            if ($dto->customer_phone || ($order->customer && $order->customer->phone)) {
                $phone = $dto->customer_phone ?? $order->customer->phone;
                app(\App\Services\WhatsAppService::class)->sendOrderConfirmation(
                    $phone, 
                    $order->order_number, 
                    (string)$order->total
                );
            }

            return $order->load('items');
        });
    }
}
