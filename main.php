<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
include('connection.php');


class Main
{
    private $conn;

    public function __construct()
    {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }

    public function addPost($json)
    {
        $json = json_decode($json, true);

        try {
            if (!isset($json["user_id"]) || !isset($json["post_content"])) {
                return json_encode(array("error" => "User ID or Post Content is empty"));
            }
            $sql = "INSERT INTO `posts`(`user_id`, `post_content`, `created_at`) VALUES (:user_id, :post_content, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":user_id", $json['user_id']);
            $stmt->bindParam(':post_content', $json['post_content'], PDO::PARAM_STR);


            if ($stmt->execute()) {
                return json_encode(array("success" => "Hugot Posted"));
            } else {
                return json_encode(array("error" => $stmt->errorInfo()));
            }
        } catch (Exception $e) {
            return json_encode(array("error", $e->getMessage()));
        }
    }

    public function getPosts()
    {
        try {

            $sql = "SELECT 
                        posts.post_id,
                        posts.user_id, 
                        posts.post_content, 
                        posts.created_at, 
                        posts.update_at, 
                        users.username,
                        COALESCE(COUNT(CASE 
                                        WHEN reaction_type <> '' AND reaction_type IS NOT NULL 
                                        THEN 1 
                                        ELSE NULL 
                                    END), 0) AS total_reactions
                    FROM 
                        posts
                    INNER JOIN 
                        users ON posts.user_id = users.user_id
                    LEFT JOIN 
                        reactions ON posts.post_id = reactions.post_id
                    GROUP BY 
                        posts.post_id, 
                        posts.user_id, 
                        posts.post_content, 
                        posts.created_at, 
                        posts.update_at, 
                        users.firstname
                    ORDER BY 
                        posts.created_at DESC
                    LIMIT 0, 30

                    ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(array("success" => $result));
        } catch (Exception $e) {
            return json_encode(array("error", $e->getMessage()));
        }
    }

    public function reactToPost($json)
    {
        $json = json_decode($json, true);

        try {
            if (isset($json["post_id"]) && isset($json["user_id"]) && isset($json["reaction"])) {

                $checkSql = "SELECT COUNT(*) FROM `reactions`
                WHERE `post_id` = :post_id
                AND `user_id` = :user_id";

                $checkStmt = $this->conn->prepare($checkSql);
                $checkStmt->bindParam(":post_id", $json["post_id"], PDO::PARAM_INT);
                $checkStmt->bindParam(":user_id", $json["user_id"]);
                $checkStmt->execute();
                $reactionExists = $checkStmt->fetchColumn() > 0;

                if ($reactionExists) {

                    $sql = "UPDATE `reactions`
                            SET `reaction_type` = :reaction, `reacted_at` = NOW()
                            WHERE `post_id` = :post_id
                            AND `user_id` = :user_id";

                    $stmt = $this->conn->prepare($sql);
                    $stmt->bindParam(":post_id", $json["post_id"], PDO::PARAM_INT);
                    $stmt->bindParam(":user_id", $json["user_id"]);
                    $stmt->bindParam(":reaction", $json["reaction"]);
                } else {

                    $sql = "INSERT INTO `reactions`(`post_id`, `user_id`, `reaction_type`, `reacted_at`)
                            VALUES (:post_id, :user_id, :reaction, NOW())";

                    $stmt = $this->conn->prepare($sql);
                    $stmt->bindParam(":post_id", $json["post_id"], PDO::PARAM_INT);
                    $stmt->bindParam(":user_id", $json["user_id"]);
                    $stmt->bindParam(":reaction", $json["reaction"]);
                }

                if ($stmt->execute()) {
                    return json_encode(array("success" => true));
                } else {
                    return json_encode(array("error" => $stmt->errorInfo()));
                }
            } else {
                return json_encode(array("error" => "Null Value Detected"));
            }
        } catch (Exception $e) {
            return json_encode(array("error" => $e->getMessage()));
        } finally {
            unset($this->conn);
            unset($stmt);
            unset($checkStmt);
        }
    }

    public function getReactions()
    {
        try {
            $sql = "SELECT post_id, reaction_type, user_id FROM reactions WHERE reaction_type <> '' AND reaction_type IS NOT NULL ";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(array("success" => $reactions));
        } catch (Exception $e) {
            return json_encode(array("error" => $e->getMessage()));
        } finally {
            unset($this->conn);
            unset($stmt);
        }
    }


}

$main_api = new Main();
if ($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_REQUEST['operation']) && isset($_REQUEST['json'])) {
        $operation = $_REQUEST['operation'];
        $json = $_REQUEST['json'];

        switch ($operation) {
            case 'addPost':
                echo $main_api->addPost($json);
                break;

            case 'getPosts':
                echo $main_api->getPosts();
                break;

            case 'reactToPost':
                echo $main_api->reactToPost($json);
                break;

            case 'getReactions':
                echo $main_api->getReactions();
                break;

            default:
                echo json_encode(["error" => "Invalid operation"]);
                break;
        }
    } else {
        echo json_encode(["error" => "Missing parameters"]);
    }
} else {
    echo json_encode(["error" => "Invalid request method"]);
}

?>