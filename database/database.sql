PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS REPORT_RESPONSE;
DROP TABLE IF EXISTS REPORT;
DROP TABLE IF EXISTS NOTIFICATION;
DROP TABLE IF EXISTS EQUIPMENT_RESERVATION;
DROP TABLE IF EXISTS CLASS_WAITLIST;
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
    Type TEXT NOT NULL CHECK (Type IN ('booking_confirmed', 'booking_cancelled', 'class_reminder', 'membership_renewal', 'general', 'report_update')),
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

CREATE TABLE CLASS_WAITLIST (
    WaitlistID INTEGER PRIMARY KEY AUTOINCREMENT,
    ClassID INTEGER NOT NULL,
    MemberID INTEGER NOT NULL,
    JoinedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PromotedAt TEXT,
    Status TEXT NOT NULL CHECK (Status IN ('Waiting', 'Enrolled', 'Cancelled')) DEFAULT 'Waiting',
    UNIQUE (ClassID, MemberID),
    FOREIGN KEY (ClassID) REFERENCES FITNESS_CLASS(ClassID) ON DELETE CASCADE,
    FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE
);

CREATE INDEX idx_class_waitlist_next ON CLASS_WAITLIST (ClassID, Status, JoinedAt);

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

CREATE TABLE EQUIPMENT_RESERVATION (
    ReservationID INTEGER PRIMARY KEY AUTOINCREMENT,
    EquipmentID INTEGER NOT NULL,
    MemberID INTEGER NOT NULL,
    StartAt TEXT NOT NULL,
    EndAt TEXT NOT NULL,
    Status TEXT NOT NULL CHECK (Status IN ('Reserved', 'Cancelled', 'Completed')) DEFAULT 'Reserved',
    CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (EquipmentID) REFERENCES EQUIPMENT(EquipmentID) ON DELETE CASCADE,
    FOREIGN KEY (MemberID) REFERENCES USER(UserID) ON DELETE CASCADE,
    CHECK (EndAt > StartAt)
);

CREATE INDEX idx_equipment_reservation_slot ON EQUIPMENT_RESERVATION (EquipmentID, Status, StartAt, EndAt);

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

