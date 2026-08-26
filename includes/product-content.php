<?php
/**
 * Shared product marketing copy.
 * Single source of truth for product descriptions and feature bullets.
 * Keys match product names in the live `products` table EXACTLY (27 products).
 * Used by: customer/store-product.php, customer/product-details.php,
 *          admin/products.php, admin/product-form.php
 */
$PRODUCT_CONTENT = [

    // ===== Custom Merchandise =====
    'Baseball Cap' => [
        'description' => 'Embroidered Custom Baseball Caps — Top off any outfit with embroidered or printed logos on adjustable everyday wear—perfect for staff uniforms, sports teams, merch lines, giveaways, and brand visibility.',
        'features' => [
            'Embroidered or printed logo',
            'Adjustable one-size fit',
            'Durable everyday wear',
            'Multiple cap styles & colors',
            'Clean, professional stitching',
            'Excellent brand visibility',
        ],
    ],
    'Custom Mug' => [
        'description' => 'High-Quality Custom Mug Printing — Create personalized mugs with vibrant, long-lasting prints—perfect for gifts, souvenirs, corporate giveaways, promotional items, and everyday use.',
        'features' => [
            'Premium 11oz ceramic',
            'Full-color wraparound print',
            'Dishwasher-safe finish',
            'Photo-quality reproduction',
            'Great gifts & giveaways',
            'Bulk pricing available',
        ],
    ],
    'Label Sticker' => [
        'description' => 'Waterproof Die-Cut Label Stickers — Stick strong on any surface with custom shapes and waterproof vivid printing—perfect for product labels, packaging, branding, small businesses, and promos.',
        'features' => [
            'Die-cut to any shape',
            'Waterproof & smudge-resistant',
            'Strong adhesive backing',
            'Vibrant full-color printing',
            'Perfect for packaging & branding',
            'Sheets or rolls available',
        ],
    ],
    'Mouse Pad' => [
        'description' => 'Custom Printed Mouse Pads — Brand every desk with vivid edge-to-edge prints on a non-slip rubber base—perfect for office giveaways, gaming setups, corporate gifts, promo merch, and workstations.',
        'features' => [
            'Non-slip rubber base',
            'Smooth precision tracking surface',
            'Vivid edge-to-edge print',
            'Easy-clean surface',
            'Standard & extended sizes',
            'Budget-friendly bulk orders',
        ],
    ],
    'Tote Bag' => [
        'description' => 'Eco-Friendly Custom Tote Bags — Carry your brand everywhere with sturdy reusable bags and long-lasting prints—perfect for trade shows, bazaars, groceries, corporate giveaways, and eco-conscious shoppers.',
        'features' => [
            'Eco-friendly reusable material',
            'Strong stitched handles',
            'Large printable area',
            'Vivid long-lasting print',
            'Everyday-useful giveaway',
            'Bulk order discounts',
        ],
    ],
    'Tumbler' => [
        'description' => 'Premium Custom Tumblers — Keep drinks hot or ice-cold for hours in insulated stainless steel with sleek custom print—perfect for gifts, corporate giveaways, coffee lovers, branded merch, and daily use.',
        'features' => [
            'Double-wall insulated stainless steel',
            'Keeps drinks hot or cold for hours',
            'Travel-friendly size',
            'Long-lasting custom print',
            'Leak-resistant lid',
            'Ideal corporate giveaway',
        ],
    ],

    // ===== Apparel & Sublimation =====
    'Basketball Jersey (Upper Only)' => [
        'description' => 'Game-Day Basketball Jerseys (Upper Only) — Suit up in breathable moisture-wicking fabric with custom colors, names, and numbers—perfect for leagues, school teams, barangay tournaments, and budget-friendly team sets.',
        'features' => [
            'Moisture-wicking performance fabric',
            'Custom names & numbers',
            'Vibrant sublimated colors',
            'Chinese-collar or classic cut',
            'Budget-friendly team pricing',
            'Easy reorders for new players',
        ],
    ],
    'Basketball Jersey (Upper/Short)' => [
        'description' => 'Complete Basketball Jersey Sets (Upper/Short) — Get the full uniform look with matched jersey and shorts in vibrant sublimated color—perfect for leagues, team uniforms, tournaments, and squads that want a professional edge.',
        'features' => [
            'Matched jersey + shorts set',
            'Moisture-wicking performance fabric',
            'Custom names & numbers',
            'Fully matched team sets',
            'Vibrant long-lasting sublimation',
            'Pro-style collars available',
        ],
    ],
    'Basketball Jersey (Upper/Short/Warmer)' => [
        'description' => 'Full Basketball Uniform Packages (Upper/Short/Warmer) — Jersey, shorts, and warmer fully customized in one package—perfect for official lineups, varsity teams, and championship-ready presentation.',
        'features' => [
            'Complete 3-piece package',
            'Jersey, shorts & warmer included',
            'Custom names & numbers throughout',
            'Premium heavyweight warmer',
            'Fully matched team colors',
            'Championship-ready look',
        ],
    ],
    'Chinese Collar' => [
        'description' => 'Chinese Collar Jersey Printing — Stay sharp and comfortable with the classic Chinese-collar cut in moisture-wicking sublimated fabric—perfect for corporate sports fests, team uniforms, events, and everyday play.',
        'features' => [
            'Classic Chinese-collar cut',
            'Moisture-wicking comfort',
            'Vibrant sublimation printing',
            'Professional clean look',
            'Great for corporate sports fests',
            'Team sets available',
        ],
    ],
    'Long Sleeve' => [
        'description' => 'Custom Sublimation Long Sleeves — Show more of your design with edge-to-edge prints on breathable quick-dry fabric—perfect for teams, org shirts, events, merch lines, and all-day comfort.',
        'features' => [
            'Edge-to-edge sublimation printing',
            'Breathable quick-dry fabric',
            'Full-length sleeve designs',
            'Prints never crack or peel',
            'Great for orgs & merch lines',
            'Comfortable all-day fit',
        ],
    ],
    'Polo Shirt' => [
        'description' => 'Custom Sublimation Polo Shirts — Look sharp with zipper or button polos in crisp fade-resistant color—perfect for staff uniforms, corporate events, sports clubs, company branding, and daily wear.',
        'features' => [
            'Zipper or button placket',
            'Professional collared look',
            'Fade-resistant sublimation print',
            'Moisture-wicking comfort',
            'Ideal for uniforms & company events',
            'Multiple colorways available',
        ],
    ],
    'V / Round Neck T-Shirt' => [
        'description' => 'Custom V / Round Neck T-Shirts — Enjoy edge-to-edge color on breathable quick-dry fabric that never cracks or peels—perfect for teams, barkada shirts, events, giveaways, and everyday comfort.',
        'features' => [
            'V-neck or round-neck options',
            'Edge-to-edge sublimation printing',
            'Breathable quick-dry fabric',
            'Prints never crack or peel',
            'Great for teams & events',
            'Sizes for all builds',
        ],
    ],
    'Varsity Jacket / Corporate Jacket' => [
        'description' => 'Custom Varsity & Corporate Jackets — Wear your identity with premium heavyweight fabric and full-custom designs—perfect for teams, schools, batch jackets, company uniforms, and statement outerwear.',
        'features' => [
            'Premium heavyweight fabric',
            'Full-custom front & back design',
            'Durable reinforced stitching',
            'Vibrant long-lasting sublimation',
            'Varsity & corporate styles',
            'Perfect for batch & company jackets',
        ],
    ],

    // ===== Business Cards =====
    'Calling card' => [
        'description' => 'Classic Custom Calling Cards — Share your contact details with clean, elegant design printed by the thousand—perfect for salons, agents, freelancers, events, and budget-friendly promotions.',
        'features' => [
            'Budget-friendly bulk of 1,000',
            'Smooth, elegant cardstock',
            'Crisp full-color printing',
            'Ideal for salons, agents & freelancers',
            'Multiple design revisions welcome',
        ],
    ],
    'Standard Business Cards' => [
        'description' => 'Affordable Professional Business Cards — Make every introduction count with crisp 350gsm cardstock and vibrant full-color printing—perfect for networking, small businesses, startups, and everyday professional use.',
        'features' => [
            'Premium 350gsm cardstock',
            'Full-color double-sided printing',
            'Sharp, smudge-free finish',
            'Best-value bulk packs',
            'Free layout assistance',
            'Ready in days, not weeks',
        ],
    ],

    // ===== Marketing Materials =====
    'Calendars' => [
        'description' => 'Custom Printed Calendars — Keep your brand visible every single day with vivid 12-month designs for wall or desk—perfect for corporate giveaways, client gifts, school fundraisers, and year-round promotion.',
        'features' => [
            'Wall or desk formats',
            '12-month custom layouts',
            'Logo on every page',
            'Spiral-bound, lays flat',
            'Durable premium paper',
            'Ideal corporate giveaway',
        ],
    ],
    'Full-color Flyers' => [
        'description' => 'Eye-Catching Full-Color Flyers — Grab attention fast with bold colors on your choice of glossy or matte stock—perfect for grand openings, sales promos, announcements, events, and mass distribution.',
        'features' => [
            'Your choice of glossy or matte',
            'Vivid high-impact colors',
            'Lightweight, easy to distribute',
            'Great for promos & announcements',
            'Bulk value pricing',
            'Rush turnaround available',
        ],
    ],
    'Tri-Fold Brochures' => [
        'description' => 'Full-Color Tri-Fold Brochures — Tell your complete story with rich double-sided printing on quality paper—perfect for menus, service guides, event programs, product catalogs, and business promotions.',
        'features' => [
            'Full-color double-sided printing',
            'Precision tri-fold creasing',
            'Quality gloss or matte paper',
            'Perfect for menus & service guides',
            'Design support available',
            'Bulk runs welcome',
        ],
    ],

    // ===== Large Format & Signage =====
    'Panaflex' => [
        'description' => 'Flexible Panaflex Printing — Display bold, weather-resistant signage that bends without breaking—perfect for storefronts, promos, announcements, outdoor ads, and long-term displays.',
        'features' => [
            'Flexible, durable face material',
            'Weather-resistant vivid inks',
            'Any custom size',
            'Ideal for storefronts & lightboxes',
            'Fade-resistant large format',
            'Long-term outdoor display',
        ],
    ],
    'Stand Banners' => [
        'description' => 'Retractable Stand Banners — Set up professionally in seconds with a sleek portable stand and premium wrinkle-free print—perfect for trade shows, conferences, exhibits, lobbies, and on-the-go branding.',
        'features' => [
            'Smooth retractable mechanism',
            'Tool-free one-minute setup',
            'Lightweight & portable',
            'Premium wrinkle-free print',
            'Sturdy stable base',
            'Includes carrying bag',
        ],
    ],
    'Tarpaulins Signage' => [
        'description' => 'Heavy-Duty Tarpaulin Signage — Announce it big with tough all-weather tarpaulins in vivid large-format color—perfect for birthdays, fiestas, sales, campaigns, and outdoor announcements.',
        'features' => [
            'All-weather heavy-duty material',
            'Vivid large-format colors',
            'Any size, any design',
            'Hemmed edges with eyelets',
            'Fade-proof outdoor printing',
            'Fast same-week production',
        ],
    ],
    'Vinyl Banners' => [
        'description' => 'Durable Custom Vinyl Banners — Command attention indoors and out with weather-resistant prints in any size—perfect for store openings, fiestas, birthdays, sales events, and outdoor advertising.',
        'features' => [
            'Indoor & outdoor durability',
            'UV- and water-resistant inks',
            'Any custom size',
            'Reinforced edges with eyelets',
            'Fade-proof vibrant printing',
            'Quick production time',
        ],
    ],

    // ===== Promotional Items & Giveaways =====
    'Lanyards' => [
        'description' => 'Custom Printed Lanyards — Wear your brand all day with durable edge-to-edge printing and sturdy clips—perfect for IDs, events, offices, schools, conventions, and low-cost bulk branding.',
        'features' => [
            'Edge-to-edge custom printing',
            'Durable strap material',
            'Sturdy metal clip included',
            'Comfortable all-day wear',
            'Lowest-cost bulk branding',
            'Ideal for events & offices',
        ],
    ],
    'PVC IDs & Cards' => [
        'description' => 'Professional PVC ID Cards — Print crisp double-sided IDs on durable scratch-resistant PVC—perfect for employee IDs, school IDs, membership cards, access cards, and bulk orders of 100.',
        'features' => [
            'Durable scratch-resistant PVC',
            'Crisp double-sided color printing',
            'Standard wallet-card size',
            'Optional QR codes & photo IDs',
            'Bulk sets available',
            'Long-lasting professional finish',
        ],
    ],
    'Umbrella' => [
        'description' => 'Branded Custom Umbrellas — Shine rain or shine with sturdy wind-resistant frames and vivid panel-to-panel printing—perfect for rainy-season promos, corporate gifts, events, giveaways, and mobile billboards.',
        'features' => [
            'Full-panel custom printing',
            'Wind-resistant sturdy frame',
            'Smooth automatic open',
            'Compact foldable options',
            'High-visibility brand placement',
            'Great rainy-season giveaway',
        ],
    ],

    // ===== Certificates & Documents =====
    'Certificate Printing' => [
        'description' => 'Elegant Custom Certificate Printing — Recognize achievement with ornate borders on premium paper ready for framing—perfect for schools, seminars, competitions, employee recognition, and awarding ceremonies.',
        'features' => [
            'Elegant ornate borders',
            'Premium certificate paper',
            'Crisp name & title printing',
            'Bulk runs available',
            'Foil & seal upgrade options',
            'Perfect for schools & events',
        ],
    ],
];
