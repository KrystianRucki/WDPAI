<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class UsersController extends AppController {

    public function search() {
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

         if ($contentType === "application/json") {
            $userRepository = new UsersRepository();
            $input = json_decode(file_get_contents("php://input"), true);
            $searchTerm = $input["search"] ?? '';

            $results = $userRepository->searchUsers($searchTerm);

            header('Content-type: application/json');
            http_response_code(200);

            echo json_encode($results);
            return;

         } else {
             http_response_code(400);
             echo json_encode(["error" => "Content type must be application/json"]);
             return;
         }

        
        http_response_code(404);
    }

    public function delete() {
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

        if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
            http_response_code(405);
            echo json_encode(["error" => "Method not allowed"]);
            return;
        }

        if ($contentType !== "application/json") {
            http_response_code(400);
            echo json_encode(["error" => "Content type must be application/json"]);
            return;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        $userId = isset($input["id"]) ? (int) $input["id"] : 0;

        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid user id"]);
            return;
        }

        $userRepository = new UsersRepository();
        $deleted = $userRepository->deleteUser($userId);

        header('Content-type: application/json');

        if (!$deleted) {
            http_response_code(404);
            echo json_encode(["error" => "User not found"]);
            return;
        }

        http_response_code(200);
        echo json_encode(["deleted" => true]);
    }
}