INSERT INTO USER (UserID, Name, Username, PasswordHash, Role, ProfilePhoto, Email, IsActive) VALUES
(7, 'Marta Almeida', 'dummy01', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy01@fithub.test', 1),
(8, 'Tiago Ferreira', 'dummy02', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy02@fithub.test', 1),
(9, 'Inês Rocha', 'dummy03', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy03@fithub.test', 1),
(10, 'Bruno Lopes', 'dummy04', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy04@fithub.test', 1),
(11, 'Carolina Pereira', 'dummy05', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy05@fithub.test', 1),
(12, 'Miguel Costa', 'dummy06', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy06@fithub.test', 1),
(13, 'Beatriz Ribeiro', 'dummy07', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy07@fithub.test', 1),
(14, 'Tomás Martins', 'dummy08', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy08@fithub.test', 1),
(15, 'Leonor Sousa', 'dummy09', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy09@fithub.test', 1),
(16, 'Diogo Cardoso', 'dummy10', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy10@fithub.test', 1),
(17, 'Mariana Teixeira', 'dummy11', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy11@fithub.test', 1),
(18, 'Gonçalo Neves', 'dummy12', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy12@fithub.test', 1),
(19, 'Rita Figueiredo', 'dummy13', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy13@fithub.test', 1),
(20, 'Pedro Barros', 'dummy14', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy14@fithub.test', 1),
(21, 'Joana Moreira', 'dummy15', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy15@fithub.test', 1),
(22, 'André Nunes', 'dummy16', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy16@fithub.test', 1),
(23, 'Catarina Gomes', 'dummy17', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy17@fithub.test', 1),
(24, 'Francisco Pinto', 'dummy18', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy18@fithub.test', 1),
(25, 'Sara Correia', 'dummy19', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy19@fithub.test', 1),
(26, 'Nuno Carvalho', 'dummy20', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy20@fithub.test', 1),
(27, 'Clara Matos', 'dummy21', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy21@fithub.test', 1),
(28, 'Henrique Dias', 'dummy22', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy22@fithub.test', 1),
(29, 'Patrícia Ramos', 'dummy23', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy23@fithub.test', 1),
(30, 'Vasco Fernandes', 'dummy24', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy24@fithub.test', 1),
(31, 'Mafalda Antunes', 'dummy25', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy25@fithub.test', 1),
(32, 'Duarte Azevedo', 'dummy26', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy26@fithub.test', 1),
(33, 'Eva Monteiro', 'dummy27', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy27@fithub.test', 1),
(34, 'Rafael Cunha', 'dummy28', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy28@fithub.test', 1),
(35, 'Isabel Batista', 'dummy29', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy29@fithub.test', 1),
(36, 'Luís Faria', 'dummy30', '$2y$12$f31/JOY0apMh8LjcP78Hv.rjomcW/H5Lrd4vLgC5vahKqOHMbH1uS', 'Member', NULL, 'dummy30@fithub.test', 1);

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

INSERT INTO CLASS_ENROLLMENT (ClassID, MemberID, Status) VALUES
(1, 7, 'Enrolled'),
(2, 7, 'Enrolled'),
(3, 8, 'Enrolled'),
(4, 8, 'Enrolled'),
(5, 9, 'Enrolled'),
(6, 9, 'Enrolled'),
(7, 10, 'Enrolled'),
(8, 10, 'Enrolled'),
(1, 11, 'Enrolled'),
(3, 11, 'Enrolled'),
(2, 12, 'Enrolled'),
(4, 12, 'Enrolled'),
(5, 13, 'Enrolled'),
(7, 13, 'Enrolled'),
(6, 14, 'Enrolled'),
(8, 14, 'Enrolled'),
(1, 15, 'Enrolled'),
(2, 15, 'Enrolled'),
(3, 16, 'Enrolled'),
(4, 16, 'Enrolled'),
(5, 17, 'Enrolled'),
(6, 17, 'Enrolled'),
(7, 18, 'Enrolled'),
(8, 18, 'Enrolled'),
(1, 19, 'Enrolled'),
(3, 19, 'Enrolled'),
(2, 20, 'Enrolled'),
(4, 20, 'Enrolled'),
(5, 21, 'Enrolled'),
(7, 21, 'Enrolled'),
(6, 22, 'Enrolled'),
(8, 22, 'Enrolled'),
(1, 23, 'Enrolled'),
(2, 23, 'Enrolled'),
(3, 24, 'Enrolled'),
(4, 24, 'Enrolled'),
(5, 25, 'Enrolled'),
(6, 25, 'Enrolled'),
(7, 26, 'Enrolled'),
(8, 26, 'Enrolled'),
(1, 27, 'Enrolled'),
(3, 27, 'Enrolled'),
(2, 28, 'Enrolled'),
(4, 28, 'Enrolled'),
(5, 29, 'Enrolled'),
(7, 29, 'Enrolled'),
(6, 30, 'Enrolled'),
(8, 30, 'Enrolled'),
(1, 31, 'Enrolled'),
(2, 31, 'Enrolled'),
(3, 32, 'Enrolled'),
(4, 32, 'Enrolled'),
(5, 33, 'Enrolled'),
(6, 33, 'Enrolled'),
(7, 34, 'Enrolled'),
(8, 34, 'Enrolled'),
(1, 35, 'Enrolled'),
(3, 35, 'Enrolled'),
(2, 36, 'Enrolled'),
(4, 36, 'Enrolled');

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

INSERT INTO EQUIPMENT_RESERVATION (EquipmentID, MemberID, StartAt, EndAt, Status) VALUES
(5, 12, datetime('now', '+1 day', 'start of day', '+18 hours'), datetime('now', '+1 day', 'start of day', '+19 hours'), 'Reserved'),
(12, 27, datetime('now', '+2 days', 'start of day', '+10 hours'), datetime('now', '+2 days', 'start of day', '+11 hours'), 'Reserved');

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

INSERT INTO MEMBER_MEMBERSHIP (MemberID, PlanID, Status) VALUES
(7, 1, 'Active'),
(8, 2, 'Active'),
(9, 3, 'Active'),
(10, 1, 'Active'),
(11, 2, 'Active'),
(12, 3, 'Active'),
(13, 1, 'Active'),
(14, 2, 'Active'),
(15, 3, 'Active'),
(16, 1, 'Active'),
(17, 2, 'Active'),
(18, 3, 'Active'),
(19, 1, 'Active'),
(20, 2, 'Active'),
(21, 3, 'Active'),
(22, 1, 'Active'),
(23, 2, 'Active'),
(24, 3, 'Active'),
(25, 1, 'Active'),
(26, 2, 'Active'),
(27, 3, 'Active'),
(28, 1, 'Active'),
(29, 2, 'Active'),
(30, 3, 'Active'),
(31, 1, 'Active'),
(32, 2, 'Active'),
(33, 3, 'Active'),
(34, 1, 'Active'),
(35, 2, 'Active'),
(36, 3, 'Active');

CREATE TABLE REPORT (
    ReportID     INTEGER PRIMARY KEY AUTOINCREMENT,
    MemberID     INTEGER NOT NULL,
    Category     TEXT NOT NULL CHECK (Category IN ('Equipment', 'Class', 'Billing', 'Staff', 'Other')),
    Subject      TEXT NOT NULL,
    Description  TEXT NOT NULL,
    Status       TEXT NOT NULL CHECK (Status IN ('Open', 'In Progress', 'Resolved', 'Closed')) DEFAULT 'Open',
    Priority     TEXT NOT NULL CHECK (Priority IN ('Low', 'Medium', 'High')) DEFAULT 'Medium',
    EquipmentID  INTEGER,
    ClassID      INTEGER,
    CreatedAt    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MemberID)    REFERENCES USER(UserID)           ON DELETE CASCADE,
    FOREIGN KEY (EquipmentID) REFERENCES EQUIPMENT(EquipmentID) ON DELETE SET NULL,
    FOREIGN KEY (ClassID)     REFERENCES FITNESS_CLASS(ClassID)  ON DELETE SET NULL
);

CREATE TABLE REPORT_RESPONSE (
    ResponseID  INTEGER PRIMARY KEY AUTOINCREMENT,
    ReportID    INTEGER NOT NULL,
    UserID      INTEGER NOT NULL,
    Message     TEXT NOT NULL,
    CreatedAt   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ReportID) REFERENCES REPORT(ReportID) ON DELETE CASCADE,
    FOREIGN KEY (UserID)   REFERENCES USER(UserID)     ON DELETE CASCADE
);

CREATE INDEX idx_report_member ON REPORT (MemberID, Status);
CREATE INDEX idx_report_status ON REPORT (Status, CreatedAt);

INSERT INTO REPORT (ReportID, MemberID, Category, Subject, Description, Status, Priority, EquipmentID, ClassID) VALUES
(1, 7,  'Equipment', 'Cable Machine stuck on heavy weight', 'The Cable Machine 01 in the Strength Zone is stuck and won''t adjust. Tried resetting but the pin is jammed.', 'Open', 'High', 9, NULL),
(2, 8,  'Class',     'Pilates class cancelled without notice', 'Tuesday''s Pilates Core Strength was cancelled with no notification. I showed up and the room was empty.', 'In Progress', 'Medium', NULL, 3),
(3, 10, 'Billing',   'Double charge on membership renewal', 'I was charged twice for my Basic membership this month. Transaction refs: TXN-4821 and TXN-4822.', 'Resolved', 'High', NULL, NULL),
(4, 12, 'Staff',     'Trainer was 15 minutes late', 'Ricardo was 15 minutes late to the HIIT Intenso class on Monday. No explanation given.', 'Open', 'Low', NULL, 2),
(5, 9,  'Other',     'Locker room needs better ventilation', 'The men''s locker room on the ground floor has poor air circulation. It gets very stuffy after peak hours.', 'Open', 'Medium', NULL, NULL);

INSERT INTO REPORT_RESPONSE (ReportID, UserID, Message) VALUES
(1, 1, 'Thanks for reporting this. We''ve flagged the Cable Machine for maintenance. A technician will inspect it tomorrow morning.'),
(2, 1, 'We apologise for the inconvenience. The cancellation was due to the trainer''s emergency. We''re working on improving our notification system.'),
(2, 8, 'Thank you for the update. Will there be a make-up class?'),
(3, 1, 'We''ve confirmed the duplicate charge and issued a refund of €19.99 to your card. It should appear within 3–5 business days.'),
(3, 10, 'Received the refund. Thank you for the quick resolution!');
