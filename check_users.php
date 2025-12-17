<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Users ===\n";
$users = App\Models\User::all(['id', 'name', 'email', 'role']);
foreach ($users as $user) {
    echo "ID: {$user->id}, Name: {$user->name}, Email: {$user->email}, Role: {$user->role}\n";
}

echo "\n=== Franchise Users (pivot table) ===\n";
$franchiseUsers = DB::table('franchise_users')->get();
foreach ($franchiseUsers as $fu) {
    $user = App\Models\User::find($fu->user_id);
    $franchise = App\Models\Franchise::find($fu->franchise_id);
    echo "User: {$user->email}, Franchise: {$franchise->name}, Role: {$fu->role}\n";
}
