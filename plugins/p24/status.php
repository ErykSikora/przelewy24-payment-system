<?php


/*

    Do tego pliku kierują Przelewy24 i w tablicy $_POST zwracają dane:

    p24_merchant_id     ID Sprzedawcy
    p24_pos_id          Sklepu (domyślnie IDSprzedawcy)
    p24_session_id      Unikalny identyfikator z systemu sprzedawcy
    p24_amount          Kwota transakcji wyrażona w WALUTA/100 (1.23 PLN = 123)
    p24_currency        PLN, EUR, GBP, CZK
    p24_order_id        Numer transakcji nadany przez Przelewy24
    p24_method          Metoda płatności użyta przez klienta
    p24_statement       Tytuł przelewu
    p24_sign            Suma kontrolna wyliczana wg opisu poniżej (Dział Obliczanie p24_sign)

*/

require_once __DIR__ . '/../../config.php'; //import config

$ID = $_POST['p24_merchant_id']; // ID sprzedawcy
// $ID = 108792; // ID sprzedawcy
$ws = ''; // Klucz do WS uzyskany z Przelewy24
$order = $_POST['p24_order_id']; // Numer transakcji nadany przez Przelewy24
$session = $_POST['p24_session_id']; // Session ID
$amount = $_POST['p24_amount']; // Kwota wyrażona w danej walucie, w setnej części (1/100 waluty podstawowej)

if (empty($ID)){
    $content = date("Y.m.d").' '.date("H:i").' [IP: '.$_SERVER["REMOTE_ADDR"].'] # BRAK DANYCH POST'.' --- '.implode(" ", $_POST).'<br><br>'.file_get_contents("status.html");
    file_put_contents("status.html",$content);
    exit('Błąd TC[2104:1]: Nieprawidłowe ID sprzedawcy');
} else {
    $content = date("Y.m.d").' '.date("H:i").' [IP: '.$_SERVER["REMOTE_ADDR"].']'.' --- '.implode(" ", $_POST).'<br><br>'.file_get_contents("status.html");
    // echo $content;
    file_put_contents("status.html",$content);
}

// !empty($ID) ?: exit('Błąd TC[2104:1]: Nieprawidłowe ID sprzedawcy') ;

# połączenie z bazą danych [PDO]

try {
    
    //parametry PDO: mysql:host=;dbname=;charset=;
    $db = new PDO(
        'mysql:host='.$config['db_hostname'].';dbname='.$system['db_name'].';charset=utf8',
        $config['db_username'],
        $config['db_password'],
        [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

} catch (PDOExeption $e){
    exit ('Błąd połączenia z bazą danych');
}

# zapytanie do bazy danych

$parish = $db->query('SELECT ws FROM parishes WHERE payment_id = '.$ID)->fetch();

$ws = $parish['ws']; // Klucz do WS uzyskany z Przelewy24

// echo $ws;

// $soap2 = new SoapClient("https://sandbox.przelewy24.pl/external/107926.wsdl");
// $test = $soap2->TestAccess('107926', '37966c42525799ef4333989c76d4662c');
// $soap2 = new SoapClient("https://secure.przelewy24.pl/external/$ID.wsdl");
// $test = $soap2->TestAccess($ID, $parish['ws']);
// if ($test)
//  echo 'Access granted';
// else
//  echo 'Access denied';

function prepareMail($id, $notice){
    $msg = $notice.' -- weryfikacje wykonano: '.date("Y.m.d").' '.date("H:i");
    // send email
    mail("test@test.pl",date("Y.m.d").' '.date("H:i").' wykonano weryfikację Przelewy24',$msg);
}

$soap = new SoapClient("https://secure.przelewy24.pl/external/$ID.wsdl");
$res = $soap->VerifyTransaction($ID, $ws, $order, $session, $amount);
if ($res->error->errorCode) {
    echo 'Something went wrong: ' . $res->error->errorMessage;
    // prepareMail($ID, 'niepowodzenie: '.$res->error->errorMessage);
} else {
    echo 'Transaction OK';
    // prepareMail($ID, 'sukces!');
}
