<?php
// Update credentials directly in the database
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'fidelity_ncrc';
$username = 'root';
$password = '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Update Database Credentials</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #4CAF50; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #45a049; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .sql { background: #f8f9fa; padding: 10px; border-left: 4px solid #007bff; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Update Database Credentials</h1>
        
        <form method='POST'>
            <div class='form-group'>
                <label>Username to update:</label>
                <input type='text' name='username' value='admin' required>
            </div>
            
            <div class='form-group'>
                <label>New Password:</label>
                <input type='text' name='new_password' required>
            </div>
            
            <div class='form-group'>
                <label>Action:</label>
                <select name='action'>
                    <option value='update'>Update Password</option>
                    <option value='verify'>Verify Current Password</option>
                    <option value='list'>List All Users</option>
                </select>
            </div>
            
            <button type='submit'>Execute</button>
        </form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $targetUsername = $_POST['username'];
    $newPassword = $_POST['new_password'] ?? '';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        switch ($action) {
            case 'update':
                if (empty($newPassword)) {
                    echo "<div class='result error'>New password is required for update</div>";
                    break;
                }
                
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
                $stmt->execute([$hash, $targetUsername]);
                
                $affected = $stmt->rowCount();
                
                echo "<div class='result success'>
                    <h3>Password Updated Successfully!</h3>
                    <p>Username: $targetUsername</p>
                    <p>Rows affected: $affected</p>
                    <div class='sql'>
                        UPDATE users SET password_hash = '$hash' WHERE username = '$targetUsername';
                    </div>
                    <p><strong>New password:</strong> $newPassword</p>
                    <p><strong>Generated hash:</strong> $hash</p>
                </div>";
                break;
                
            case 'verify':
                $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
                $stmt->execute([$targetUsername]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    echo "<div class='result success'>
                        <h3>Current Hash for '$targetUsername':</h3>
                        <div class='sql'>" . htmlspecialchars($user['password_hash']) . "</div>
                        <p>Hash length: " . strlen($user['password_hash']) . " characters</p>
                    </div>";
                } else {
                    echo "<div class='result error'>User '$targetUsername' not found</div>";
                }
                break;
                
            case 'list':
                $stmt = $conn->query("SELECT id, username, email, role, is_active FROM users ORDER BY id");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<div class='result success'>
                    <h3>All Users in Database:</h3>
                    <table border='1' cellpadding='10' cellspacing='0' style='width: 100%;'>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Active</th>
                        </tr>";
                
                foreach ($users as $user) {
                    echo "<tr>
                        <td>{$user['id']}</td>
                        <td>{$user['username']}</td>
                        <td>{$user['email']}</td>
                        <td>{$user['role']}</td>
                        <td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>
                    </tr>";
                }
                
                echo "</table></div>";
                break;
        }
        
    } catch (PDOException $e) {
        echo "<div class='result error'>
            <h3>Database Error:</h3>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
            <p>Check your database configuration in config.php</p>
        </div>";
    }
}

echo "</div></body></html>";
?>