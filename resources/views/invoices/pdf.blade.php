<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            line-height: 1.6;
        }
        .container {
            width: 100%;
            margin: 0 auto;
        }
        .header {
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2D3748;
            margin: 0;
            font-size: 28px;
        }
        .company-info {
            float: right;
            text-align: right;
        }
        .invoice-details {
            margin-bottom: 30px;
            overflow: hidden;
        }
        .left-col {
            float: left;
            width: 50%;
        }
        .right-col {
            float: right;
            width: 50%;
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background: #f8f9fa;
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .total-section {
            float: right;
            width: 40%;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .grand-total {
            font-weight: bold;
            font-size: 18px;
            border-top: 2px solid #ddd;
            margin-top: 10px;
            padding-top: 10px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 12px;
            color: #888;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-info">
                <strong>Multi-Tenant SaaS</strong><br>
                123 Business Street<br>
                New York, NY 10001<br>
                support@example.com
            </div>
            <h1>INVOICE</h1>
            <p>#{{ $invoice->invoice_number }}</p>
        </div>

        <div class="invoice-details">
            <div class="left-col">
                <strong>Bill To:</strong><br>
                {{ $invoice->tenant->company_name ?? $invoice->tenant->name }}<br>
                {{ $invoice->tenant->admin_email }}<br>
                @if($invoice->tenant->address)
                    {{ $invoice->tenant->address }}<br>
                    {{ $invoice->tenant->city }}, {{ $invoice->tenant->country }}
                @endif
            </div>
            <div class="right-col">
                <strong>Invoice Date:</strong> {{ $invoice->invoice_date->format('M d, Y') }}<br>
                <strong>Due Date:</strong> {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A' }}<br>
                <strong>Status:</strong> 
                <span class="badge badge-{{ $invoice->status }}">{{ $invoice->status }}</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Plan</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        @if(isset($invoice->metadata['item_type']) && $invoice->metadata['item_type'] === 'domain')
                            <strong>Domain Registration: {{ $invoice->metadata['domain_name'] ?? 'N/A' }}</strong><br>
                            <span style="color: #666; font-size: 12px;">Registration for {{ $invoice->metadata['years'] ?? 1 }} year(s)</span>
                        @elseif(isset($invoice->metadata['item_type']) && $invoice->metadata['item_type'] === 'module')
                            <strong>{{ $invoice->metadata['module_name'] ?? ($invoice->module->module_name ?? 'Module') }}</strong><br>
                            <span style="color: #666; font-size: 12px;">{{ $invoice->module->description ?? 'Service subscription' }}</span>
                        @else
                            <strong>{{ $invoice->module->module_name ?? 'Service' }}</strong><br>
                            <span style="color: #666; font-size: 12px;">{{ $invoice->module->description ?? $invoice->notes }}</span>
                        @endif
                    </td>
                    <td>
                        @if(isset($invoice->metadata['item_type']) && $invoice->metadata['item_type'] === 'domain')
                            {{ $invoice->metadata['years'] ?? 1 }} Year(s)
                        @else
                            {{ ucfirst($invoice->subscription_type) }}
                        @endif
                    </td>
                    <td style="text-align: right;">${{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            <table style="width: 100%">
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align: right;">${{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                @if($invoice->discount > 0)
                <tr>
                    <td>Discount:</td>
                    <td style="text-align: right;">-${{ number_format($invoice->discount, 2) }}</td>
                </tr>
                @endif
                @if($invoice->tax > 0)
                <tr>
                    <td>Tax:</td>
                    <td style="text-align: right;">${{ number_format($invoice->tax, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td><strong>Total:</strong></td>
                    <td style="text-align: right;"><strong>${{ number_format($invoice->total, 2) }}</strong></td>
                </tr>
            </table>
        </div>

        <div style="clear: both;"></div>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>This is a computer-generated invoice and doesn't require a signature.</p>
        </div>
    </div>
</body>
</html>
