
<?php

class Auth
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function register($name, $email, $password, $phone, $address, $role)
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (name, email, password, phone, address, role)
                VALUES (:name, :email, :password, :phone, :address, :role)";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashedPassword,
            ':phone' => $phone,
            ':address' => $address,
            ':role' => $role
        ]);
    }

    public function login($email, $password)
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        return $user;
    }
}

?>
