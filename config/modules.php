<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available Modules (40 Modules)
    |--------------------------------------------------------------------------
    */

    // ── Core Commerce ───────────────────────────────────────────────────
    'ecommerce' => [
        'name'        => 'eCommerce',
        'description' => 'Full-featured online store with product catalog, shopping cart, and order management',
        'migrations_path' => 'database/migrations/tenant/modules/ecommerce',
        'price'       => 49.99,
        'icon'        => 'shopping-cart',
        'color'       => '#6366f1',
        'features'    => ['Product Catalog', 'Shopping Cart', 'Order Management', 'Customer Accounts', 'Payment Integration'],
    ],

    'pos' => [
        'name'        => 'Point of Sale (POS)',
        'description' => 'Complete POS system with cash register, barcode support, and sales tracking',
        'migrations_path' => 'database/migrations/tenant/modules/pos',
        'price'       => 29.99,
        'icon'        => 'monitor',
        'color'       => '#f97316',
        'features'    => ['Product Management', 'Sales Tracking', 'Inventory Control', 'Barcode Support', 'Sales Reports'],
    ],

    'inventory' => [
        'name'        => 'Inventory Management',
        'description' => 'Suppliers, purchase orders, stock tracking and reorder management',
        'migrations_path' => 'database/migrations/tenant/modules/inventory',
        'price'       => 34.99,
        'icon'        => 'package',
        'color'       => '#f59e0b',
        'features'    => ['Supplier Management', 'Purchase Orders', 'Stock Auto-Update on Receive', 'Low-Stock Alerts'],
    ],

    'marketplace' => [
        'name'        => 'Marketplace',
        'description' => 'Multi-vendor marketplace with vendor management and commission tracking',
        'migrations_path' => 'database/migrations/tenant/modules/marketplace',
        'price'       => 49.00,
        'icon'        => 'store',
        'color'       => '#7c3aed',
        'features'    => ['Vendor Management', 'Commission Tracking', 'Multi-Vendor Products', 'Vendor Payouts'],
    ],

    'flash-sales' => [
        'name'        => 'Flash Sales',
        'description' => 'Time-limited deals with countdown timers and stock limits',
        'migrations_path' => 'database/migrations/tenant/modules/flash_sales',
        'price'       => 14.99,
        'icon'        => 'zap',
        'color'       => '#ef4444',
        'features'    => ['Countdown Timer', 'Stock Limits', 'Auto-Scheduling', 'Campaign Analytics'],
    ],

    // ── Customer & Marketing ────────────────────────────────────────────
    'crm' => [
        'name'        => 'CRM & Loyalty',
        'description' => 'Customer management, deals pipeline, contacts, and task management',
        'migrations_path' => 'database/migrations/tenant/modules/crm',
        'price'       => 39.99,
        'icon'        => 'users',
        'color'       => '#10b981',
        'features'    => ['Customer Management', 'Deals Pipeline', 'Contact Management', 'Task Tracking', 'Customer LTV Analytics'],
    ],

    'marketing' => [
        'name'        => 'Marketing',
        'description' => 'Campaign management, email marketing, social media scheduling and analytics',
        'migrations_path' => 'database/migrations/tenant/modules/marketing',
        'price'       => 29.99,
        'icon'        => 'trending-up',
        'color'       => '#ec4899',
        'features'    => ['Campaign Manager', 'Email Marketing', 'Social Scheduling', 'Marketing Analytics'],
    ],

    'loyalty' => [
        'name'        => 'Loyalty Program',
        'description' => 'Points system, rewards catalog, member tiers, and referral tracking',
        'migrations_path' => 'database/migrations/tenant/modules/loyalty',
        'price'       => 24.99,
        'icon'        => 'award',
        'color'       => '#f472b6',
        'features'    => ['Points System', 'Rewards Catalog', 'Member Tiers', 'Referral Program'],
    ],

    'whatsapp' => [
        'name'        => 'WhatsApp Business',
        'description' => 'WhatsApp Business API integration for customer messaging and notifications',
        'migrations_path' => 'database/migrations/tenant/modules/whatsapp',
        'price'       => 19.99,
        'icon'        => 'message-circle',
        'color'       => '#25d366',
        'features'    => ['WhatsApp Cloud API', 'Template Messages', 'Chatbot', 'Order Notifications'],
    ],

    'notifications' => [
        'name'        => 'Notification Center',
        'description' => 'In-app notifications with templates, broadcast messaging and alert management',
        'migrations_path' => 'database/migrations/tenant/modules/notifications',
        'price'       => 19.99,
        'icon'        => 'bell',
        'color'       => '#06b6d4',
        'features'    => ['Broadcast Notifications', 'Template Engine', 'Mark Read / Unread', 'Auto Cleanup', 'Notification Analytics'],
    ],

    'reviews' => [
        'name'        => 'Reviews & Ratings',
        'description' => 'Product reviews, star ratings, moderation, and review analytics',
        'migrations_path' => 'database/migrations/tenant/modules/reviews',
        'price'       => 9.99,
        'icon'        => 'star',
        'color'       => '#fbbf24',
        'features'    => ['Product Reviews', 'Star Ratings', 'Moderation Dashboard', 'Review Analytics'],
    ],

    // ── Business Operations ─────────────────────────────────────────────
    'finance' => [
        'name'        => 'Finance & Reports',
        'description' => 'Chart of accounts, double-entry bookkeeping, tax rules and financial reports',
        'migrations_path' => 'database/migrations/tenant/modules/finance',
        'price'       => 39.99,
        'icon'        => 'bar-chart',
        'color'       => '#ef4444',
        'features'    => ['Chart of Accounts', 'Double-Entry Ledger', 'Tax Configuration', 'Multi-Currency', 'Financial Reports'],
    ],

    'hrm' => [
        'name'        => 'HR & Staff Management',
        'description' => 'Employee records, departments, attendance tracking and leave management',
        'migrations_path' => 'database/migrations/tenant/modules/hrm',
        'price'       => 29.99,
        'icon'        => 'briefcase',
        'color'       => '#8b5cf6',
        'features'    => ['Department Management', 'Employee Records', 'Attendance Tracking', 'Leave Requests', 'Payroll Summary'],
    ],

    'expenses' => [
        'name'        => 'Expense Management',
        'description' => 'Expense tracking, receipt scanning, approval workflows and budget monitoring',
        'migrations_path' => 'database/migrations/tenant/modules/expenses',
        'price'       => 14.99,
        'icon'        => 'credit-card',
        'color'       => '#a855f7',
        'features'    => ['Expense Tracking', 'Receipt Upload', 'Approval Workflow', 'Budget Monitoring'],
    ],

    'contracts' => [
        'name'        => 'Contracts',
        'description' => 'Contract management, renewals, e-signatures, and compliance tracking',
        'migrations_path' => 'database/migrations/tenant/modules/contracts',
        'price'       => 19.99,
        'icon'        => 'file-text',
        'color'       => '#64748b',
        'features'    => ['Contract Templates', 'Auto-Renewals', 'E-Signatures', 'Compliance Tracking'],
    ],

    'branches' => [
        'name'        => 'Branch Management',
        'description' => 'Multi-branch operations with stock transfers, staff assignment, and analytics',
        'migrations_path' => 'database/migrations/tenant/modules/branches',
        'price'       => 29.00,
        'icon'        => 'git-branch',
        'color'       => '#0ea5e9',
        'features'    => ['Branch Profiles', 'Stock Transfers', 'Staff Assignment', 'Branch Analytics'],
    ],

    'manufacturing' => [
        'name'        => 'Manufacturing',
        'description' => 'Bill of materials, production orders, quality control and work centers',
        'migrations_path' => 'database/migrations/tenant/modules/manufacturing',
        'price'       => 49.99,
        'icon'        => 'tool',
        'color'       => '#78716c',
        'features'    => ['Bill of Materials', 'Production Orders', 'Quality Control', 'Work Centers'],
    ],

    // ── Analytics & Tracking ────────────────────────────────────────────
    'tracking' => [
        'name'        => 'Tracking & Analytics',
        'description' => 'Event tracking, pixel management, attribution, and analytics dashboards',
        'migrations_path' => 'database/migrations/tenant/modules/tracking',
        'price'       => 29.99,
        'icon'        => 'activity',
        'color'       => '#14b8a6',
        'features'    => ['Event Tracking', 'Pixel Management', 'Attribution', 'Real-time Dashboard'],
    ],

    'analytics' => [
        'name'        => 'Advanced Analytics',
        'description' => 'Business intelligence, custom reports, KPI dashboards and data exports',
        'migrations_path' => 'database/migrations/tenant/modules/analytics',
        'price'       => 34.99,
        'icon'        => 'pie-chart',
        'color'       => '#3b82f6',
        'features'    => ['Custom Reports', 'KPI Dashboard', 'Data Export', 'Trend Analysis'],
    ],

    'security' => [
        'name'        => 'Security & Audit',
        'description' => 'Audit logging, access controls, IP whitelist, and security monitoring',
        'migrations_path' => 'database/migrations/tenant/modules/security',
        'price'       => 24.99,
        'icon'        => 'shield',
        'color'       => '#dc2626',
        'features'    => ['Audit Logs', 'Access Controls', 'IP Whitelisting', 'Security Dashboard'],
    ],

    // ── Cross-Border & Compliance ───────────────────────────────────────
    'cross-border-ior' => [
        'name'        => 'Cross-Border IOR',
        'description' => 'Importer of Record, HS code classification, duty calculation and compliance',
        'migrations_path' => 'database/migrations/tenant/modules/cross_border_ior',
        'price'       => 79.00,
        'icon'        => 'globe',
        'color'       => '#0284c7',
        'features'    => ['IOR Service', 'HS Code Lookup', 'Duty Calculator', 'Restricted Items Check'],
    ],

    'zatca' => [
        'name'        => 'ZATCA E-Invoicing',
        'description' => 'Saudi Arabia ZATCA e-invoicing compliance with QR codes and XML generation',
        'migrations_path' => 'database/migrations/tenant/modules/zatca',
        'price'       => 29.99,
        'icon'        => 'file-check',
        'color'       => '#059669',
        'features'    => ['E-Invoice Generation', 'QR Code', 'ZATCA XML Submission', 'Compliance Reports'],
    ],

    'maroof' => [
        'name'        => 'Maroof Integration',
        'description' => 'Saudi Maroof marketplace trust badge and verification integration',
        'migrations_path' => 'database/migrations/tenant/modules/maroof',
        'price'       => 9.99,
        'icon'        => 'check-circle',
        'color'       => '#16a34a',
        'features'    => ['Maroof Badge', 'Trust Verification', 'Auto-Sync'],
    ],

    'national-address' => [
        'name'        => 'National Address',
        'description' => 'Saudi national address validation and auto-completion for shipping',
        'migrations_path' => 'database/migrations/tenant/modules/national_address',
        'price'       => 9.99,
        'icon'        => 'map-pin',
        'color'       => '#2563eb',
        'features'    => ['Address Lookup', 'Auto-Complete', 'Validation', 'Shipping Integration'],
    ],

    'sadad' => [
        'name'        => 'SADAD Payment',
        'description' => 'SADAD billing and payment gateway integration for Saudi Arabia',
        'migrations_path' => 'database/migrations/tenant/modules/sadad',
        'price'       => 14.99,
        'icon'        => 'dollar-sign',
        'color'       => '#4f46e5',
        'features'    => ['SADAD Billing', 'Payment Gateway', 'Auto-Reconciliation'],
    ],

    // ── Industry Verticals ──────────────────────────────────────────────
    'restaurant' => [
        'name'        => 'Restaurant Manager',
        'description' => 'Table management, menu builder, kitchen display, and reservations',
        'migrations_path' => 'database/migrations/tenant/modules/restaurant',
        'price'       => 39.99,
        'icon'        => 'coffee',
        'color'       => '#d97706',
        'features'    => ['Table Management', 'Menu Builder', 'Kitchen Display', 'Reservations', 'QR Menu'],
    ],

    'salon' => [
        'name'        => 'Salon & Spa',
        'description' => 'Appointment scheduling, service catalog, staff management and client profiles',
        'migrations_path' => 'database/migrations/tenant/modules/salon',
        'price'       => 29.99,
        'icon'        => 'scissors',
        'color'       => '#e11d48',
        'features'    => ['Appointment Booking', 'Service Catalog', 'Staff Schedules', 'Client Profiles'],
    ],

    'healthcare' => [
        'name'        => 'Healthcare',
        'description' => 'Patient records, appointment scheduling, prescriptions and billing',
        'migrations_path' => 'database/migrations/tenant/modules/healthcare',
        'price'       => 49.99,
        'icon'        => 'heart',
        'color'       => '#ef4444',
        'features'    => ['Patient Records', 'Appointment Scheduling', 'Prescription Management', 'Medical Billing'],
    ],

    'education' => [
        'name'        => 'Education',
        'description' => 'Course management, student enrollment, grade tracking and certificates',
        'migrations_path' => 'database/migrations/tenant/modules/education',
        'price'       => 39.99,
        'icon'        => 'book-open',
        'color'       => '#7c3aed',
        'features'    => ['Course Management', 'Student Enrollment', 'Grading', 'Certificates'],
    ],

    'lms' => [
        'name'        => 'Learning Management (LMS)',
        'description' => 'Online courses, quizzes, progress tracking and certificates',
        'migrations_path' => 'database/migrations/tenant/modules/lms',
        'price'       => 34.99,
        'icon'        => 'video',
        'color'       => '#6d28d9',
        'features'    => ['Online Courses', 'Quiz Builder', 'Progress Tracking', 'Certificates'],
    ],

    'fitness' => [
        'name'        => 'Fitness & Gym',
        'description' => 'Membership management, class scheduling, trainer profiles and workout plans',
        'migrations_path' => 'database/migrations/tenant/modules/fitness',
        'price'       => 29.99,
        'icon'        => 'activity',
        'color'       => '#059669',
        'features'    => ['Membership Plans', 'Class Scheduling', 'Trainer Profiles', 'Workout Plans'],
    ],

    'real-estate' => [
        'name'        => 'Real Estate',
        'description' => 'Property listings, tenant management, rent collection and maintenance tracking',
        'migrations_path' => 'database/migrations/tenant/modules/real_estate',
        'price'       => 49.99,
        'icon'        => 'home',
        'color'       => '#0d9488',
        'features'    => ['Property Listings', 'Tenant Management', 'Rent Collection', 'Maintenance Requests'],
    ],

    'automotive' => [
        'name'        => 'Automotive',
        'description' => 'Vehicle inventory, service booking, parts management and fleet tracking',
        'migrations_path' => 'database/migrations/tenant/modules/automotive',
        'price'       => 39.99,
        'icon'        => 'truck',
        'color'       => '#475569',
        'features'    => ['Vehicle Inventory', 'Service Booking', 'Parts Management', 'Fleet Tracking'],
    ],

    'travel' => [
        'name'        => 'Travel & Tourism',
        'description' => 'Tour packages, booking management, itinerary builder and travel guides',
        'migrations_path' => 'database/migrations/tenant/modules/travel',
        'price'       => 39.99,
        'icon'        => 'map',
        'color'       => '#0891b2',
        'features'    => ['Tour Packages', 'Booking Management', 'Itinerary Builder', 'Travel Guides'],
    ],

    'events' => [
        'name'        => 'Events Management',
        'description' => 'Event creation, ticketing, attendee management and virtual events',
        'migrations_path' => 'database/migrations/tenant/modules/events',
        'price'       => 29.99,
        'icon'        => 'calendar',
        'color'       => '#c026d3',
        'features'    => ['Event Creation', 'Ticketing', 'Attendee Management', 'Virtual Events'],
    ],

    'freelancer' => [
        'name'        => 'Freelancer',
        'description' => 'Project management, time tracking, invoicing and client portal',
        'migrations_path' => 'database/migrations/tenant/modules/freelancer',
        'price'       => 19.99,
        'icon'        => 'compass',
        'color'       => '#2dd4bf',
        'features'    => ['Project Management', 'Time Tracking', 'Invoice Generator', 'Client Portal'],
    ],

    // ── Platform & Infrastructure ───────────────────────────────────────
    'app-marketplace' => [
        'name'        => 'App Marketplace',
        'description' => 'Third-party app integrations, plugin store and API connections',
        'migrations_path' => 'database/migrations/tenant/modules/app_marketplace',
        'price'       => 0.00,
        'icon'        => 'grid',
        'color'       => '#4f46e5',
        'features'    => ['App Store', 'Plugin Integrations', 'API Connections', 'Webhook Manager'],
    ],

    'landlord' => [
        'name'        => 'Landlord Platform',
        'description' => 'Platform administration, tenant management, and system configuration',
        'migrations_path' => 'database/migrations/tenant/modules/landlord',
        'price'       => 0.00,
        'icon'        => 'settings',
        'color'       => '#334155',
        'features'    => ['Tenant Management', 'Platform Config', 'System Monitoring'],
    ],

    'seo-manager' => [
        'name'        => 'SEO Manager',
        'description' => 'Meta tags, sitemaps, structured data, and SEO analytics',
        'migrations_path' => 'database/migrations/tenant/modules/seo_manager',
        'price'       => 14.99,
        'icon'        => 'search',
        'color'       => '#84cc16',
        'features'    => ['Meta Tag Editor', 'Sitemap Generator', 'Structured Data', 'SEO Score'],
    ],

    'pages' => [
        'name'        => 'Page Builder',
        'description' => 'Drag-and-drop page builder for landing pages and content pages',
        'migrations_path' => 'database/migrations/tenant/modules/pages',
        'price'       => 19.99,
        'icon'        => 'layout',
        'color'       => '#6366f1',
        'features'    => ['Drag & Drop Builder', 'Templates', 'Custom HTML/CSS', 'Responsive Preview'],
    ],
];
