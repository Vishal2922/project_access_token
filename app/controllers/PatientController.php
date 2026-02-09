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

    /**
     * Unified Validator: Enforces Phone, Gmail, Gender, and Age constraints
     */
    private function validateRequestData($data, $isPatch = false)
    {
        // 1. Age Validation
        if (!$isPatch || isset($data['age'])) {
            $age = $data['age'] ?? null;
            if ($age === null || !is_numeric($age) || $age < 0 || $age > 120) {
                Response::json(400, "Invalid age. Age must be a number between 0 and 120.");
                exit();
            }
        }

        // 2. Gender Validation
        if (!$isPatch || isset($data['gender'])) {
            $gender = $data['gender'] ?? '';
            $validGenders = ['Male', 'Female', 'Other', 'male', 'female', 'other'];
            if (!in_array($gender, $validGenders)) {
                Response::json(400, "Invalid gender. Must be 'Male', 'Female', or 'Other'.");
                exit();
            }
        }

        // 3. Phone Validator (Exactly 10 digits)
        if (!$isPatch || isset($data['phone'])) {
            $phone = $data['phone'] ?? '';
            if (!preg_match('/^[0-9]{10}$/', $phone)) {
                Response::json(400, "Invalid phone number. Must be exactly 10 digits.");
                exit();
            }
        }

        // 4. Gmail Validator
        if (isset($data['user_email']) || isset($data['email'])) {
            $email = $data['user_email'] ?? $data['email'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@gmail.com')) {
                Response::json(400, "Invalid email format. Only @gmail.com addresses are accepted.");
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

        $user_email = $data['user_email'] ?? $data['email'];
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

        $existingPatient = $this->patient->readOne($id, $auth_email);
        if (!$existingPatient) {
            Response::json(404, "Update failed: Patient not found or unauthorized.");
            exit();
        }

        // Merge existing data with new input to prevent NULL overwrites
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
     * FIXED: Now merges data to prevent NULL values in unprovided fields
     */
    public function patch($id = null)
    {
        if (empty($id)) {
            Response::json(400, "Patient ID is required.");
            exit();
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $auth_email = AuthMiddleware::$currentUserEmail;

        // 1. Validate only the fields that were actually provided
        $this->validateRequestData($data, true);

        // 2. Fetch the existing record
        $existingPatient = $this->patient->readOne($id, $auth_email);
        if (!$existingPatient) {
            Response::json(404, "Patch failed: Patient not found or unauthorized.");
            exit();
        }

        // 3. OVERCOME NULL ISSUE: Merge existing data with the partial updates
        // This ensures unprovided fields (like age or gender) keep their old values.
        $finalData = array_merge($existingPatient, $data);

        try {
            // Send the FULL merged array to the model update method
            if ($this->patient->update($id, $auth_email, $finalData)) {
                Response::json(200, "Patient partially updated successfully.");
            }
        } catch (PDOException $e) {
            $this->handleDBError($e, $data['phone'] ?? $existingPatient['phone']);
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

    /**
     * DELETE /api/patients/{id}
     */
    public function destroy($id = null)
    {
        if (empty($id)) {
            Response::json(400, "Delete Error: Patient ID is required.");
            exit();
        }

        $auth_email = AuthMiddleware::$currentUserEmail;

        try {
            $stmt = $this->patient->delete($id, $auth_email);
            if ($stmt->rowCount() > 0) {
                Response::json(200, "Patient deleted successfully.");
            } else {
                Response::json(404, "Delete failed: Patient ID $id not found.");
            }
        } catch (PDOException $e) {
            Response::json(500, "System Error: " . $e->getMessage());
        }
    }

    /**
     * GET /api/patients
     * Returns the complete profile of the logged-in user and their patient list
     */
    public function index()
    {
        // 1. Get Authenticated Email from JWT via Middleware [cite: 124-131]
        $user_email = AuthMiddleware::$currentUserEmail;

        if (!$user_email) {
            Response::json(401, "Unauthorized: User context missing.");
            exit();
        }

        // 2. Healthcare Logic: Fetch patients linked to this specific user [cite: 133-134]
        $stmt = $this->patient->readByUser($user_email);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /**
         * 3. Response Structure: This satisfies healthcare dashboard requirements.
         * Ithu moolama frontend-la "Welcome [Email]" matum "Total Patients: [Count]" 
         * nu easy-aa display panna mudiyum.
         */
        $responseData = [
            "user_profile" => [
                "email" => $user_email,
                "role" => "Healthcare Provider",
                "total_patients_linked" => count($patients),
                "server_time" => date("Y-m-d H:i:s")
            ],
            "patient_records" => $patients
        ];

        // 4. Return the combined data
        if (count($patients) >= 0) {
            Response::json(200, $responseData);
        } else {
            Response::json(404, "No patient records found linked to your account.");
        }
    }

    public function show($id)
    {
        $user_email = AuthMiddleware::$currentUserEmail;
        $row = $this->patient->readOne($id, $user_email);
        if ($row) {
            Response::json(200, $row);
        } else {
            Response::json(404, "Patient not found.");
        }
    }
}
