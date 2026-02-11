<?php

class Patient
{
    private $conn;
    private $table_name = "patients";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * 1. Create Patient
     */
    public function create($data)
    {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET name=:name, age=:age, gender=:gender, phone=:phone, address=:address, user_email=:user_email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $data['name']);
        $stmt->bindParam(":age", $data['age']);
        $stmt->bindParam(":gender", $data['gender']);
        $stmt->bindParam(":phone", $data['phone']);
        $stmt->bindParam(":address", $data['address']);
        $stmt->bindParam(":user_email", $data['user_email']); 
        return $stmt->execute();
    }

    /**
     * 2. Update Patient
     */
    public function update($id, $auth_email, $data, $bypassEmail = false)
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET name = :name, age = :age, gender = :gender, 
                      phone = :phone, address = :address, updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";
        
        if (!$bypassEmail) {
            $query .= " AND user_email = :user_email";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':age', $data['age']);
        $stmt->bindParam(':gender', $data['gender']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':id', $id);
        
        if (!$bypassEmail) {
            $stmt->bindParam(':user_email', $auth_email);
        }

        return $stmt->execute();
    }

    /**
     * 3. Delete Patient
     * FIXED: Inga boolean-ku badhilaa statement object-ai return pannuvom.
     */
    public function delete($id, $email, $bypassEmail = false)
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        
        if (!$bypassEmail) {
            $query .= " AND user_email = :email";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        if (!$bypassEmail) {
            $stmt->bindParam(":email", $email);
        }

        // execute() panninaalum statement object-aiye thiruppi anuppuvom
        // Ippo Controller-la $stmt->rowCount() work aagum.
        return $stmt; 
    }

    /**
     * 4. Read All
     */
    public function readAll()
    {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    /**
     * 5. Read By User
     */
    public function readByUser($email)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_email = :email ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt;
    }

    /**
     * 6. Read One (Strict Ownership)
     */
    public function readOne($id, $auth_email)
    {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id = ? AND user_email = ? 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id, $auth_email]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 7. Read One Public
     */
    public function readOnePublic($id)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC); 
    }
}