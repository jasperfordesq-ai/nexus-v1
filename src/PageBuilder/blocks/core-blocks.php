<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Core Block Registrations
 *
 * Registers all built-in page builder blocks
 */

use Nexus\PageBuilder\BlockRegistry;
use Nexus\PageBuilder\Renderers;

// ============================================
// LAYOUT BLOCKS
// ============================================

BlockRegistry::register('hero', [
    'label' => 'Hero Section',
    'icon' => 'fa-mountain',
    'category' => 'layout',
    'description' => 'Full-width banner with title, subtitle, and call-to-action',
    'defaults' => [
        'title' => 'Welcome',
        'subtitle' => '',
        'backgroundImage' => '',
        'backgroundOverlay' => '0.4',
        'alignment' => 'center',
        'height' => 'medium',
        'buttonText' => '',
        'buttonUrl' => ''
    ],
    'fields' => [
        'title' => [
            'type' => 'text',
            'label' => 'Title',
            'required' => true
        ],
        'subtitle' => [
            'type' => 'textarea',
            'label' => 'Subtitle',
            'rows' => 3
        ],
        'backgroundImage' => [
            'type' => 'image',
            'label' => 'Background Image'
        ],
        'backgroundOverlay' => [
            'type' => 'range',
            'label' => 'Overlay Darkness',
            'min' => '0',
            'max' => '1',
            'step' => '0.1'
        ],
        'alignment' => [
            'type' => 'select',
            'label' => 'Text Alignment',
            'options' => [
                'left' => 'Left',
                'center' => 'Center',
                'right' => 'Right'
            ]
        ],
        'height' => [
            'type' => 'select',
            'label' => 'Section Height',
            'options' => [
                'small' => 'Small (300px)',
                'medium' => 'Medium (500px)',
                'large' => 'Large (700px)',
                'full' => 'Full Screen'
            ]
        ],
        'buttonText' => [
            'type' => 'text',
            'label' => 'Button Text'
        ],
        'buttonUrl' => [
            'type' => 'text',
            'label' => 'Button URL'
        ]
    ]
]);

BlockRegistry::registerRenderer('hero', new Renderers\HeroBlockRenderer());

// ============================================
// CONTENT BLOCKS
// ============================================

BlockRegistry::register('richtext', [
    'label' => 'Rich Text',
    'icon' => 'fa-align-left',
    'category' => 'content',
    'description' => 'Formatted text content with WYSIWYG editor',
    'defaults' => [
        'content' => '<p>Enter your content here...</p>',
        'width' => 'normal',
        'padding' => 'normal'
    ],
    'fields' => [
        'content' => [
            'type' => 'wysiwyg',
            'label' => 'Content',
            'required' => true
        ],
        'width' => [
            'type' => 'select',
            'label' => 'Content Width',
            'options' => [
                'narrow' => 'Narrow (600px)',
                'normal' => 'Normal (800px)',
                'wide' => 'Wide (1200px)',
                'full' => 'Full Width'
            ]
        ],
        'padding' => [
            'type' => 'select',
            'label' => 'Vertical Padding',
            'options' => [
                'none' => 'None',
                'small' => 'Small',
                'normal' => 'Normal',
                'large' => 'Large'
            ]
        ]
    ]
]);

BlockRegistry::registerRenderer('richtext', new Renderers\RichTextBlockRenderer());

// ============================================
// DYNAMIC BLOCKS (Smart Blocks)
// ============================================

