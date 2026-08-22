
-- =====================================================
-- Seed data for ACTIVITY table
-- Run this AFTER seed-cities.sql (City_ID references must exist)
-- City_ID mapping (from seed-cities.sql, in insert order):
-- 1 Goa, 2 Jaipur, 3 Manali, 4 Paris, 5 Nice, 6 Tokyo, 7 Kyoto,
-- 8 Rome, 9 Venice, 10 Bangkok, 11 Phuket, 12 New York,
-- 13 Los Angeles, 14 Dubai, 15 Barcelona, 16 Bali, 17 Zurich
-- =====================================================

INSERT INTO ACTIVITY (City_ID, Activity_Name, Activity_Type, Description, Duration, Estimated_Cost, Image) VALUES
-- Goa
(1, 'Baga Beach Sunset Cruise', 'sightseeing', 'Evening boat cruise with music and views of Baga coastline.', 2.00, 1200, ''),
(1, 'Beach Shack Seafood Dinner', 'food', 'Fresh grilled seafood at a beachside shack.', 1.50, 900, ''),
(1, 'Parasailing at Candolim', 'adventure', 'High-flying parasailing over the Arabian Sea.', 1.00, 1500, ''),

-- Jaipur
(2, 'Amber Fort Heritage Walk', 'sightseeing', 'Guided walking tour of the iconic Amber Fort.', 3.00, 800, ''),
(2, 'Rajasthani Thali Experience', 'food', 'Traditional multi-course Rajasthani thali dinner.', 1.50, 700, ''),
(2, 'Hot Air Balloon Ride', 'adventure', 'Sunrise hot air balloon ride over Jaipur.', 2.00, 8500, ''),

-- Manali
(3, 'Solang Valley Trek', 'adventure', 'Guided trek through Solang Valley with mountain views.', 4.00, 1000, ''),
(3, 'Old Manali Cafe Hopping', 'food', 'Explore riverside cafes serving Israeli and local food.', 2.00, 600, ''),
(3, 'Hadimba Temple Visit', 'sightseeing', 'Visit the ancient cedar-wood temple in the forest.', 1.00, 100, ''),

-- Paris
(4, 'Eiffel Tower Summit Access', 'sightseeing', 'Skip-the-line access to the top of the Eiffel Tower.', 2.00, 3500, ''),
(4, 'Louvre Museum Guided Tour', 'sightseeing', 'Guided tour of the Louvre highlighting the Mona Lisa and more.', 3.00, 2800, ''),
(4, 'Seine River Dinner Cruise', 'food', 'Fine dining cruise along the Seine with city views.', 2.50, 6000, ''),

-- Nice
(5, 'Promenade des Anglais Bike Tour', 'adventure', 'Cycle along the famous Nice waterfront.', 2.00, 1200, ''),
(5, 'French Riviera Cooking Class', 'food', 'Hands-on class making Provençal dishes.', 3.00, 3200, ''),

-- Tokyo
(6, 'Shibuya & Harajuku Walking Tour', 'sightseeing', 'Explore Tokyo''s trendiest neighborhoods with a local guide.', 3.00, 1800, ''),
(6, 'Sushi Making Workshop', 'food', 'Learn to make authentic sushi from a Tokyo chef.', 2.00, 4500, ''),
(6, 'teamLab Digital Art Museum', 'sightseeing', 'Immersive digital art installations.', 2.50, 2200, ''),

-- Kyoto
(7, 'Fushimi Inari Shrine Hike', 'sightseeing', 'Walk through thousands of iconic red torii gates.', 2.50, 0, ''),
(7, 'Traditional Kaiseki Dinner', 'food', 'Multi-course Japanese haute cuisine experience.', 2.00, 5500, ''),
(7, 'Arashiyama Bamboo Grove Walk', 'sightseeing', 'Stroll through the towering bamboo forest.', 1.50, 300, ''),

