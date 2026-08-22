-- =====================================================
-- Seed data for COUNTRY and CITY tables
-- Run this AFTER schema.sql - without this, city-search.php
-- and activity-search.php will show "No cities found"
-- =====================================================

INSERT INTO COUNTRY (Country_Name, Region) VALUES
('India', 'Asia'),
('France', 'Europe'),
('Japan', 'Asia'),
('Italy', 'Europe'),
('Thailand', 'Asia'),
('USA', 'North America'),
('UAE', 'Middle East'),
('Spain', 'Europe'),
('Indonesia', 'Asia'),
('Switzerland', 'Europe');

INSERT INTO CITY (Country_ID, City_Name, Cost_Index, Popularity, Description, Image) VALUES
(1, 'Goa', 35, 95, 'Beaches, nightlife and Portuguese heritage.', ''),
(1, 'Jaipur', 30, 88, 'The Pink City, forts and palaces.', ''),
(1, 'Manali', 32, 80, 'Himalayan hill station for adventure lovers.', ''),
(2, 'Paris', 85, 98, 'The City of Light, art and romance.', ''),
(2, 'Nice', 70, 65, 'French Riviera coastal charm.', ''),
(3, 'Tokyo', 90, 97, 'Ultra-modern city with deep tradition.', ''),
(3, 'Kyoto', 75, 82, 'Temples, gardens and geisha culture.', ''),
(4, 'Rome', 78, 93, 'Ancient history meets vibrant street life.', ''),
(4, 'Venice', 82, 78, 'Canals, gondolas and island charm.', ''),
(5, 'Bangkok', 40, 90, 'Street food, temples and buzzing markets.', ''),
(5, 'Phuket', 45, 75, 'Islands, beaches and diving.', ''),
(6, 'New York', 95, 96, 'The city that never sleeps.', ''),
(6, 'Los Angeles', 88, 70, 'Beaches, Hollywood and sunshine.', ''),
(7, 'Dubai', 80, 92, 'Skyscrapers, desert safaris and luxury shopping.', ''),
(8, 'Barcelona', 65, 85, 'Gaudi architecture and Mediterranean beaches.', ''),
(9, 'Bali', 38, 89, 'Rice terraces, temples and surf beaches.', ''),
(10, 'Zurich', 92, 60, 'Lakes, mountains and clean city living.', '');
