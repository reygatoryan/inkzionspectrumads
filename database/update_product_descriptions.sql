-- ============================================================
-- Inkzion Spectrum Ads - Product Description Updates
-- Matched to the LIVE database's actual product names (27).
-- ============================================================
-- Purpose: Syncs products.description with the marketing copy
--          in includes/product-content.php
--
-- HOW TO RUN (INFINITYFREE LIVE SITE):
--   1. Go to https://control.infinityfree.net/ > MySQL Databases
--   2. Click "phpMyAdmin" next to your database
--      (e.g., if0_42406703_inkzion) and select it on the left
--   3. Click the "SQL" tab, paste this entire file, click "Go"
--
-- HOW TO RUN (LOCAL XAMPP):
--   1. Start MySQL, open http://localhost/phpmyadmin
--   2. Select `inkzion`, click the SQL tab, paste, Go
--
-- NOTE: The website already shows these descriptions without
--       running this file. This sync only keeps the raw
--       database records consistent (e.g., for admin views).
-- ============================================================

UPDATE products SET description = 'Embroidered Custom Baseball Caps — Top off any outfit with embroidered or printed logos on adjustable everyday wear—perfect for staff uniforms, sports teams, merch lines, giveaways, and brand visibility.' WHERE name = 'Baseball Cap';
UPDATE products SET description = 'Game-Day Basketball Jerseys (Upper Only) — Suit up in breathable moisture-wicking fabric with custom colors, names, and numbers—perfect for leagues, school teams, barangay tournaments, and budget-friendly team sets.' WHERE name = 'Basketball Jersey (Upper Only)';
UPDATE products SET description = 'Complete Basketball Jersey Sets (Upper/Short) — Get the full uniform look with matched jersey and shorts in vibrant sublimated color—perfect for leagues, team uniforms, tournaments, and squads that want a professional edge.' WHERE name = 'Basketball Jersey (Upper/Short)';
UPDATE products SET description = 'Full Basketball Uniform Packages (Upper/Short/Warmer) — Jersey, shorts, and warmer fully customized in one package—perfect for official lineups, varsity teams, and championship-ready presentation.' WHERE name = 'Basketball Jersey (Upper/Short/Warmer)';
UPDATE products SET description = 'Custom Printed Calendars — Keep your brand visible every single day with vivid 12-month designs for wall or desk—perfect for corporate giveaways, client gifts, school fundraisers, and year-round promotion.' WHERE name = 'Calendars';
UPDATE products SET description = 'Classic Custom Calling Cards — Share your contact details with clean, elegant design printed by the thousand—perfect for salons, agents, freelancers, events, and budget-friendly promotions.' WHERE name = 'Calling card';
UPDATE products SET description = 'Elegant Custom Certificate Printing — Recognize achievement with ornate borders on premium paper ready for framing—perfect for schools, seminars, competitions, employee recognition, and awarding ceremonies.' WHERE name = 'Certificate Printing';
UPDATE products SET description = 'Chinese Collar Jersey Printing — Stay sharp and comfortable with the classic Chinese-collar cut in moisture-wicking sublimated fabric—perfect for corporate sports fests, team uniforms, events, and everyday play.' WHERE name = 'Chinese Collar';
UPDATE products SET description = 'High-Quality Custom Mug Printing — Create personalized mugs with vibrant, long-lasting prints—perfect for gifts, souvenirs, corporate giveaways, promotional items, and everyday use.' WHERE name = 'Custom Mug';
UPDATE products SET description = 'Eye-Catching Full-Color Flyers — Grab attention fast with bold colors on your choice of glossy or matte stock—perfect for grand openings, sales promos, announcements, events, and mass distribution.' WHERE name = 'Full-color Flyers';
UPDATE products SET description = 'Waterproof Die-Cut Label Stickers — Stick strong on any surface with custom shapes and waterproof vivid printing—perfect for product labels, packaging, branding, small businesses, and promos.' WHERE name = 'Label Sticker';
UPDATE products SET description = 'Custom Printed Lanyards — Wear your brand all day with durable edge-to-edge printing and sturdy clips—perfect for IDs, events, offices, schools, conventions, and low-cost bulk branding.' WHERE name = 'Lanyards';
UPDATE products SET description = 'Custom Sublimation Long Sleeves — Show more of your design with edge-to-edge prints on breathable quick-dry fabric—perfect for teams, org shirts, events, merch lines, and all-day comfort.' WHERE name = 'Long Sleeve';
UPDATE products SET description = 'Custom Printed Mouse Pads — Brand every desk with vivid edge-to-edge prints on a non-slip rubber base—perfect for office giveaways, gaming setups, corporate gifts, promo merch, and workstations.' WHERE name = 'Mouse Pad';
UPDATE products SET description = 'Flexible Panaflex Printing — Display bold, weather-resistant signage that bends without breaking—perfect for storefronts, promos, announcements, outdoor ads, and long-term displays.' WHERE name = 'Panaflex';
UPDATE products SET description = 'Custom Sublimation Polo Shirts — Look sharp with zipper or button polos in crisp fade-resistant color—perfect for staff uniforms, corporate events, sports clubs, company branding, and daily wear.' WHERE name = 'Polo Shirt';
UPDATE products SET description = 'Professional PVC ID Cards — Print crisp double-sided IDs on durable scratch-resistant PVC—perfect for employee IDs, school IDs, membership cards, access cards, and bulk orders of 100.' WHERE name = 'PVC IDs & Cards';
UPDATE products SET description = 'Retractable Stand Banners — Set up professionally in seconds with a sleek portable stand and premium wrinkle-free print—perfect for trade shows, conferences, exhibits, lobbies, and on-the-go branding.' WHERE name = 'Stand Banners';
UPDATE products SET description = 'Affordable Professional Business Cards — Make every introduction count with crisp 350gsm cardstock and vibrant full-color printing—perfect for networking, small businesses, startups, and everyday professional use.' WHERE name = 'Standard Business Cards';
UPDATE products SET description = 'Heavy-Duty Tarpaulin Signage — Announce it big with tough all-weather tarpaulins in vivid large-format color—perfect for birthdays, fiestas, sales, campaigns, and outdoor announcements.' WHERE name = 'Tarpaulins Signage';
UPDATE products SET description = 'Eco-Friendly Custom Tote Bags — Carry your brand everywhere with sturdy reusable bags and long-lasting prints—perfect for trade shows, bazaars, groceries, corporate giveaways, and eco-conscious shoppers.' WHERE name = 'Tote Bag';
UPDATE products SET description = 'Full-Color Tri-Fold Brochures — Tell your complete story with rich double-sided printing on quality paper—perfect for menus, service guides, event programs, product catalogs, and business promotions.' WHERE name = 'Tri-Fold Brochures';
UPDATE products SET description = 'Premium Custom Tumblers — Keep drinks hot or ice-cold for hours in insulated stainless steel with sleek custom print—perfect for gifts, corporate giveaways, coffee lovers, branded merch, and daily use.' WHERE name = 'Tumbler';
UPDATE products SET description = 'Branded Custom Umbrellas — Shine rain or shine with sturdy wind-resistant frames and vivid panel-to-panel printing—perfect for rainy-season promos, corporate gifts, events, giveaways, and mobile billboards.' WHERE name = 'Umbrella';
UPDATE products SET description = 'Custom V / Round Neck T-Shirts — Enjoy edge-to-edge color on breathable quick-dry fabric that never cracks or peels—perfect for teams, barkada shirts, events, giveaways, and everyday comfort.' WHERE name = 'V / Round Neck T-Shirt';
UPDATE products SET description = 'Custom Varsity & Corporate Jackets — Wear your identity with premium heavyweight fabric and full-custom designs—perfect for teams, schools, batch jackets, company uniforms, and statement outerwear.' WHERE name = 'Varsity Jacket / Corporate Jacket';
UPDATE products SET description = 'Durable Custom Vinyl Banners — Command attention indoors and out with weather-resistant prints in any size—perfect for store openings, fiestas, birthdays, sales events, and outdoor advertising.' WHERE name = 'Vinyl Banners';

-- ============================================================
-- CHECKLIST: run after updates - every row should show a NEW
-- long description. Rows still showing old short text were
-- not matched (name spelling differs).
-- ============================================================

SELECT id, name, LEFT(description, 60) AS current_description FROM products ORDER BY name;
