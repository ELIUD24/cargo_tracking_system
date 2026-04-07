<?php
// models/User.php

class User {
    private $conn;
    private $table = "users";

    public $id;
    public $name;
    public $email;
    public $password;
    public $role;
    public $phone;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Register new user
    public function register() {
        // Sanitize inputs
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password = htmlspecialchars(strip_tags($this->password));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->phone = htmlspecialchars(strip_tags($this->phone));

        // Check if email exists
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return false;
        }

        // Hash password
        $password_hash = password_hash($this->password, PASSWORD_BCRYPT);

        // Insert query
        $query = "INSERT INTO " . $this->table . " 
                  (name, email, password, role, phone, created_at) 
                  VALUES (:name, :email, :password, :role, :phone, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $password_hash);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":phone", $this->phone);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }

    // Login user
    public function login() {
        $query = "SELECT id, name, email, password, role, phone 
                  FROM " . $this->table . " 
                  WHERE email = :email AND role = :role 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->role = htmlspecialchars(strip_tags($this->role));
        
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":role", $this->role);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($this->password, $row['password'])) {
                $this->id = $row['id'];
                $this->name = $row['name'];
                $this->email = $row['email'];
                $this->role = $row['role'];
                $this->phone = $row['phone'];
                return true;
            }
        }
        
        return false;
    }

    // Get user by ID
    public function readOne() {
        $query = "SELECT id, name, email, role, phone, created_at 
                  FROM " . $this->table . " 
                  WHERE id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->role = $row['role'];
            $this->phone = $row['phone'];
            $this->created_at = $row['created_at'];
            return true;
        }
        
        return false;
    }
}
?>