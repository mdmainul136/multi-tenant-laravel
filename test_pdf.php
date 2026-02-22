<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

try {
    // Mock an invoice object for testing view rendering
    $invoice = new Invoice([
        'invoice_number' => 'INV-TEST-001',
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'status' => 'paid',
        'subscription_type' => 'monthly',
        'subtotal' => 100.00,
        'tax' => 10.00,
        'discount' => 0.00,
        'total' => 110.00,
    ]);
    
    // Mock relations
    $invoice->setRelation('tenant', new App\Models\Tenant([
        'name' => 'Test Tenant',
        'company_name' => 'Test Company',
        'admin_email' => 'admin@test.com',
        'address' => '123 Test St',
        'city' => 'Test City',
        'country' => 'Test Country'
    ]));
    
    $invoice->setRelation('module', new App\Models\Module([
        'module_name' => 'POS Module',
        'description' => 'Point of Sale System'
    ]));

    echo "Attempting to render PDF view...\n";
    $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
    $output = $pdf->output();
    
    if (strlen($output) > 0 && strpos($output, '%PDF') === 0) {
        echo "✅ PDF generated successfully! Size: " . strlen($output) . " bytes\n";
    } else {
        echo "❌ PDF generation failed or invalid output.\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
