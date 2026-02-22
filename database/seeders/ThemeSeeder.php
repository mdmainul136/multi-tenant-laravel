<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Theme;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        $themes = [
            [
                'name' => 'Riyadh Modern',
                'vertical' => 'Fashion & Lifestyle',
                'config' => [
                    'brandName' => 'Riyadh Modern',
                    'primaryColor' => '#0f3460',
                    'accentColor' => '#e94560',
                    'headingFont' => 'Outfit',
                    'heroHeading' => 'New Season, New Styles',
                    'heroSubtext' => 'Discover the latest trends in Saudi fashion with our premium collection.',
                    'navStyle' => 'centered',
                ],
                'preview_url' => 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)',
            ],
            [
                'name' => 'Pharma Care',
                'vertical' => 'Healthcare',
                'config' => [
                    'brandName' => 'Pharma Care',
                    'primaryColor' => '#22c55e',
                    'accentColor' => '#166534',
                    'headingFont' => 'Inter',
                    'heroHeading' => 'Your Health, Our Priority',
                    'heroSubtext' => 'Safe and reliable medications delivered to your doorstep across the kingdom.',
                    'navStyle' => 'sticky',
                ],
                'preview_url' => 'linear-gradient(135deg, #166534 0%, #22c55e 50%, #86efac 100%)',
            ],
            [
                'name' => 'Jeddah Boutique',
                'vertical' => 'Luxury & Premium',
                'config' => [
                    'brandName' => 'Jeddah Boutique',
                    'primaryColor' => '#c9a96e',
                    'accentColor' => '#2d2d2d',
                    'headingFont' => 'Montserrat',
                    'heroHeading' => 'Elegance Redefined',
                    'heroSubtext' => 'Experience the finest luxury items curated for the sophisticated lifestyle.',
                    'navStyle' => 'minimal',
                ],
                'preview_url' => 'linear-gradient(135deg, #2d2d2d 0%, #1a1a1a 50%, #0d0d0d 100%)',
            ],
            [
                'name' => 'Skyline Luxe',
                'vertical' => 'Real Estate',
                'config' => [
                    'brandName' => 'Skyline Luxe',
                    'primaryColor' => '#2a4a7f',
                    'accentColor' => '#c9a96e',
                    'headingFont' => 'Montserrat',
                    'heroHeading' => 'Find Your Dream Home',
                    'heroSubtext' => 'Modern apartments and villas in the heart of the city.',
                    'navStyle' => 'sticky',
                ],
                'preview_url' => 'linear-gradient(135deg, #0c1b33 0%, #1a365d 50%, #2a4a7f 100%)',
            ],
            [
                'name' => 'Bistro Noir',
                'vertical' => 'Fine Dining',
                'config' => [
                    'brandName' => 'Bistro Noir',
                    'primaryColor' => '#1a1a1a',
                    'accentColor' => '#c9a96e',
                    'headingFont' => 'Playfair Display',
                    'heroHeading' => 'A Taste of Perfection',
                    'heroSubtext' => 'Join us for an unforgettable culinary journey tonight.',
                    'navStyle' => 'minimal',
                ],
                'preview_url' => 'linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #404040 100%)',
            ],
            [
                'name' => 'Edu Modern',
                'vertical' => 'Online Academy',
                'config' => [
                    'brandName' => 'Edu Modern',
                    'primaryColor' => '#2563eb',
                    'accentColor' => '#1e3a5f',
                    'headingFont' => 'Outfit',
                    'heroHeading' => 'Learn from the Best',
                    'heroSubtext' => 'Unlock your potential with our expert-led online courses.',
                    'navStyle' => 'centered',
                ],
                'preview_url' => 'linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #3b82f6 100%)',
            ],
            [
                'name' => 'Iron Gym',
                'vertical' => 'Fitness',
                'config' => [
                    'brandName' => 'Iron Gym',
                    'primaryColor' => '#dc2626',
                    'accentColor' => '#0f0f0f',
                    'headingFont' => 'Inter',
                    'heroHeading' => 'Unleash Your Power',
                    'heroSubtext' => 'Join the elite fitness community and crush your goals.',
                    'navStyle' => 'sticky',
                ],
                'preview_url' => 'linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 50%, #dc2626 100%)',
            ],
            [
                'name' => 'Corporate Edge',
                'vertical' => 'Business',
                'config' => [
                    'brandName' => 'Corporate Edge',
                    'primaryColor' => '#3b82f6',
                    'accentColor' => '#0f172a',
                    'headingFont' => 'Inter',
                    'heroHeading' => 'Solutions for Success',
                    'heroSubtext' => 'Professional consulting and services for the modern enterprise.',
                    'navStyle' => 'sticky',
                ],
                'preview_url' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%)',
            ],
            [
                'name' => 'Global Sourcing',
                'vertical' => 'Supply Chain',
                'config' => [
                    'brandName' => 'Global Sourcing',
                    'primaryColor' => '#4338ca',
                    'accentColor' => '#1e1b4b',
                    'headingFont' => 'Plus Jakarta Sans',
                    'heroHeading' => 'Global Logistics, Simplified',
                    'heroSubtext' => 'Connect with suppliers and manage your supply chain seamlessly.',
                    'navStyle' => 'minimal',
                ],
                'preview_url' => 'linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%)',
            ],
            [
                'name' => 'Heritage Craft',
                'vertical' => 'Artisan',
                'config' => [
                    'brandName' => 'Heritage Craft',
                    'primaryColor' => '#c84b31',
                    'accentColor' => '#4a1942',
                    'headingFont' => 'Montserrat',
                    'heroHeading' => 'Timeless Traditions',
                    'heroSubtext' => 'Authentic handmade items crafted with love and care.',
                    'navStyle' => 'centered',
                ],
                'preview_url' => 'linear-gradient(135deg, #4a1942 0%, #6b2d5b 50%, #c84b31 100%)',
            ],
        ];

        foreach ($themes as $theme) {
            Theme::updateOrCreate(['name' => $theme['name']], $theme);
        }
    }
}