-- Rome
(8, 'Colosseum Underground Tour', 'sightseeing', 'Exclusive access to the Colosseum''s underground chambers.', 3.00, 4200, ''),
(8, 'Roman Pasta Making Class', 'food', 'Learn to make fresh pasta from scratch.', 2.50, 3800, ''),
(8, 'Vatican Museums & Sistine Chapel', 'sightseeing', 'Guided tour of the Vatican Museums and Sistine Chapel.', 3.50, 4000, ''),

-- Venice
(9, 'Gondola Ride Through Canals', 'sightseeing', 'Classic gondola ride through Venice''s waterways.', 1.00, 4500, ''),
(9, 'Venetian Cicchetti Food Tour', 'food', 'Sample small plates and wine across local bacari.', 2.50, 3000, ''),

-- Bangkok
(10, 'Grand Palace & Wat Phra Kaew', 'sightseeing', 'Visit Thailand''s most sacred temple complex.', 2.50, 1500, ''),
(10, 'Street Food Night Tour', 'food', 'Guided tour of Bangkok''s best street food stalls.', 3.00, 1800, ''),
(10, 'Muay Thai Class', 'adventure', 'Beginner-friendly Muay Thai training session.', 1.50, 1200, ''),

-- Phuket
(11, 'Phi Phi Islands Speedboat Tour', 'adventure', 'Full-day speedboat tour to the Phi Phi Islands.', 6.00, 3500, ''),
(11, 'Thai Cooking Class', 'food', 'Hands-on Thai cooking class with market visit.', 3.00, 2000, ''),

-- New York
(12, 'Statue of Liberty & Ellis Island', 'sightseeing', 'Ferry tour to Liberty Island and Ellis Island museum.', 4.00, 2800, ''),
(12, 'Broadway Show Ticket', 'sightseeing', 'Evening Broadway musical experience.', 2.50, 9500, ''),
(12, 'NYC Pizza Walking Tour', 'food', 'Sample iconic NYC pizza slices across Manhattan.', 2.00, 2200, ''),

-- Los Angeles
(13, 'Hollywood Sign Hike', 'adventure', 'Scenic hike up to the iconic Hollywood Sign viewpoint.', 3.00, 0, ''),
(13, 'Santa Monica Pier & Beach', 'sightseeing', 'Explore the pier, rides and beach boardwalk.', 2.00, 500, ''),
(13, 'In-N-Out & Food Truck Crawl', 'food', 'Taste LA''s famous burgers and food truck scene.', 2.50, 1500, ''),

-- Dubai
(14, 'Burj Khalifa At The Top', 'sightseeing', 'Observation deck access on the world''s tallest building.', 1.50, 5500, ''),
(14, 'Desert Safari with BBQ Dinner', 'adventure', 'Dune bashing, camel ride and BBQ dinner under the stars.', 5.00, 4500, ''),
(14, 'Dubai Mall & Fountain Show', 'sightseeing', 'Shopping and evening fountain show viewing.', 2.00, 0, ''),

-- Barcelona
(15, 'Sagrada Familia Guided Tour', 'sightseeing', 'Skip-the-line tour of Gaudi''s masterpiece.', 2.00, 3200, ''),
(15, 'Tapas & Wine Walking Tour', 'food', 'Sample authentic tapas across the Gothic Quarter.', 3.00, 3800, ''),
(15, 'Park Guell Exploration', 'sightseeing', 'Explore Gaudi''s colorful park with city views.', 1.50, 1000, ''),

-- Bali
(16, 'Ubud Rice Terrace & Temple Tour', 'sightseeing', 'Visit Tegalalang rice terraces and Tirta Empul temple.', 4.00, 1800, ''),
(16, 'Balinese Cooking Class', 'food', 'Hands-on class with market visit and traditional recipes.', 3.00, 2500, ''),
(16, 'Surfing Lesson at Kuta Beach', 'adventure', 'Beginner surf lesson with instructor.', 2.00, 2000, ''),

-- Zurich
(17, 'Lake Zurich Boat Cruise', 'sightseeing', 'Scenic cruise along Lake Zurich.', 1.50, 2800, ''),
(17, 'Swiss Chocolate Tasting Tour', 'food', 'Guided tasting tour through Zurich''s chocolate shops.', 2.00, 3500, '');
