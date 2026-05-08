<?php

require "./../vendor/autoload.php";

$method = $_SERVER['REQUEST_METHOD'];
$db = new MongoDB\Client("mongodb://mongo:27017");
$artemis_crew = $db->artemis->artemis_crew;

function getRandomString($n) {
    return bin2hex(openssl_random_pseudo_bytes($n / 2));
}


if($method == 'POST'){
    $percorso = $_SERVER['REQUEST_URI'];
    if (str_ends_with($percorso, '/portrait')){
        $parti = explode("/", $percorso);
        $id_astro = trim($parti[count($parti) - 2]);

        if (!isset($_FILES['image'])) {
            http_response_code(400);
            exit;
        }

        $dir = 'uploads/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $estensione = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $nuovoNome = getRandomString(16) . "." . $estensione;
        $pathFinale = $dir . $nuovoNome;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $pathFinale)) {
            try {
                $res = $artemis_crew->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($id_astro)], 
                    ['$set' => ['image_path' => $pathFinale]]          
                );

                http_response_code(200);
                echo json_encode([
                    "message" => "è andato tutto bene",
                    "file_path" => $pathFinale  ,
                ]);

            } catch (Exception $e) {
                http_response_code(500);
            }
        } else {
            http_response_code(500);
        }

    }else{

        $json = json_decode(file_get_contents("php://input"),true);
        $brand_new_astro = [
            "nome" => $json['name'],
            "ruolo" => $json['role'],
            "agenzia" => $json['agency'],
            "stato" => "In addestramento" 
        ];
        $res = $artemis_crew->insertOne($brand_new_astro);
        $newId = $res->getInsertedId();

        http_response_code(201); 
        echo "new Id " . $newId;

    }

}else if($method == 'GET'){
    http_response_code(200);
    header("Content-type: application/json"); 
    $cursor = $artemis_crew->find([]);
    $result = [];
    foreach($cursor as $astro){
        //creo una lista di oggetti, prima li stavo stampando uno dopo l'altro
        $result[] = $astro;
    }
    //ora stampo la lista invece
    echo json_encode($result);
}else if($method == 'DELETE'){
    $percorso = $_SERVER['REQUEST_URI'];
    $parti = explode("/", trim($percorso, '/'));
    
    $id_astro = end($parti);

    try {
        $oid = new MongoDB\BSON\ObjectId($id_astro);
        $astronauta = $artemis_crew->findOne(['_id' => $oid]);

        if ($astronauta) {
            if (isset($astronauta['image_path']) && file_exists($astronauta['image_path'])) {
                unlink($astronauta['image_path']); // Elimina il file fisico
            }

            $deleteResult = $artemis_crew->deleteOne(['_id' => $oid]);

            //spero che sia solo uno
            if ($deleteResult->getDeletedCount() === 1) {
                http_response_code(200);
            }
            //se non trova l'astronauta
        } else {
            http_response_code(404);
        }

    } catch (Exception $e) {
        http_response_code(400);
    }
}

?>