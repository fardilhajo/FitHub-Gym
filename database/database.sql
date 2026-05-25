PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS NOTIFICATION;
DROP TABLE IF EXISTS MEMBER_MEMBERSHIP;
DROP TABLE IF EXISTS MEMBERSHIP_PLAN;
DROP TABLE IF EXISTS PERSONAL_TRAINING_BOOKING;
DROP TABLE IF EXISTS TRAINER_AVAILABILITY;
DROP TABLE IF EXISTS MEAL;
DROP TABLE IF EXISTS NUTRITION_PLAN;
DROP TABLE IF EXISTS FITNESS_GOAL;
DROP TABLE IF EXISTS WORKOUT_LOG;
DROP TABLE IF EXISTS CLASS_REVIEW;
DROP TABLE IF EXISTS CLASS_ENROLLMENT;
DROP TABLE IF EXISTS FITNESS_CLASS;
DROP TABLE IF EXISTS EQUIPMENT;
DROP TABLE IF EXISTS TRAINER_PROFILE;
DROP TABLE IF EXISTS USER;

CREATE TABLE USER (
    UserID INTEGER PRIMARY KEY AUTOINCREMENT,
    Name TEXT NOT NULL,
    Username TEXT NOT NULL UNIQUE,
    PasswordHash TEXT NOT NULL,
    Role TEXT NOT NULL CHECK (Role IN ('Member', 'Trainer', 'Admin')) DEFAULT 'Member',
    ProfilePhoto TEXT,
    Email TEXT,
    IsActive INTEGER NOT NULL DEFAULT 1,
    CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE NOTIFICATION (
    NotificationID INTEGER PRIMARY KEY AUTOINCREMENT,
    UserID INTEGER NOT NULL,
    Type TEXT NOT NULL CHECK (Type IN ('booking_confirmed', 'booking_cancelled', 'class_reminder', 'membership_renewal', 'general')),
    Title TEXT NOT NULL,
    Message TEXT NOT NULL,
    IsRead INTEGER NOT NULL DEFAULT 0,
    CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES USER(UserID) ON DELETE CASCADE
);

CREATE TABLE TRAINER_PROFILE (
    TrainerID INTEGER PRIMARY KEY,
    Bio TEXT,
    Specializations TEXT,
    Certifications TEXT,
    FOREIGN KEY (TrainerID) REFERENCES USER(UserID) ON DELETE CASCADE
);

CREATE TABLE TRAINER_AVAILABILITY (
    AvailabilityID INTEGER PRIMARY KEY AUTOINCREMENT,
    TrainerID INTEGER NOT NULL,
    Day TEXT NOT NULL,
    StartTime TEXT NOT NULL,
    EndTime TEXT NOT NULL,
    Location TEXT NOT NULL DEFAULT 'PT Studio',
    IsActive INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (TrainerID) REFERENCES USER(UserID) ON DELETE CASCADE
);

CREATE TABLE PERSONAL_TRAINING_BOOKING (
    BookingID INTEGER PRIMARY KEY AUTOINCREMENT,
    AvailabilityID INTEGER NOT NULL,
    TrainerID INTEGER NOT NULL,
    MemberID INTEGER NOT NULL,
    SessionDate TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Status TEXT NOT NULL CHECK (Status IN ('Booked', 'Cancelled')) DEFAULT 'Booked',
    Notes TEXT,
    CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (AvailabilityID, MemberID),
    FOREIGN KEY (AvailabilityID) REFERENCES TRAINER_AVAILABILITY(AvailabilityID) ON DELETE CASCADE,
    FOREIGN KEY (TrainerID) REFERENCES USER(UserID) ON DELETE CASCADE,
    FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
);

CREATE TABLE MEMBERSHIP_PLAN (
    PlanID INTEGER PRIMARY KEY AUTOINCREMENT,
    Name TEXT NOT NULL UNIQUE,
    PriceMonthly REAL NOT NULL CHECK (PriceMonthly >= 0),
    AccessLevel TEXT NOT NULL,
    Description TEXT NOT NULL,
    IsActive INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE MEMBER_MEMBERSHIP (
    MembershipID INTEGER PRIMARY KEY AUTOINCREMENT,
    MemberID INTEGER NOT NULL UNIQUE,
    PlanID INTEGER NOT NULL,
    SubscribedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Status TEXT NOT NULL CHECK (Status IN ('Active', 'Cancelled')) DEFAULT 'Active',
    FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE,
    FOREIGN KEY (PlanID) REFERENCES MEMBERSHIP_PLAN(PlanID) ON DELETE CASCADE
);

CREATE TABLE FITNESS_CLASS (
    ClassID INTEGER PRIMARY KEY AUTOINCREMENT,
    Name TEXT NOT NULL,
    Type TEXT NOT NULL,
    Day TEXT NOT NULL,
    StartTime TEXT NOT NULL,
    EndTime TEXT NOT NULL,
    Room TEXT NOT NULL,
    Capacity INTEGER NOT NULL CHECK (Capacity > 0),
    Description TEXT NOT NULL,
    ImageURL TEXT,
    TrainerID INTEGER NOT NULL,
    IsActive INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (TrainerID) REFERENCES USER(UserID)
);

CREATE TABLE CLASS_ENROLLMENT (
    EnrollmentID INTEGER PRIMARY KEY AUTOINCREMENT,
    ClassID INTEGER NOT NULL,
    MemberID INTEGER NOT NULL,
    EnrolledAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Status TEXT NOT NULL CHECK (Status IN ('Enrolled', 'Cancelled')) DEFAULT 'Enrolled',
    UNIQUE (ClassID, MemberID),
    FOREIGN KEY (ClassID) REFERENCES FITNESS_CLASS(ClassID) ON DELETE CASCADE,
    FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
);

CREATE TABLE CLASS_REVIEW (
    ReviewID INTEGER PRIMARY KEY AUTOINCREMENT,
    ClassID INTEGER NOT NULL,
    MemberID INTEGER NOT NULL,
    Rating INTEGER NOT NULL CHECK (Rating BETWEEN 1 AND 5),
    Comment TEXT,
    CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (ClassID, MemberID),
    FOREIGN KEY (ClassID) REFERENCES FITNESS_CLASS(ClassID) ON DELETE CASCADE,
    FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
);

CREATE TABLE EQUIPMENT (
    EquipmentID INTEGER PRIMARY KEY AUTOINCREMENT,
    Name TEXT NOT NULL,
    Category TEXT NOT NULL,
    Status TEXT NOT NULL CHECK (Status IN ('Available', 'In use', 'Reserved', 'Maintenance')) DEFAULT 'Available',
    Location TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE NUTRITION_PLAN (
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
);

CREATE TABLE MEAL (
    MealID INTEGER PRIMARY KEY AUTOINCREMENT,
    PlanID INTEGER NOT NULL,
    Name TEXT NOT NULL,
    MealType TEXT NOT NULL CHECK (MealType IN ('Breakfast', 'Lunch', 'Dinner', 'Snack')),
    Calories INTEGER NOT NULL DEFAULT 0,
    ProteinG REAL,
    CarbsG REAL,
    FatG REAL,
    Notes TEXT,
    FOREIGN KEY (PlanID) REFERENCES NUTRITION_PLAN(PlanID) ON DELETE CASCADE
);

CREATE TABLE WORKOUT_LOG (
    LogID INTEGER PRIMARY KEY AUTOINCREMENT,
    MemberID INTEGER NOT NULL,
    LogDate TEXT NOT NULL DEFAULT (date('now')),
    WorkoutType TEXT NOT NULL,
    DurationMinutes INTEGER NOT NULL CHECK (DurationMinutes > 0),
    CaloriesBurned INTEGER,
    Notes TEXT,
    CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
);

CREATE TABLE FITNESS_GOAL (
    GoalID INTEGER PRIMARY KEY AUTOINCREMENT,
    MemberID INTEGER NOT NULL,
    Title TEXT NOT NULL,
    GoalType TEXT NOT NULL CHECK (GoalType IN ('Workouts', 'Duration', 'Calories', 'Custom')),
    TargetValue REAL NOT NULL,
    CurrentValue REAL NOT NULL DEFAULT 0,
    Unit TEXT NOT NULL,
    Deadline TEXT,
    Status TEXT NOT NULL CHECK (Status IN ('Active', 'Completed', 'Abandoned')) DEFAULT 'Active',
    CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
);

INSERT INTO USER (UserID, Name, Username, PasswordHash, Role, ProfilePhoto, IsActive) VALUES
(1, 'Admin FitHub', 'admin', '$2y$12$Jt.ZRXBQotsXiqazPml37eF4MdeE0/L4NacVV.p.iejDlYM9EGQ22', 'Admin', NULL, 1),
(2, 'João Member', 'member', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 1),
(3, 'Ana Costa', 'ana', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Trainer', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=600&q=80', 1),
(4, 'Ricardo Silva', 'ricardo', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Trainer', 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=600&q=80', 1),
(5, 'Sofia Martins', 'sofia', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Trainer', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=600&q=80', 1),
(6, 'David Santos', 'david', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Trainer', 'https://images.unsplash.com/photo-1507591064344-4c6ce005b128?auto=format&fit=crop&w=600&q=80', 1);

INSERT INTO TRAINER_PROFILE (TrainerID, Bio, Specializations, Certifications) VALUES
(3, 'Ana focuses on mobility, breathing and sustainable strength. Her classes are calm, precise and accessible to beginners.', 'Yoga, Mobility, Recovery', 'RYT 200 Yoga Alliance, Functional Mobility Specialist'),
(4, 'Ricardo builds high intensity sessions for members who want measurable progress without destroying their knees, a revolutionary concept.', 'HIIT, Strength, Conditioning', 'NASM Personal Trainer, Strength and Conditioning Level 1'),
(5, 'Sofia teaches controlled movement and core training with a strong focus on posture and injury prevention.', 'Pilates, Core, Posture', 'Certified Pilates Instructor, Corrective Exercise Specialist'),
(6, 'David combines cycling, endurance work and motivation for members who enjoy sweating on purpose.', 'Spinning, Cardio, Endurance', 'Indoor Cycling Coach, Sports Performance Certificate');

INSERT INTO FITNESS_CLASS (ClassID, Name, Type, Day, StartTime, EndTime, Room, Capacity, Description, ImageURL, TrainerID, IsActive) VALUES
(1, 'Morning Zen Flow', 'Yoga', 'Monday', '09:00', '10:00', 'Studio B', 15, 'A calm yoga flow focused on breathing, balance and mobility.', 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=700&q=80', 3, 1),
(2, 'HIIT Intenso', 'HIIT', 'Monday', '18:00', '19:00', 'Studio A', 20, 'High-energy interval training with short recovery blocks.', 'https://images.unsplash.com/photo-1517963879433-6ad2b056d712?auto=format&fit=crop&w=700&q=80', 4, 1),
(3, 'Pilates Core Strength', 'Pilates', 'Tuesday', '10:00', '11:00', 'Studio C', 12, 'Controlled pilates class to build core strength and posture.', 'https://images.unsplash.com/photo-1518611507436-f9221403cca2?auto=format&fit=crop&w=700&q=80', 5, 1),
(4, 'Spinning Power', 'Spinning', 'Wednesday', '19:00', '20:00', 'Studio A', 20, 'Cardio ride with high intensity climbs and sprint blocks.', 'https://images.unsplash.com/photo-1538805060514-97d9cc17730c?auto=format&fit=crop&w=700&q=80', 6, 1),
(5, 'Strength Basics', 'Strength', 'Thursday', '17:30', '18:30', 'Weight Room', 10, 'Technique-first strength session for beginners.', 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=700&q=80', 4, 1),
(6, 'Recovery Stretch', 'Recovery', 'Friday', '08:00', '08:45', 'Studio B', 14, 'Mobility and guided stretching to recover after training.', 'https://images.unsplash.com/photo-1518310383802-640c2de311b2?auto=format&fit=crop&w=700&q=80', 3, 1),
(7, 'Boxing Conditioning', 'Boxing', 'Saturday', '11:00', '12:00', 'Studio A', 16, 'Boxing-inspired conditioning with footwork and bag intervals.', 'https://images.unsplash.com/photo-1549719386-74dfcbf7dbed?auto=format&fit=crop&w=700&q=80', 4, 1),
(8, 'Pilates Evening Reset', 'Pilates', 'Thursday', '19:30', '20:30', 'Studio C', 12, 'Low impact pilates for mobility, breath and control.', 'https://images.unsplash.com/photo-1518611507436-f9221403cca2?auto=format&fit=crop&w=700&q=80', 5, 1);

INSERT INTO CLASS_ENROLLMENT (ClassID, MemberID, Status) VALUES
(1, 2, 'Enrolled'),
(2, 2, 'Enrolled'),
(3, 2, 'Enrolled');

INSERT INTO EQUIPMENT (Name, Category, Status, Location) VALUES
('Treadmill 01', 'Treadmills', 'Available', 'Cardio Zone'),
('Treadmill 02', 'Treadmills', 'Available', 'Cardio Zone'),
('Treadmill 03', 'Treadmills', 'In use', 'Cardio Zone'),
('Bike 01', 'Bikes', 'Available', 'Cardio Zone'),
('Bike 02', 'Bikes', 'Reserved', 'Cardio Zone'),
('Bench Press 01', 'Weight Benches', 'Available', 'Free Weights'),
('Bench Press 02', 'Weight Benches', 'In use', 'Free Weights'),
('Squat Rack 01', 'Racks', 'Available', 'Strength Zone'),
('Cable Machine 01', 'Machines', 'Maintenance', 'Strength Zone'),
('Dumbbell Set', 'Free Weights', 'Available', 'Free Weights'),
('Rowing Machine 01', 'Rowers', 'Available', 'Cardio Zone'),
('Leg Press', 'Machines', 'Reserved', 'Strength Zone');

INSERT INTO TRAINER_AVAILABILITY (TrainerID, Day, StartTime, EndTime, Location) VALUES
(3, 'Monday', '08:00', '09:00', 'PT Studio 1'),
(3, 'Wednesday', '10:00', '11:00', 'PT Studio 1'),
(4, 'Tuesday', '17:00', '18:00', 'PT Studio 2'),
(4, 'Thursday', '18:00', '19:00', 'PT Studio 2'),
(5, 'Wednesday', '10:00', '11:00', 'PT Studio 3'),
(5, 'Friday', '07:30', '08:30', 'PT Studio 3'),
(6, 'Monday', '08:00', '09:00', 'PT Studio 1'),
(6, 'Friday', '07:30', '08:30', 'PT Studio 1');

INSERT INTO MEMBERSHIP_PLAN (Name, PriceMonthly, AccessLevel, Description) VALUES
('Basic', 19.99, 'Gym floor + standard classes', 'Access to gym equipment, standard class booking and progress tracking.'),
('Premium', 34.99, 'Everything in Basic + nutrition + priority classes', 'Adds nutrition plans, priority booking and extended class access.'),
('Elite', 54.99, 'Premium + personal training discounts', 'Best for members who want coaching, priority support and personal training perks.');
