<?php
declare(strict_types=1);
// PHP 8.2+ ; aucun contenu de demande ni secret dans les journaux applicatifs.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
set_exception_handler(function (Throwable $error): void {
    respond(503, ['ok' => false, 'message' => 'L’envoi est momentanément indisponible. Vos informations sont conservées dans le formulaire. Vous pouvez réessayer plus tard ou nous écrire à arnaud.feret@jmcpropretenature.fr.']);
});
$configPath = dirname(__DIR__) . '/jmc-private/contact-config.php';
if (!is_file($configPath)) respond(503, ['ok'=>false, 'message'=>'L’envoi n’est pas encore activé. Vos informations sont conservées dans le formulaire. Vous pouvez nous écrire à arnaud.feret@jmcpropretenature.fr.']);
$config = require $configPath;
if (!is_array($config) || ($config['enabled'] ?? false) !== true || strlen((string)($config['rate_secret'] ?? '')) < 64) {
    respond(503, ['ok'=>false, 'message'=>'L’envoi n’est pas encore activé. Vos informations sont conservées dans le formulaire. Vous pouvez nous écrire à arnaud.feret@jmcpropretenature.fr.']);
}
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET','POST'], true)) { header('Allow: GET, POST'); respond(405, ['ok'=>false,'message'=>'Méthode non autorisée.']); }
// Ne pas faire confiance aux en-têtes X-Forwarded-* fournis par le client.
$https = isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
if (!$https) respond(403, ['ok'=>false,'message'=>'Une connexion HTTPS est nécessaire pour envoyer votre demande.']);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('JMC_CONTACT');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
if (!session_start()) throw new RuntimeException('session unavailable');
$now = time();
$_SESSION['forms'] = array_filter($_SESSION['forms'] ?? [], static fn($entry) => ($entry['time'] ?? 0) > $now - 3600);
if ($method === 'GET') {
    if (count($_SESSION['forms']) >= 5) array_shift($_SESSION['forms']);
    $token = bin2hex(random_bytes(32));
    $_SESSION['forms'][$token] = ['time'=>$now,'sent'=>false];
    respond(200, ['ok'=>true,'token'=>$token]);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) respond(413,['ok'=>false,'message'=>'Votre demande est trop longue. Réduisez le texte et réessayez.']);