BlockRegistry::register('members-grid', [
    'label' => 'Members Grid',
    'icon' => 'fa-users',
    'category' => 'dynamic',
    'description' => 'Display member profiles from your database',
    'defaults' => [
        'limit' => 6,
        'columns' => 3,
        'orderBy' => 'created_at',
        'filter' => 'all',
        'showBio' => true,
        'showAvatar' => true
    ],
    'fields' => [
        'limit' => [
            'type' => 'number',
            'label' => 'Number of Members',
            'min' => 1,
            'max' => 100,
            'default' => 6
        ],
        'columns' => [
            'type' => 'select',
            'label' => 'Columns',
            'options' => [
                1 => '1 Column',
                2 => '2 Columns',
                3 => '3 Columns',
                4 => '4 Columns',
                6 => '6 Columns'
            ]
        ],
        'orderBy' => [
            'type' => 'select',
            'label' => 'Order By',
            'options' => [
                'created_at' => 'Newest First',
                'name' => 'Alphabetical',
                'last_active_at' => 'Most Active'
            ]
        ],
        'filter' => [
            'type' => 'select',
            'label' => 'Filter',
            'options' => [
                'all' => 'All Members',
                'verified' => 'Verified Only',
                'featured' => 'Featured Only',
                'active' => 'Active (Last 30 Days)'
            ]
        ],
        'showBio' => [
            'type' => 'checkbox',
            'label' => 'Show Bio'
        ],
        'showAvatar' => [
            'type' => 'checkbox',
            'label' => 'Show Avatar'
        ]
    ]
]);

BlockRegistry::registerRenderer('members-grid', new Renderers\MembersGridRenderer());

// ============================================
// ADDITIONAL CONTENT BLOCKS
// ============================================

BlockRegistry::register('image', [
    'label' => 'Image',
    'icon' => 'fa-image',
    'category' => 'content',
    'description' => 'Single image with optional caption and link',
    'defaults' => [
        'imageUrl' => '',
        'alt' => '',
        'caption' => '',
        'width' => 'normal',
        'alignment' => 'center',
        'linkUrl' => ''
    ],
    'fields' => [
        'imageUrl' => [
            'type' => 'text',
            'label' => 'Image URL',
            'required' => true
        ],
        'alt' => [
            'type' => 'text',
            'label' => 'Alt Text (for accessibility)'
        ],
        'caption' => [
            'type' => 'text',
            'label' => 'Caption (optional)'
        ],
        'width' => [
            'type' => 'select',
            'label' => 'Image Width',
            'options' => [
                'small' => 'Small (400px)',
                'normal' => 'Normal (600px)',
                'large' => 'Large (900px)',
                'full' => 'Full Width'
            ]
        ],
        'alignment' => [
            'type' => 'select',
            'label' => 'Alignment',
            'options' => [
                'left' => 'Left',
                'center' => 'Center',
                'right' => 'Right'
            ]
        ],
        'linkUrl' => [
            'type' => 'text',
            'label' => 'Link URL (optional)'
        ]
    ]
]);

BlockRegistry::registerRenderer('image', new Renderers\ImageBlockRenderer());

BlockRegistry::register('button', [
    'label' => 'Button / CTA',
    'icon' => 'fa-hand-pointer',
    'category' => 'content',
    'description' => 'Call-to-action button with customizable styling',
    'defaults' => [
        'text' => 'Click Here',
        'url' => '#',
        'style' => 'primary',
        'size' => 'medium',
        'alignment' => 'center',
        'openInNewTab' => false,
        'icon' => ''
    ],
    'fields' => [
        'text' => [
            'type' => 'text',
            'label' => 'Button Text',
            'required' => true
        ],
        'url' => [
            'type' => 'text',
            'label' => 'Button URL',
            'required' => true
        ],
        'style' => [
            'type' => 'select',
            'label' => 'Button Style',
            'options' => [
                'primary' => 'Primary (Blue)',
                'secondary' => 'Secondary (Gray)',
                'outline' => 'Outline',
                'danger' => 'Danger (Red)'
            ]
        ],
        'size' => [
            'type' => 'select',
            'label' => 'Button Size',
            'options' => [
                'small' => 'Small',
                'medium' => 'Medium',
                'large' => 'Large'
            ]
        ],
        'alignment' => [
            'type' => 'select',
            'label' => 'Alignment',
            'options' => [
                'left' => 'Left',
                'center' => 'Center',
                'right' => 'Right'
            ]
        ],
        'icon' => [
            'type' => 'text',
            'label' => 'Icon (Font Awesome name, e.g., "arrow-right")'
        ],
        'openInNewTab' => [
            'type' => 'checkbox',
            'label' => 'Open in New Tab'
        ]
    ]
]);

BlockRegistry::registerRenderer('button', new Renderers\ButtonBlockRenderer());

