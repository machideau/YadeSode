<?php
class ApiController {
    protected function sendResponse($data, $status = 200, $message = null) {
        header('Content-Type: application/json');
        http_response_code($status);
        
        $response = [
            'status' => $status < 400 ? 'success' : 'error',
            'data' => $data
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    protected function getRequestData() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    protected function validateRequired($data, $required_fields) {
        $missing = [];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->sendResponse(
                null, 
                400, 
                'Champs obligatoires manquants: ' . implode(', ', $missing)
            );
        }
    }
}

?>