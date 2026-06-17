
<?php
// 1. Configuration des accès à la base de données
$host     = 'localhost';
$db_name   = 'db';
$user_name = 'appuser';
$password = 'secretpassword';

// 2. Connexion à la base de données via PDO
try{
    $PDO = new PDO("mysql:host=$host;dbname:$db_name", $user_name, $password); //Connexion avec PDO (user +password)
    $PDO->SetAttribute(PDO::Attr_ERMODE, PDO::ERMONE_Exception); // Gestion erreur

    echo "[INFO] Connexion réussie à la base de données.\n";


    // 3. Affichage du résultat
    $ligneSupprimees = $stmt->rowCount(); 
    echo "[SUCCÈS] Nettoyage terminé. Nombre de doublons anciens supprimés : $ligneSupprimees - Date : $date\n"; 
    //avec date du compte (dans colonne createdAt) la plus ancienne pour chaque doublon

    $date = date('YYYY-mm-dd'); // = Format de date

    // faire un choix entre garder le plus ancien ou le plus récent (ici on garde le plus ancien)

    $stmt = $PDO->prepare("SELECT email, MIN(createdAt) AS date FROM contacts GROUP BY email HAVING COUNT(email) > 1");
    $stmt->execute();

    echo "[INFO] Liste des doublons anciens :\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Email : " . $row['email'] . " - Date la plus ancienne : " . $row['date'] . "\n";
    }


    // 4. Préparation de la requête de suppression (en fonction de la date)

    $sql = "DELETE c1 FROM contacts_brevo c1 INNER JOIN contacts_brevo c2 ON c1.email = c2.email AND c1.createdAt > c2.createdAt;"



    // 5. Exécution de la requête
    $stmt = $PDO->prepare($sql);
    $stmt->execute();


}

catch (PDOException $e) {
    echo "[ERREUR] Impossible d'exécuter la commande : " . $e->getMessage() . "\n";

}
