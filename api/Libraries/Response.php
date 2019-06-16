<?php 
Class Response {

    function send($data, $message, $response = 200) {
        header('Content-Type: application/json');
        http_response_code($response);
        $response = array(
            "data" => $data,
            "message" => $message
        );
        echo json_encode($response);
        exit;
    }
}
?>