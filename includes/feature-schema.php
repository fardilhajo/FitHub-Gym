<?php
/** Lightweight schema bootstrap for feature pages. */
function ensure_booking_feature_schema(PDO $db): void
{
    $db->exec('PRAGMA foreign_keys = ON');

    ensure_user_profile_schema($db);
    ensure_notification_schema($db);

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

    ensure_class_waitlist_schema($db);
    ensure_equipment_reservation_schema($db);
    ensure_class_review_schema($db);
    ensure_nutrition_schema($db);
    ensure_progress_schema($db);
    seed_booking_features($db);
}

function ensure_user_profile_schema(PDO $db): void
{
    $db->exec('PRAGMA foreign_keys = ON');

    $columns = $db->query('PRAGMA table_info(USER)')->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_map(static fn(array $column): string => $column['name'], $columns);

    if (!in_array('Email', $columnNames, true)) {
        $db->exec('ALTER TABLE USER ADD COLUMN Email TEXT');
    }
}

function ensure_notification_schema(PDO $db): void
{
    $db->exec('PRAGMA foreign_keys = ON');
    ensure_user_profile_schema($db);

    $db->exec('
        CREATE TABLE IF NOT EXISTS NOTIFICATION (
            NotificationID INTEGER PRIMARY KEY AUTOINCREMENT,
            UserID INTEGER NOT NULL,
            Type TEXT NOT NULL CHECK (Type IN ("booking_confirmed", "booking_cancelled", "class_reminder", "membership_renewal", "general")),
            Title TEXT NOT NULL,
            Message TEXT NOT NULL,
            IsRead INTEGER NOT NULL DEFAULT 0,
            CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (UserID) REFERENCES USER(UserID) ON DELETE CASCADE
        )
    ');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_notification_user_read ON NOTIFICATION (UserID, IsRead, CreatedAt)');
}

function ensure_class_waitlist_schema(PDO $db): void
{
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('
        CREATE TABLE IF NOT EXISTS CLASS_WAITLIST (
            WaitlistID INTEGER PRIMARY KEY AUTOINCREMENT,
            ClassID INTEGER NOT NULL,
            MemberID INTEGER NOT NULL,
            JoinedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PromotedAt TEXT,
            Status TEXT NOT NULL CHECK (Status IN ("Waiting", "Enrolled", "Cancelled")) DEFAULT "Waiting",
            UNIQUE (ClassID, MemberID),
            FOREIGN KEY (ClassID) REFERENCES FITNESS_CLASS(ClassID) ON DELETE CASCADE,
            FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
        )
    ');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_class_waitlist_next ON CLASS_WAITLIST (ClassID, Status, JoinedAt)');
}

function ensure_equipment_reservation_schema(PDO $db): void
{
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('
        CREATE TABLE IF NOT EXISTS EQUIPMENT_RESERVATION (
            ReservationID INTEGER PRIMARY KEY AUTOINCREMENT,
            EquipmentID INTEGER NOT NULL,
            MemberID INTEGER NOT NULL,
            StartAt TEXT NOT NULL,
            EndAt TEXT NOT NULL,
            Status TEXT NOT NULL CHECK (Status IN ("Reserved", "Cancelled", "Completed")) DEFAULT "Reserved",
            CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (EquipmentID) REFERENCES EQUIPMENT(EquipmentID) ON DELETE CASCADE,
            FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE,
            CHECK (EndAt > StartAt)
        )
    ');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_equipment_reservation_slot ON EQUIPMENT_RESERVATION (EquipmentID, Status, StartAt, EndAt)');
}

function ensure_class_review_schema(PDO $db): void
{
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('
        CREATE TABLE IF NOT EXISTS CLASS_REVIEW (
            ReviewID INTEGER PRIMARY KEY AUTOINCREMENT,
            ClassID INTEGER NOT NULL,
            MemberID INTEGER NOT NULL,
            Rating INTEGER NOT NULL CHECK (Rating BETWEEN 1 AND 5),
            Comment TEXT,
            CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (ClassID, MemberID),
            FOREIGN KEY (ClassID) REFERENCES FITNESS_CLASS(ClassID) ON DELETE CASCADE,
            FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
        )
    ');
}

function ensure_nutrition_schema(PDO $db): void
{
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('
        CREATE TABLE IF NOT EXISTS NUTRITION_PLAN (
            PlanID INTEGER PRIMARY KEY AUTOINCREMENT,
            TrainerID INTEGER NOT NULL,
            MemberID INTEGER NOT NULL,
            Title TEXT NOT NULL,
            Description TEXT,
            DailyCalorieTarget INTEGER,
            IsActive INTEGER NOT NULL DEFAULT 1,
            CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (TrainerID) REFERENCES USER(UserID) ON DELETE CASCADE,
            FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
        )
    ');

    $db->exec('
        CREATE TABLE IF NOT EXISTS MEAL (
            MealID INTEGER PRIMARY KEY AUTOINCREMENT,
            PlanID INTEGER NOT NULL,
            Name TEXT NOT NULL,
            MealType TEXT NOT NULL CHECK (MealType IN ("Breakfast", "Lunch", "Dinner", "Snack")),
            Calories INTEGER NOT NULL DEFAULT 0,
            ProteinG REAL,
            CarbsG REAL,
            FatG REAL,
            Notes TEXT,
            FOREIGN KEY (PlanID) REFERENCES NUTRITION_PLAN(PlanID) ON DELETE CASCADE
        )
    ');
}

function ensure_progress_schema(PDO $db): void
{
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('
        CREATE TABLE IF NOT EXISTS WORKOUT_LOG (
            LogID INTEGER PRIMARY KEY AUTOINCREMENT,
            MemberID INTEGER NOT NULL,
            LogDate TEXT NOT NULL DEFAULT (date(\'now\')),
            WorkoutType TEXT NOT NULL,
            DurationMinutes INTEGER NOT NULL CHECK (DurationMinutes > 0),
            CaloriesBurned INTEGER,
            Notes TEXT,
            CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
        )
    ');

    $db->exec('
        CREATE TABLE IF NOT EXISTS FITNESS_GOAL (
            GoalID INTEGER PRIMARY KEY AUTOINCREMENT,
            MemberID INTEGER NOT NULL,
            Title TEXT NOT NULL,
            GoalType TEXT NOT NULL CHECK (GoalType IN ("Workouts", "Duration", "Calories", "Custom")),
            TargetValue REAL NOT NULL,
            CurrentValue REAL NOT NULL DEFAULT 0,
            Unit TEXT NOT NULL,
            Deadline TEXT,
            Status TEXT NOT NULL CHECK (Status IN ("Active", "Completed", "Abandoned")) DEFAULT "Active",
            CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
        )
    ');
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
