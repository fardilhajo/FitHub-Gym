<?php
/** Lightweight schema bootstrap for feature pages. */
function ensure_booking_feature_schema(PDO $db): void
{
    $db->exec('PRAGMA foreign_keys = ON');

    $db->exec('
        CREATE TABLE IF NOT EXISTS TRAINER_AVAILABILITY (
            AvailabilityID INTEGER PRIMARY KEY AUTOINCREMENT,
            TrainerID INTEGER NOT NULL,
            Day TEXT NOT NULL,
            StartTime TEXT NOT NULL,
            EndTime TEXT NOT NULL,
            Location TEXT NOT NULL DEFAULT "PT Studio",
            IsActive INTEGER NOT NULL DEFAULT 1,
            FOREIGN KEY (TrainerID) REFERENCES USER(UserID) ON DELETE CASCADE
        )
    ');

    $db->exec('
        CREATE TABLE IF NOT EXISTS PERSONAL_TRAINING_BOOKING (
            BookingID INTEGER PRIMARY KEY AUTOINCREMENT,
            AvailabilityID INTEGER NOT NULL,
            TrainerID INTEGER NOT NULL,
            MemberID INTEGER NOT NULL,
            SessionDate TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            Status TEXT NOT NULL CHECK (Status IN ("Booked", "Cancelled")) DEFAULT "Booked",
            Notes TEXT,
            CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (AvailabilityID, MemberID),
            FOREIGN KEY (AvailabilityID) REFERENCES TRAINER_AVAILABILITY(AvailabilityID) ON DELETE CASCADE,
            FOREIGN KEY (TrainerID) REFERENCES USER(UserID) ON DELETE CASCADE,
            FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
        )
    ');

    $db->exec('
        CREATE TABLE IF NOT EXISTS MEMBERSHIP_PLAN (
            PlanID INTEGER PRIMARY KEY AUTOINCREMENT,
            Name TEXT NOT NULL UNIQUE,
            PriceMonthly REAL NOT NULL CHECK (PriceMonthly >= 0),
            AccessLevel TEXT NOT NULL,
            Description TEXT NOT NULL,
            IsActive INTEGER NOT NULL DEFAULT 1
        )
    ');

    $db->exec('
        CREATE TABLE IF NOT EXISTS MEMBER_MEMBERSHIP (
            MembershipID INTEGER PRIMARY KEY AUTOINCREMENT,
            MemberID INTEGER NOT NULL UNIQUE,
            PlanID INTEGER NOT NULL,
            SubscribedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            Status TEXT NOT NULL CHECK (Status IN ("Active", "Cancelled")) DEFAULT "Active",
            FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE,
            FOREIGN KEY (PlanID) REFERENCES MEMBERSHIP_PLAN(PlanID) ON DELETE CASCADE
        )
    ');

    seed_booking_features($db);
}

function seed_booking_features(PDO $db): void
{
    $planCount = (int)$db->query('SELECT COUNT(*) FROM MEMBERSHIP_PLAN')->fetchColumn();
    if ($planCount === 0) {
        $stmt = $db->prepare('INSERT INTO MEMBERSHIP_PLAN (Name, PriceMonthly, AccessLevel, Description) VALUES (?, ?, ?, ?)');
        $plans = [
            ['Basic', 19.99, 'Gym floor + standard classes', 'Access to gym equipment, standard class booking and progress tracking.'],
            ['Premium', 34.99, 'Everything in Basic + nutrition + priority classes', 'Adds nutrition plans, priority booking and extended class access.'],
            ['Elite', 54.99, 'Premium + personal training discounts', 'Best for members who want coaching, priority support and personal training perks.'],
        ];
        foreach ($plans as $plan) {
            $stmt->execute($plan);
        }
    }

    $availabilityCount = (int)$db->query('SELECT COUNT(*) FROM TRAINER_AVAILABILITY')->fetchColumn();
    if ($availabilityCount === 0) {
        $trainers = $db->query('SELECT UserID FROM USER WHERE Role = "Trainer" AND IsActive = 1 ORDER BY UserID')->fetchAll(PDO::FETCH_COLUMN);
        $slots = [
            ['Monday', '08:00', '09:00'],
            ['Tuesday', '17:00', '18:00'],
            ['Wednesday', '10:00', '11:00'],
            ['Thursday', '18:00', '19:00'],
            ['Friday', '07:30', '08:30'],
        ];
        $stmt = $db->prepare('INSERT INTO TRAINER_AVAILABILITY (TrainerID, Day, StartTime, EndTime, Location) VALUES (:trainer, :day, :start, :end, :location)');
        foreach ($trainers as $index => $trainerId) {
            for ($i = 0; $i < 2; $i++) {
                $slot = $slots[($index + $i) % count($slots)];
                $stmt->execute([
                    ':trainer' => $trainerId,
                    ':day' => $slot[0],
                    ':start' => $slot[1],
                    ':end' => $slot[2],
                    ':location' => 'PT Studio ' . (($index % 3) + 1),
                ]);
            }
        }
    }
}
