<?php
/* ============================================================
api/auth.php
Authentification : connexion, déconnexion, vérification session

Appels depuis le front-end :
POST api/auth.php?action=connexion { email, mot_de_passe }
POST api/auth.php?action=deconnexion
GET api/auth.php?action=verifier
============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
case 'connexion': traiterConnexion(); break;
case 'deconnexion': traiterDeconnexion(); break;
case 'verifier': verifierSession(); break;
case 'inscription': traiterInscription(); break;
default:
http_response_code(400);
echo json_encode(['erreur' => 'Action inconnue']);
}

/* ============================================================
CONNEXION
============================================================ */
function traiterConnexion(): void {
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
http_response_code(405);
echo json_encode(['erreur' => 'Méthode non autorisée']);
return;
}

$data = json_decode(file_get_contents('php://input'), true);

$email = trim($data['email'] ?? '');
$mdp = trim($data['mot_de_passe'] ?? '');

// --- Validation basique ---
if (!$email || !$mdp) {
http_response_code(400);
echo json_encode(['erreur' => 'Email et mot de passe requis']);
return;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
http_response_code(400);
echo json_encode(['erreur' => 'Format d\'email invalide']);
return;
}

// --- Recherche en base ---
$db = getDB();
$stmt = $db->prepare(
'SELECT id, nom, prenom, email, mot_de_passe, role, statut
FROM utilisateurs WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($mdp, $user['mot_de_passe'])) {
http_response_code(401);
echo json_encode(['erreur' => 'Email ou mot de passe incorrect']);
return;
}

if ($user['statut'] !== 'actif') {
http_response_code(403);
echo json_encode(['erreur' => 'Compte désactivé. Contactez l\'administration.']);
return;
}

// --- Créer la session ---
$_SESSION['utilisateur_id'] = (int)$user['id'];
$_SESSION['nom'] = $user['nom'];
$_SESSION['prenom'] = $user['prenom'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];

// --- Récupérer les infos spécifiques au rôle ---
$infos = chargerInfosRole($db, $user);

// --- Redirection selon le rôle ---
$redirections = [
'admin' => 'tableau-de-bord.html',
'professeur' => 'tableau-de-bord.html',
'etudiant' => 'tableau-de-bord.html',
];

echo json_encode([
'succes' => true,
'utilisateur' => [
'id' => (int)$user['id'],
'nom' => $user['nom'],
'prenom' => $user['prenom'],
'email' => $user['email'],
'role' => $user['role'],
'infos' => $infos,
],
'redirection' => $redirections[$user['role']] ?? 'connexion.html',
]);
}

/* ============================================================
Charger les infos spécifiques au rôle après connexion
============================================================ */
function chargerInfosRole(PDO $db, array $user): array {
switch ($user['role']) {

case 'etudiant':
$stmt = $db->prepare(
'SELECT e.id, e.matricule, e.photo,
f.nom AS filiere,
n.libelle AS niveau,
o.nom AS option_nom,
aa.libelle AS annee_academique
FROM etudiants e
JOIN classes c ON e.classe_id = c.id
JOIN filieres f ON c.filiere_id = f.id
JOIN niveaux n ON c.niveau_id = n.id
LEFT JOIN options_filiere o ON c.option_id = o.id
JOIN annees_academiques aa ON c.annee_academique_id = aa.id
WHERE e.utilisateur_id = ? LIMIT 1'
);
$stmt->execute([$user['id']]);
$infos = $stmt->fetch() ?: [];

if (!empty($infos['id'])) {
$_SESSION['etudiant_id'] = (int)$infos['id'];
}
return $infos;

case 'professeur':
$stmt = $db->prepare(
'SELECT id, specialite, grade, telephone
FROM professeurs WHERE utilisateur_id = ? LIMIT 1'
);
$stmt->execute([$user['id']]);
$infos = $stmt->fetch() ?: [];

if (!empty($infos['id'])) {
$_SESSION['professeur_id'] = (int)$infos['id'];
}
return $infos;

default:
return [];
}
}

/* ============================================================
INSCRIPTION LIBRE (crée un compte étudiant en attente de validation)
============================================================ */
function traiterInscription(): void {
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
http_response_code(405);
echo json_encode(['erreur' => 'Méthode non autorisée']);
return;
}

$data = json_decode(file_get_contents('php://input'), true);
$nom = trim($data['nom'] ?? '');
$prenom = trim($data['prenom'] ?? '');
$email = trim($data['email'] ?? '');
$mdp = trim($data['mot_de_passe'] ?? '');

if (!$nom || !$prenom || !$email || !$mdp) {
http_response_code(400);
echo json_encode(['erreur' => 'Tous les champs sont obligatoires']);
return;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
http_response_code(400);
echo json_encode(['erreur' => 'Format d\'email invalide']);
return;
}

if (strlen($mdp) < 6) {
http_response_code(400);
echo json_encode(['erreur' => 'Le mot de passe doit contenir au moins 6 caractères']);
return;
}

$db = getDB();

// Vérifier si l'email existe déjà
$check = $db->prepare('SELECT id FROM utilisateurs WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
http_response_code(409);
echo json_encode(['erreur' => 'Cet email est déjà utilisé']);
return;
}

$hash = password_hash($mdp, PASSWORD_BCRYPT);
$stmt = $db->prepare(
'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, statut)
VALUES (?, ?, ?, ?, "etudiant", "inactif")'
);
// statut "inactif" : l'admin doit valider le compte et assigner une classe
$stmt->execute([$nom, $prenom, $email, $hash]);

echo json_encode([
'succes' => true,
'message' => "Compte créé. Attendez la validation par l'administration."
]);
}

/* ============================================================
DÉCONNEXION
============================================================ */
function traiterDeconnexion(): void {
deconnecter();
echo json_encode(['succes' => true, 'redirection' => 'connexion.html']);
}

/* ============================================================
VÉRIFICATION DE SESSION (appelée au chargement de chaque page)
============================================================ */
function verifierSession(): void {
if (!estConnecte()) {
echo json_encode(['connecte' => false]);
return;
}

echo json_encode([
'connecte' => true,
'role' => getRole(),
'nom' => $_SESSION['nom'] ?? '',
'prenom' => $_SESSION['prenom'] ?? '',
'email' => $_SESSION['email'] ?? '',
]);
}

