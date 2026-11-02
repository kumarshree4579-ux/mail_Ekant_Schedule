<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Config/Env.php';
require dirname(__DIR__) . '/app/Core/Database.php';

use App\Config\Env;
use App\Core\Database;

Env::load(dirname(__DIR__) . '/.env');

$db = Database::get();

$name = 'Admin';
$email = $_ENV['APP_EMAIL'] ?? 'admin@tvserviceforhotel.com'; // or change this
$password = 'admin123'; // change this before running or login immediately and change
$role = 'admin';

$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo "User with email {$email} already exists.\n";
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$insert = $db->prepare("INSERT INTO users (name, email, password, role, active, otp_required, created_at) VALUES (?, ?, ?, ?, 1, 0, ?)");
$insert->execute([$name, $email, $hash, $role, time()]);

echo "Admin user seeded successfully!\n";
echo "Email: {$email}\n";
echo "Password: {$password}\n";
