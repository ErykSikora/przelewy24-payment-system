<?php

    $_GET['sandbox']==1 ? $url = 'https://sandbox.przelewy24.pl/trnRegister' : $url = 'https://secure.przelewy24.pl/trnRegister';

    header('Content-Type: text/html; charset=utf-8');
    setlocale(LC_CTYPE, 'pl_PL.UTF-8');

    $title = $_POST['parish'];
    $miejscowosc = $_POST['city'];
    $ksiadz = $_POST['ksiadz'];
    $telefon = $_POST['phone'];
    $mail = $_POST['system'];
    empty($_POST['p24_client']) ? $fullname = 'anonimowo' : $fullname = $_POST['p24_client'];

    # def
    $url_status = 'https://YOURDOMAIN/plugins/p24/status.php';

    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    $data = array(
        'p24_session_id' => $_POST['p24_session_id'],
        'p24_merchant_id' => $_POST['p24_merchant_id'],
        'p24_pos_id' => $_POST['p24_pos_id'],
        'p24_amount' => $_POST['p24_amount'],
        'p24_currency' => 'PLN',
        'p24_description' => $_POST['p24_description'],
        'p24_client' => $_POST['p24_client'],
        'p24_country' => 'PL',
        'p24_email' => $_POST['p24_email'],
        'p24_encoding' => 'UTF-8',
        'p24_language' => 'pl',
        'p24_url_return' => $_POST['p24_url_return'],
        'p24_url_status' => $url_status,
        'p24_api_version' => "3.2",
        'p24_wait_for_result' => 1,
        'p24_sign' => $_POST['p24_sign']
    );

    $ch = curl_init($url); //inicjacja połączenia curl
    curl_setopt($ch, CURLOPT_POST, 1); //zadeklarowanie, że chcemy wysłać dane POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data); //załączenie naszych danych do POST fields
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:multipart/form-data; charset=utf-8')); //ustawienie content-type
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //zwrócenie zapytania - możliwość przypisania go do zmiennej $response
    $result = curl_exec($ch); //wykonanie żądania

    if (strpos($result, 'token') !== false) {

        $result = explode("token=",$result); //rozbijam odpowiedź, pobieram token

        $to = $_POST['p24_email']; //mail wpłacającego

        $charset = "utf-8";
        $subject = '[XYZ.PL] Twój link do płatności - '.$title;
        $newsubject='=?UTF-8?B?'.base64_encode($subject).'?=';
        $subject = $newsubject;

        $mailFrom = "<".$mail.">";
        $boundary = md5(uniqid().microtime());
        $headers = "MIME-Version: 1.0\r\n"; 
        $headers .= "From: xyz.pl - $title $mailFrom\r\n";
        $headers .= "Return-Path: $mail\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";

        $msgSender = file_get_contents( __DIR__."/p24/confirm_payment.html" );
        $msgSender = str_replace("*miejscowosc*", $miejscowosc, $msgSender);
        $msgSender = str_replace("*data*", date("d.m.Y").'r.', $msgSender);
        $msgSender = str_replace("*root*", $_POST['p24_url_return'], $msgSender);
        $msgSender = str_replace("*parafia*", $title, $msgSender);
        $msgSender = str_replace("*oplac*", "https://go.przelewy24.pl/trnRequest/$result[1]", $msgSender);
        $msgSender = str_replace("*amount*", $_POST['p24_amount']/100, $msgSender);
        $msgSender = str_replace("*fullname*", $fullname, $msgSender);
        $msgSender = str_replace("*email*", $_POST['p24_email'], $msgSender);
        $msgSender = str_replace("*ksiadz*", $ksiadz, $msgSender);
        $msgSender = str_replace("*telefon*", $telefon, $msgSender);

        // Plain text version of message
        $body = "--$boundary\r\n" .
                "Content-Type: text/plain; charset=UTF-8\r\n" .
                "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode(strip_tags('Tutaj początek wiadomości'.$msgSender)));
        // HTML version of message
        $body .= "--$boundary\r\n" .
                "Content-Type: text/html; charset=UTF-8\r\n" .
                "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($msgSender));
        $body .= "--$boundary--";

        if ( !@mail($to, $subject, $body, $headers) ){
            @mail($to, $subject, $body, $headers, "-f ".$email);
        }
        
        if ($_GET['sandbox']==1){
            header("Location: https://sandbox.przelewy24.pl/trnRequest/$result[1]");
        } else {
            header("Location: https://secure.przelewy24.pl/trnRequest/$result[1]");
        }
        

    } else if (strpos($result, 'errorMessage') !== false){

        $result = explode("errorMessage=",$result);
        echo 'XYZ.PL - BŁĄD PRZELEWY24: '.$result[1];

    }
    curl_close($ch); //zamknięcie połączenia
    exit();