BlockRegistry::register('video', [
    'label' => 'Video Embed',
    'icon' => 'fa-video',
    'category' => 'content',
    'description' => 'Embed YouTube, Vimeo, or native video',
    'defaults' => [
        'videoUrl' => '',
        'width' => 'normal',
        'aspectRatio' => '16-9'
    ],
    'fields' => [
        'videoUrl' => [
            'type' => 'text',
            'label' => 'Video URL',
            'required' => true
        ],
        'width' => [
            'type' => 'select',
            'label' => 'Video Width',
            'options' => [
                'narrow' => 'Narrow (600px)',
                'normal' => 'Normal (800px)',
                'wide' => 'Wide (1200px)',
                'full' => 'Full Width'
            ]
        ],
        'aspectRatio' => [
            'type' => 'select',
            'label' => 'Aspect Ratio',
            'options' => [
                '16-9' => '16:9 (Widescreen)',
                '4-3' => '4:3 (Standard)',
                '1-1' => '1:1 (Square)'
            ]
        ]
    ]
]);

BlockRegistry::registerRenderer('video', new Renderers\VideoBlockRenderer());

// ============================================
// LAYOUT BLOCKS
// ============================================

BlockRegistry::register('columns', [
    'label' => 'Columns',
    'icon' => 'fa-columns',
    'category' => 'layout',
    'description' => 'Multi-column layout for organizing content',
    'defaults' => [
        'columnCount' => 2,
        'gap' => 'normal',
        'columns' => ['<p>Column 1</p>', '<p>Column 2</p>']
    ],
    'fields' => [
        'columnCount' => [
            'type' => 'select',
            'label' => 'Number of Columns',
            'options' => [
                2 => '2 Columns',
                3 => '3 Columns',
                4 => '4 Columns'
            ]
        ],
        'gap' => [
            'type' => 'select',
            'label' => 'Gap Between Columns',
            'options' => [
                'none' => 'None',
                'small' => 'Small',
                'normal' => 'Normal',
                'large' => 'Large'
            ]
        ],
        'columns' => [
            'type' => 'textarea',
            'label' => 'Column Content (HTML)',
            'rows' => 10
        ]
    ]
]);

BlockRegistry::registerRenderer('columns', new Renderers\ColumnsBlockRenderer());

// ============================================
// MORE DYNAMIC BLOCKS (Smart Blocks)
// ============================================

BlockRegistry::register('groups-grid', [
    'label' => 'Groups Grid',
    'icon' => 'fa-users-rectangle',
    'category' => 'dynamic',
    'description' => 'Display groups from your database',
    'defaults' => [
        'limit' => 6,
        'columns' => 3,
        'orderBy' => 'created_at',
        'filter' => 'all',
        'showDescription' => true,
        'showMemberCount' => true
    ],
    'fields' => [
        'limit' => [
            'type' => 'number',
            'label' => 'Number of Groups',
            'min' => 1,
            'max' => 100,
            'default' => 6
        ],
        'columns' => [
            'type' => 'select',
            'label' => 'Columns',
            'options' => [
                1 => '1 Column',
                2 => '2 Columns',
                3 => '3 Columns',
                4 => '4 Columns',
                6 => '6 Columns'
            ]
        ],
        'orderBy' => [
            'type' => 'select',
            'label' => 'Order By',
            'options' => [
                'created_at' => 'Newest First',
                'name' => 'Alphabetical',
                'member_count' => 'Most Members'
            ]
        ],
        'filter' => [
            'type' => 'select',
            'label' => 'Filter',
            'options' => [
                'all' => 'All Groups',
                'public' => 'Public Only',
                'private' => 'Private Only',
                'featured' => 'Featured'
            ]
        ],
        'showDescription' => [
            'type' => 'checkbox',
            'label' => 'Show Description'
        ],
        'showMemberCount' => [
            'type' => 'checkbox',
            'label' => 'Show Member Count'
        ]
    ]
]);

BlockRegistry::registerRenderer('groups-grid', new Renderers\GroupsGridRenderer());

