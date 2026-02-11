<?php

class PatientController
{
    private $db;
    private $patient;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->patient = new Patient($this->db);
    }

    private function validateRequestData($data, $isPatch = false)
    {
        if (!$isPatch || isset($data['age'])) {
            $age = $data['age'] ?? null;
            if ($age === null || !is_numeric($age) || $age < 0 || $age > 120) {
                Response::json(400, "Invalid age. Age must be a number between 0 and 120.");
                exit();
            }
        }

        if (!$isPatch || isset($data['gender'])) {
            $gender = $data['gender'] ?? '';
            $validGenders = ['Male', 'Female', 'Other', 'male', 'female', 'other'];
            if (!in_array($gender, $validGenders)) {
                Response::json(400, "Invalid gender. Must be 'Male', 'Female', or 'Other'.");
                exit();
            }
        }

        if (!$isPatch || isset($data['phone'])) {
            $phone = $data['phone'] ?? '';
            if (!preg_match('/^[0-9]{10}$/', $phone)) {
                Response::json(400, "Invalid phone number. Must be exactly 10 digits.");
                exit();
            }
        }
    }

    /**
     * POST /api/patients
     */
    public function store()
    {
        $data = json_decode(file_get_contents("php://input"), true);
        $this->validateRequestData($data);

        $user_email = AuthMiddleware::$currentUserEmail; 
        $phone = $data['phone'];

        try {
            $data['user_email'] = $user_email; 
            if ($this->patient->create($data)) {
                Response::json(201, "Patient created and linked to " . $user_email);
            }
        } catch (PDOException $e) {
            $this->handleDBError($e, $phone, $user_email);
        }
    }

    /**
     * PUT /api/patients/{id}
     */
    public function update($id = null)
    {
        if (empty($id)) {
            Response::json(400, "Update Error: Patient ID is required.");
            exit();
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $auth_email = AuthMiddleware::$currentUserEmail;

        $this->validateRequestData($data);

        $existingPatient = $this->patient->readOnePublic($id);
        
        if (!$existingPatient) {
            Response::json(404, "Update failed: Patient ID $id not found.");
            exit();
        }

        if ($existingPatient['user_email'] !== $auth_email) {
            Response::json(403, "Unauthorized: You cannot update this record.");
            exit();
        }

        $finalData = array_merge($existingPatient, $data);

        try {
            $this->patient->update($id, $auth_email, $finalData); 
            Response::json(200, "Patient updated successfully.");
        } catch (PDOException $e) {
            $this->handleDBError($e, $data['phone'] ?? $existingPatient['phone']);
        }
    }

    /**
     * PATCH /api/patients/{id}
     */
    public function patch($id = null)
    {
        if (empty($id)) {
            Response::json(400, "Patient ID is required.");
            exit();
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $auth_email = AuthMiddleware::$currentUserEmail;

        $this->validateRequestData($data, true);

        $existingPatient = $this->patient->readOnePublic($id);
        if (!$existingPatient) {
            Response::json(404, "Patch failed: Patient ID $id not found.");
            exit();
        }

        if ($existingPatient['user_email'] !== $auth_email) {
            Response::json(403, "Unauthorized: Ownership mismatch.");
            exit();
        }

        $finalData = array_merge($existingPatient, $data);

        try {
            if ($this->patient->update($id, $auth_email, $finalData)) {
                Response::json(200, "Patient partially updated successfully.");
            }
        } catch (PDOException $e) {
            $this->handleDBError($e, $data['phone'] ?? $existingPatient['phone']);
        }
    }

    /**
     * DELETE /api/patients/{id}
     * FIXED: execute() panniya piragu rowCount() check pannurhom
     */
    public function destroy($id = null)
    {
        if (empty($id)) {
            Response::json(400, "Delete Error: Patient ID is required.");
            exit();
        }

        $auth_email = AuthMiddleware::$currentUserEmail;

        try {
            // Step 1: Query-ai prepare panni Statement object-ai vaangurohm
            $stmt = $this->patient->delete($id, $auth_email, false); 
            
            // Step 2: Query-ai execute pannurohm
            if ($stmt->execute()) {
                // Step 3: Success aanaal rowCount() check pannurohm
                if ($stmt->rowCount() > 0) {
                    Response::json(200, "Patient deleted successfully.");
                } else {
                    Response::json(403, "Delete failed: Not authorized or ID not found.");
                }
            }
        } catch (PDOException $e) {
            Response::json(500, "System Error: " . $e->getMessage());
        }
    }

    public function index()
    {
        $user_email = AuthMiddleware::$currentUserEmail;

        if (!$user_email) {
            Response::json(401, "Unauthorized: User context missing.");
            exit();
        }

        $stmt = $this->patient->readAll(); 
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $responseData = [
            "system_info" => [
                "request_by" => $user_email,
                "total_records_in_system" => count($patients),
                "server_time" => date("Y-m-d H:i:s")
            ],
            "patient_records" => $patients
        ];

        Response::json(200, $responseData);
    }

    public function show($id)
    {
        $row = $this->patient->readOnePublic($id);
        
        if ($row) {
            Response::json(200, $row);
        } else {
            Response::json(404, "Patient ID $id not found.");
        }
    }

    private function handleDBError($e, $phone, $email = '')
    {
        if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'Duplicate entry')) {
            Response::json(400, "Duplicate Error: Phone number ($phone) is already registered.");
        } else if (str_contains($e->getMessage(), '1452')) {
            Response::json(404, "Error: No user account found for '" . $email . "'.");
        } else {
            Response::json(500, "System Error: " . $e->getMessage());
        }
        exit();
    }
}