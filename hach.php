<?php
// Remplace 'TonMotDePasse' par ce que tu veux
$motdepasse_en_clair = "adminprincipal";

// Génération du hash sécurisé
$hash_secise = password_hash($motdepasse_en_clair, PASSWORD_DEFAULT);

echo "<b>Mot de passe en clair :</b> " . $motdepasse_en_clair . "<br>";
echo "<b>Hash à copier dans la BDD :</b> <code>" . $hash_secise . "</code>";
?>