function field(string $name, int $max, bool $multiline = false): string {
    $value = $_POST[$name] ?? '';
    if (!is_string($value) || !preg_match('//u', $value) || strlen($value) > $max || preg_match($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $value)) {
        respond(422,['ok'=>false,'message'=>'Un champ contient une valeur invalide ou trop longue. Vérifiez votre saisie.']);
    }
    return trim($value);
}
$token = field('token',128);
if ($token === '' || !isset($_SESSION['forms'][$token])) respond(403,['ok'=>false,'refresh'=>true,'message'=>'Votre session a expiré. Réessayez : votre saisie est conservée.']);
if ($_SESSION['forms'][$token]['sent']) respond(200,['ok'=>true]); // Double clic / réponse perdue : pas de second e-mail.
if (field('website',200) !== '' || $now - $_SESSION['forms'][$token]['time'] < 3) respond(422,['ok'=>false,'message'=>'Veuillez patienter quelques secondes puis réessayer.']);
$limits = ['nom'=>200,'profil'=>100,'email'=>254,'telephone'=>60,'activite'=>100,'commune'=>240,'message'=>12000];
$values=[];
foreach ($limits as $key=>$max) $values[$key]=field($key,$max,$key==='message');
foreach (['nom','profil','email','telephone','activite','commune','message'] as $key) if ($values[$key]==='') respond(422,['ok'=>false,'message'=>'Veuillez compléter tous les champs obligatoires.']);
if (field('consentement',5)!=='1') respond(422,['ok'=>false,'message'=>'Veuillez confirmer avoir pris connaissance de la politique de confidentialité.']);
if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) respond(422,['ok'=>false,'message'=>'Vérifiez votre adresse e-mail.']);
if (!in_array($values['profil'],['Particulier','Professionnel','Syndic de copropriété','Agence immobilière'],true) || !in_array($values['activite'],['Espaces verts','Ménage et entretien','Vitres et vitrines','Petits travaux et maintenance','Plusieurs activités'],true)) respond(422,['ok'=>false,'message'=>'Sélectionnez un profil et une activité valides.']);
$from = $config['from'] ?? '';
if (!is_string($from) || !preg_match('/\A[a-zA-Z0-9._+-]+@jmcpropretenature\.fr\z/',$from) || !filter_var($from,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('invalid sender');
$directory = $config['rate_directory'] ?? '';
if (!is_string($directory) || !is_dir($directory) || !is_writable($directory)) throw new RuntimeException('rate storage unavailable');
// Un compteur par IP, pseudonymisé avec secret ; ne pas utiliser X-Forwarded-For.
$key = hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'),$config['rate_secret']);
$handle=fopen($directory.'/'.$key.'.json','c+');
if (!$handle || !flock($handle,LOCK_EX)) throw new RuntimeException('lock unavailable');
$entries=json_decode(stream_get_contents($handle),true);
$entries=array_values(array_filter(is_array($entries)?$entries:[],static fn($t)=>is_int($t)&&$t>$now-900));
if(count($entries)>=5){flock($handle,LOCK_UN);fclose($handle);header('Retry-After: 900');respond(429,['ok'=>false,'message'=>'Trop de tentatives. Veuillez réessayer dans 15 minutes. Votre saisie est conservée.']);}
$entries[]=$now;rewind($handle);ftruncate($handle,0);
if (fwrite($handle,json_encode($entries))===false) throw new RuntimeException('write unavailable');
fflush($handle);flock($handle,LOCK_UN);fclose($handle);
// Purge des compteurs périmés, pas de conservation indéfinie des identifiants IP.
foreach (glob($directory.'/*.json') ?: [] as $file) if (is_file($file) && filemtime($file)<$now-86400) @unlink($file);
$subject='Nouvelle demande de devis JMC – '.$values['nom'];
$words=[]; $chunk='';
foreach (preg_split('//u',$subject,-1,PREG_SPLIT_NO_EMPTY) as $char) {
    if (strlen($chunk.$char)>42) { $words[]='=?UTF-8?B?'.base64_encode($chunk).'?='; $chunk=''; }
    $chunk.=$char;
}
if ($chunk!=='') $words[]='=?UTF-8?B?'.base64_encode($chunk).'?=';
$encodedSubject=implode("\r\n ",$words);
$labels=['nom'=>'Nom','profil'=>'Profil du demandeur','email'=>'Adresse e-mail','telephone'=>'Téléphone','activite'=>'Activité concernée','commune'=>'Commune d’intervention','message'=>'Description du besoin'];
$body="Nouvelle demande reçue depuis le formulaire JMC Propreté Nature\n\n";
foreach($labels as $key=>$label) $body.=$label." : ".($values[$key]!==''?$values[$key]:'Non renseigné')."\n\n";
$body.="Politique de confidentialité : prise de connaissance confirmée\nDate de transmission (UTC) : ".gmdate('Y-m-d H:i:s')."\n";
$headers=['From'=>'JMC Proprete Nature <'.$from.'>','Reply-To'=>$values['email'],'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8','Content-Transfer-Encoding'=>'base64'];
// Aucun destinataire ou expéditeur issu du navigateur ; aucun mot de passe nécessaire avec mail() OVH.
$accepted=mail('arnaud.feret@jmcpropretenature.fr',$encodedSubject,chunk_split(base64_encode($body),76,"\r\n"),$headers);
if (!$accepted) respond(502,['ok'=>false,'message'=>'Votre demande n’a pas pu être envoyée. Vos informations sont conservées. Veuillez réessayer plus tard ou nous écrire à arnaud.feret@jmcpropretenature.fr.']);
$_SESSION['forms'][$token]['sent']=true;
respond(200,['ok'=>true]);
