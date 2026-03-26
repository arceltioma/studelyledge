<?php

function getPDO() {
    static $pdo = null;

    if ($pdo === null) {

        $host = 'localhost';
        $db   = 'studelyledge'; // ⚠️ adapte si besoin
        $user = 'root';
        $pass = '';

        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$db;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die("Erreur connexion DB : " . $e->getMessage());
        }
    }

    return $pdo;
}