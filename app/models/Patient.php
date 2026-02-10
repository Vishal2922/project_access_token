<?php
class Patient
{
    private $conn;
    private $table_name = "patients";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // 1. Create Patient
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
     * Update/Patch Query
     * This implementation prevents NULL overwrites by using the merged data from the controller.
     */
    public function update($id, $auth_email, $data)
    {
        $query = "UPDATE " . $this->table_name . " 
              SET name = :name, 
                  age = :age, 
                  gender = :gender, 
                  phone = :phone, 
                  address = :address,
                  updated_at = CURRENT_TIMESTAMP
              WHERE id = :id 
              AND user_email = :user_email";

        $stmt = $this->conn->prepare($query);

        // Bind all merged values to prevent NULLs // put values to placeholder
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':age', $data['age']);
        $stmt->bindParam(':gender', $data['gender']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_email', $auth_email);

        return $stmt->execute();
    }

    // 3. Delete Patient 
    public function delete($id, $email)
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":email", $email);

        $stmt->execute();
        return $stmt; // Boolean-ku bathila statement-ai return pannunga!
    }

    /**
     * 6. Read All Patients
     * Fetches every record in the patients table
     */
    public function readAll()
    {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readByUser($email)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_email = :email ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt;
    }

    public function readOne($id, $auth_email)
    {
        $query = "SELECT * FROM " . $this->table_name . " 
              WHERE id = ? AND user_email = ? 
              LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id, $auth_email]);

        return $stmt->fetch(PDO::FETCH_ASSOC); // Returns the existing record to be merged in the Controller
    }
}
