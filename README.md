[![Open in Visual Studio Code](https://classroom.github.com/assets/open-in-vscode-2e0aaae1b6195c2367325f4f02e2d04e9abb55f0b24a779b69b11b9e10269abc.svg)](https://classroom.github.com/online_ide?assignment_repo_id=23453743&assignment_repo_type=AssignmentRepo)

# LTW Project - ltw02g06

A web platform for personal trainers built with PHP, SQLite, HTML and CSS.

## Requirements

- PHP 8.0 or higher (with PDO and PDO_SQLite extensions enabled)
- A terminal / command prompt

## Setup

### 1. Clone the repository

```bash
git clone https://github.com/FEUP-LTW-2026/ltw-project-ltw02g06.git
cd ltw-project-ltw02g06
```

### 2. Create the database connection file

Create the file `Database/db.php` with the following content:

```php
<?php
function getDB(): PDO {
    $db = new PDO('sqlite:' . __DIR__ . '/LTW-DataBAse-1.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $db;
}
?>
```

### 3. Start the local PHP server

From the project root, run:

```bash
php -S localhost:8000
```

Then open your browser at [http://localhost:8000](http://localhost:8000).

## Connecting pages to the database

HTML pages that need dynamic data should be renamed to `.php` and use PDO to query the database.

**Fetch a single row:**
```php
<?php
require_once 'Database/db.php';
$db = getDB();

$stmt = $db->prepare('SELECT * FROM trainers WHERE id = ?');
$stmt->execute([$_GET['id'] ?? 1]);
$trainer = $stmt->fetch();
?>
```

**Fetch all rows:**
```php
$stmt = $db->query('SELECT * FROM trainers');
$trainers = $stmt->fetchAll();
```

**Insert data:**
```php
$stmt = $db->prepare('INSERT INTO bookings (user_id, trainer_id, date) VALUES (?, ?, ?)');
$stmt->execute([$userId, $trainerId, $date]);
```

**Output data safely in HTML (always use `htmlspecialchars` to prevent XSS):**
```php
<h1><?= htmlspecialchars($trainer['name']) ?></h1>
<p><?= htmlspecialchars($trainer['bio']) ?></p>
```

> Always use prepared statements with `?` placeholders — never put user input directly in SQL queries.

## Project Structure

```
ltw-project-ltw02g06/
├── Database/
│   ├── LTW-DataBAse-1.db   # SQLite database
│   └── db.php              # PDO connection helper
├── profile.php             # Trainer profile page
├── book-class.php          # Class booking page
├── index.php               # Home page
├── style.css               # Shared styles
└── README.md
```
