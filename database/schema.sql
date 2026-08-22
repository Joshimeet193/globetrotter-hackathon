-- =====================================================
-- GlobeTrotter Database Schema
-- =====================================================

CREATE SCHEMA globetrotter;

USE globetrotter;

CREATE TABLE USERS (
    User_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(50) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Profile_Photo VARCHAR(255),
    Language VARCHAR(30),
    Created_At DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE COUNTRY (
    Country_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    Country_Name VARCHAR(60) NOT NULL UNIQUE,
    Region VARCHAR(60)
);

CREATE TABLE CITY (
    City_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    Country_ID INT(11) NOT NULL,
    City_Name VARCHAR(60) NOT NULL,
    Cost_Index DECIMAL(5,2) CHECK (Cost_Index >= 0),
    Popularity INT(11) DEFAULT 0 CHECK (Popularity >= 0),
    Description TEXT,
    Image VARCHAR(255),

    CONSTRAINT fk_city_country
        FOREIGN KEY (Country_ID)
        REFERENCES COUNTRY(Country_ID)
);

CREATE TABLE TRIP (
    Trip_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    User_ID INT(11) NOT NULL,
    Trip_Name VARCHAR(100) NOT NULL,
    Start_Date DATE NOT NULL,
    End_Date DATE NOT NULL,
    Description TEXT,
    Cover_Photo VARCHAR(255),
    Budget DECIMAL(10,2) CHECK (Budget >= 0),
    Is_Public BOOLEAN DEFAULT FALSE,

    CONSTRAINT fk_trip_user
        FOREIGN KEY (User_ID)
        REFERENCES USERS(User_ID)
);

CREATE TABLE TRIP_STOP (
    Stop_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    Trip_ID INT(11) NOT NULL,
    City_ID INT(11) NOT NULL,
    Stop_Order INT(11) NOT NULL CHECK (Stop_Order > 0),
    Arrival_Date DATE NOT NULL,
    Departure_Date DATE NOT NULL,

    CONSTRAINT fk_stop_trip
        FOREIGN KEY (Trip_ID)
        REFERENCES TRIP(Trip_ID),

    CONSTRAINT fk_stop_city
        FOREIGN KEY (City_ID)
        REFERENCES CITY(City_ID),

    CONSTRAINT uq_trip_stop_order
        UNIQUE (Trip_ID, Stop_Order)
);

CREATE TABLE ACTIVITY (
    Activity_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    City_ID INT(11) NOT NULL,
    Activity_Name VARCHAR(100) NOT NULL,
    Activity_Type VARCHAR(50) NOT NULL,
    Description TEXT,
    Duration DECIMAL(4,2) CHECK (Duration > 0),
    Estimated_Cost DECIMAL(10,2) CHECK (Estimated_Cost >= 0),
    Image VARCHAR(255),

    CONSTRAINT fk_activity_city
        FOREIGN KEY (City_ID)
        REFERENCES CITY(City_ID)
);

CREATE TABLE ITINERARY (
    Itinerary_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    Stop_ID INT(11) NOT NULL,
    Activity_ID INT(11) NOT NULL,
    Activity_Date DATE NOT NULL,
    Start_Time TIME,
    End_Time TIME,
    Notes TEXT,
    Activity_Cost DECIMAL(10,2) CHECK (Activity_Cost >= 0),

    CONSTRAINT fk_itinerary_stop
        FOREIGN KEY (Stop_ID)
        REFERENCES TRIP_STOP(Stop_ID),

    CONSTRAINT fk_itinerary_activity
        FOREIGN KEY (Activity_ID)
        REFERENCES ACTIVITY(Activity_ID)
);

CREATE TABLE EXPENSE (
    Expense_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    Trip_ID INT(11) NOT NULL,
    Stop_ID INT(11),
    Expense_Type VARCHAR(30) NOT NULL,
    Description VARCHAR(150),
    Amount DECIMAL(10,2) NOT NULL CHECK (Amount >= 0),
    Expense_Date DATE NOT NULL,

    CONSTRAINT fk_expense_trip
        FOREIGN KEY (Trip_ID)
        REFERENCES TRIP(Trip_ID),

    CONSTRAINT fk_expense_stop
        FOREIGN KEY (Stop_ID)
        REFERENCES TRIP_STOP(Stop_ID)
);

CREATE TABLE SAVED_DESTINATION (
    Saved_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    User_ID INT(11) NOT NULL,
    City_ID INT(11) NOT NULL,
    Saved_At DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_saved_user
        FOREIGN KEY (User_ID)
        REFERENCES USERS(User_ID),

    CONSTRAINT fk_saved_city
        FOREIGN KEY (City_ID)
        REFERENCES CITY(City_ID),

    CONSTRAINT uq_user_city
        UNIQUE (User_ID, City_ID)
);

CREATE TABLE TRIP_SHARE (
    Share_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
    Trip_ID INT(11) NOT NULL,
    Shared_By INT(11) NOT NULL,
    Shared_With INT(11) NOT NULL,
    Shared_At DATETIME DEFAULT CURRENT_TIMESTAMP,
    Permission VARCHAR(20) DEFAULT 'VIEW',

    CONSTRAINT fk_share_trip
        FOREIGN KEY (Trip_ID)
        REFERENCES TRIP(Trip_ID),

    CONSTRAINT fk_share_sender
        FOREIGN KEY (Shared_By)
        REFERENCES USERS(User_ID),

    CONSTRAINT fk_share_receiver
        FOREIGN KEY (Shared_With)
        REFERENCES USERS(User_ID),

    CONSTRAINT uq_trip_shared_with
        UNIQUE (Trip_ID, Shared_With)
);