BlockRegistry::register('listings-grid', [
    'label' => 'Listings Grid',
    'icon' => 'fa-rectangle-list',
    'category' => 'dynamic',
    'description' => 'Display listings/classifieds from your database',
    'defaults' => [
        'limit' => 6,
        'columns' => 3,
        'orderBy' => 'created_at',
        'categoryId' => 0,
        'showPrice' => true,
        'showLocation' => true
    ],
    'fields' => [
        'limit' => [
            'type' => 'number',
            'label' => 'Number of Listings',
            'min' => 1,
            'max' => 100,
            'default' => 6
        ],
        'columns' => [
            'type' => 'select',
            'label' => 'Columns',
            'options' => [
                1 => '1 Column',
                2 => '2 Columns',
                3 => '3 Columns',
                4 => '4 Columns',
                6 => '6 Columns'
            ]
        ],
        'orderBy' => [
            'type' => 'select',
            'label' => 'Order By',
            'options' => [
                'created_at' => 'Newest First',
                'price' => 'Price',
                'title' => 'Title'
            ]
        ],
        'categoryId' => [
            'type' => 'number',
            'label' => 'Category ID (0 = all)',
            'min' => 0,
            'default' => 0
        ],
        'showPrice' => [
            'type' => 'checkbox',
            'label' => 'Show Price'
        ],
        'showLocation' => [
            'type' => 'checkbox',
            'label' => 'Show Location'
        ]
    ]
]);

BlockRegistry::registerRenderer('listings-grid', new Renderers\ListingsGridRenderer());

// ============================================
// INTERACTIVE BLOCKS
// ============================================

BlockRegistry::register('accordion', [
    'label' => 'Accordion / FAQ',
    'icon' => 'fa-list-check',
    'category' => 'content',
    'description' => 'Collapsible accordion sections for FAQs or content',
    'defaults' => [
        'title' => 'Frequently Asked Questions',
        'style' => 'default',
        'allowMultiple' => false,
        'items' => [
            ['question' => 'What is this?', 'answer' => '<p>This is an accordion item.</p>'],
            ['question' => 'How does it work?', 'answer' => '<p>Click to expand and collapse.</p>']
        ]
    ],
    'fields' => [
        'title' => [
            'type' => 'text',
            'label' => 'Section Title'
        ],
        'style' => [
            'type' => 'select',
            'label' => 'Style',
            'options' => [
                'default' => 'Default',
                'bordered' => 'Bordered',
                'minimal' => 'Minimal'
            ]
        ],
        'allowMultiple' => [
            'type' => 'checkbox',
            'label' => 'Allow Multiple Items Open'
        ],
        'items' => [
            'type' => 'repeater',
            'label' => 'Accordion Items',
            'help' => 'Add question/answer pairs'
        ]
    ]
]);

BlockRegistry::registerRenderer('accordion', new Renderers\AccordionBlockRenderer());

BlockRegistry::register('testimonials', [
    'label' => 'Testimonials',
    'icon' => 'fa-quote-left',
    'category' => 'content',
    'description' => 'Customer testimonials and reviews',
    'defaults' => [
        'title' => 'What Our Customers Say',
        'columns' => 3,
        'style' => 'cards',
        'testimonials' => [
            [
                'quote' => 'This product changed my life!',
                'name' => 'John Doe',
                'position' => 'CEO',
                'company' => 'Acme Corp',
                'avatar' => '',
                'rating' => 5
            ]
        ]
    ],
    'fields' => [
        'title' => [
            'type' => 'text',
            'label' => 'Section Title'
        ],
        'columns' => [
            'type' => 'select',
            'label' => 'Columns',
            'options' => [
                1 => '1 Column',
                2 => '2 Columns',
                3 => '3 Columns',
                4 => '4 Columns'
            ]
        ],
        'style' => [
            'type' => 'select',
            'label' => 'Style',
            'options' => [
                'cards' => 'Cards',
                'minimal' => 'Minimal',
                'bordered' => 'Bordered'
            ]
        ],
        'testimonials' => [
            'type' => 'repeater',
            'label' => 'Testimonials',
            'help' => 'Add customer testimonials'
        ]
    ]
]);

BlockRegistry::registerRenderer('testimonials', new Renderers\TestimonialsBlockRenderer());

