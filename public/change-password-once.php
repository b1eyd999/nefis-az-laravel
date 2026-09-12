<?php
// Müvəqqəti: admin şifrəsini bir dəfəlik dəyişmək üçün. İstifadədən sonra silinməlidir.

$envPath = __DIR__ . '/../../nefis-laravel/.env';
$env = file_get_contents($envPath);

function envVal($env, $key) {
    if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $env, $m)) {
        return trim($m[1]);
    }
    return null;
}

$host = envVal($env, 'DB_HOST');
$db = envVal($env, 'DB_DATABASE');
$user = envVal($env, 'DB_USERNAME');
$pass = envVal($env, 'DB_PASSWORD');

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
$newHash = password_hash('89515018784cemil!!!!', PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->execute([$newHash, 'yainov609@gmail.com']);

echo $stmt->rowCount() > 0 ? "Password updated successfully." : "No user matched — nothing updated.";