BlockRegistry::register('cta-cards', [
    'label' => 'CTA Cards',
    'icon' => 'fa-grip',
    'category' => 'content',
    'description' => 'Call-to-action cards with icons',
    'defaults' => [
        'columns' => 3,
        'style' => 'default',
        'cards' => [
            [
                'icon' => 'fa-rocket',
                'title' => 'Fast Setup',
                'description' => 'Get started in minutes',
                'buttonText' => 'Learn More',
                'buttonUrl' => '#',
                'iconColor' => 'primary'
            ],
            [
                'icon' => 'fa-shield-halved',
                'title' => 'Secure',
                'description' => 'Bank-level encryption',
                'buttonText' => 'Learn More',
                'buttonUrl' => '#',
                'iconColor' => 'secondary'
            ],
            [
                'icon' => 'fa-headset',
                'title' => '24/7 Support',
                'description' => 'Always here to help',
                'buttonText' => 'Contact Us',
                'buttonUrl' => '#',
                'iconColor' => 'accent'
            ]
        ]
    ],
    'fields' => [
        'columns' => [
            'type' => 'select',
            'label' => 'Columns',
            'options' => [
                2 => '2 Columns',
                3 => '3 Columns',
                4 => '4 Columns'
            ]
        ],
        'style' => [
            'type' => 'select',
            'label' => 'Style',
            'options' => [
                'default' => 'Default',
                'elevated' => 'Elevated',
                'minimal' => 'Minimal'
            ]
        ],
        'cards' => [
            'type' => 'repeater',
            'label' => 'Cards',
            'help' => 'Add feature/CTA cards'
        ]
    ]
]);

BlockRegistry::registerRenderer('cta-cards', new Renderers\CtaCardBlockRenderer());

BlockRegistry::register('stats', [
    'label' => 'Stats / Counters',
    'icon' => 'fa-chart-line',
    'category' => 'content',
    'description' => 'Animated statistics and counters',
    'defaults' => [
        'columns' => 4,
        'style' => 'default',
        'animated' => true,
        'stats' => [
            [
                'number' => '10000',
                'label' => 'Happy Customers',
                'suffix' => '+',
                'prefix' => '',
                'icon' => 'fa-users',
                'color' => 'primary'
            ],
            [
                'number' => '50',
                'label' => 'Countries',
                'suffix' => '+',
                'prefix' => '',
                'icon' => 'fa-globe',
                'color' => 'secondary'
            ],
            [
                'number' => '99',
                'label' => 'Satisfaction Rate',
                'suffix' => '%',
                'prefix' => '',
                'icon' => 'fa-star',
                'color' => 'accent'
            ],
            [
                'number' => '24',
                'label' => 'Support',
                'suffix' => '/7',
                'prefix' => '',
                'icon' => 'fa-headset',
                'color' => 'success'
            ]
        ]
    ],
    'fields' => [
        'columns' => [
            'type' => 'select',
            'label' => 'Columns',
            'options' => [
                2 => '2 Columns',
                3 => '3 Columns',
                4 => '4 Columns',
                5 => '5 Columns'
            ]
        ],
        'style' => [
            'type' => 'select',
            'label' => 'Style',
            'options' => [
                'default' => 'Default',
                'minimal' => 'Minimal',
                'bordered' => 'Bordered'
            ]
        ],
        'animated' => [
            'type' => 'checkbox',
            'label' => 'Animate on Scroll'
        ],
        'stats' => [
            'type' => 'repeater',
            'label' => 'Statistics',
            'help' => 'Add stats to display'
        ]
    ]
]);

BlockRegistry::registerRenderer('stats', new Renderers\StatsBlockRenderer());

BlockRegistry::register('spacer', [
    'label' => 'Spacer',
    'icon' => 'fa-arrows-up-down',
    'category' => 'layout',
    'description' => 'Add vertical spacing between blocks',
    'defaults' => [
        'height' => 'medium'
    ],
    'fields' => [
        'height' => [
            'type' => 'select',
            'label' => 'Height',
            'options' => [
                'small' => 'Small (20px)',
                'medium' => 'Medium (40px)',
                'large' => 'Large (60px)',
                'xlarge' => 'Extra Large (100px)'
            ]
        ]
    ]
]);

BlockRegistry::registerRenderer('spacer', new Renderers\SpacerBlockRenderer());
