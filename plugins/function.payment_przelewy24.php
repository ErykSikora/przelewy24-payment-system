<?php

opcache_reset();
session_start();

function smarty_function_payment_przelewy24($params, &$smarty) {

    # FUNKCJE I ZMIENNE - DEKLARACJE

    require __DIR__ . '/../config.php';
    if ($system['show_errors'] == true) ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

    empty($params['title']) ? $title = 'Płatność' : $title = $params['title']; //nazwa odbiorcy
    empty($params['lang']) ? $language = 'pl' : $language = $params['lang']; //jezyk
    empty($params['formID']) ? $formID = 'platnosc' : $formID = $params['formID']; //ID formularza
    empty($params['donations']) ? $donations = '1,2,5,10,20' : $donations = $params['donations']; //kwoty datków
    empty($params['mails']) ? $mails = 'test@test.pl' : $mails = explode(',',$params['mails']); //adresy mailowe
    empty($params['alias']) ? $alias = '' : $alias = $params['alias']; //alias strony
    empty($params['priest']) ? $priest = '' : $priest = $params['priest']; //ksiądz
    empty($params['phone']) ? $phone = '' : $phone = $params['phone']; //ksiądz - telefon
    empty($params['city']) ? $city = '' : $city = $params['city']; //miejscowość
    empty($params['status']) ? $status = 0 : $status = $params['status']; //status

    empty($params['pid']) ? $p24_id = NULL : $p24_id = $params['pid']; //ID klienta # Przelewy24
    empty($params['crc']) ? $CRC = NULL : $CRC = $params['crc']; //klucz CRC # Przelewy24

    if ($alias == 'sandbox') $mode = '?sandbox=1';
    $sendMail = true; //wysyłka maila

    $root = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/'; //link do strony glownej

    if (isset($_SERVER['REQUEST_URI'])) {
        $action = $_SERVER['REQUEST_URI'];
    } else {
        $action = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
        if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != '') {
            $action .= '?' . $_SERVER['QUERY_STRING'];
        }
    }

    function generate_p24_sign($p24_session_id, $p24_merchant_id, $p24_amount, $p24_currency, $CRC){
        // https://docs.przelewy24.pl/Płatności_internetowe#2.7_Obliczanie_p24_sign
        // zwraca połączenie pól, których separatorem jest znak | (pipe/pionowa kreska)
        return md5($p24_session_id.'|'.$p24_merchant_id.'|'.$p24_amount.'|'.$p24_currency.'|'.$CRC);
    }

    function generate_donations($donations, $p){
        isset($p['price']) ? $default = $p['price'] : $default = 2;
        if (!empty($p['other_price'])){
            $default = 99; //nie mogę dać FALSE, bo pierwsza iteracja w foreach wyżej ma index 0 (czyli false) i nie przekazuje się pierwszy parametr
            $other = ' value="'.$p['other_price'].'"';
            $otherClass = ' active-other-price';
        } else {
            $other = '';
        }

        $HTML = '';
        $donations = explode(",",$donations);
        foreach ($donations as $key=>$donate) {
            if ($key==$default && $default!=99){$cl = ' default-value'; $c = ' checked';} else {$cl = ''; $c = '';}
            $HTML .= '<input id="price'.$donate.'" type="radio" name="price"'.$c.' value="'.$donate.'"/>
            <label class="radio-label text-header-2'.$cl.'" for="price'.$donate.'">'.$donate.' zł</label>';
        }
        $HTML .= '<input class="radio-label text '.$otherClass.'" id="customPrice" type="number" name="price_other" placeholder="Inna kwota"'.$other.'>';
        return $HTML;
    }

    function inactive($e = 'disabled'){
        switch($e){
            case 'class':
            case 'disabled':
                return 'disabled';
            case 'alert':
                return '<div class="inactive_alert text-center">KONTO NIEAKTYWNE</div>';
            default:
                return 'disabled';
        }
    }

    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    $p24_session = generateRandomString(14); //generowanie klucza zamówienia # Przelewy24
    
    # TŁUMACZENIA

    require __DIR__ . '/p24/tlumaczenia.php';
    
    # MECHANIKA FORMULARZA

    if (isset($_POST['amount'])){
        //ponizej wykrywanie istnienia zmiennej aby uniknac walidacji przez uzupelnieniem formularza

        $validation = true; //flaga walidacji
        $validationMsg = '';
        $error_message = '';
        
        //zapisywanie danych
        $amount =               $_SESSION['s_amount'] =                 $_POST['price'];
        $amount =               $_SESSION['s_amount'] =                 $_POST['price_other'];
        $name =                 $_SESSION['s_name'] =                   $_POST['name'];
        $email =                $_SESSION['s_email'] =                  $_POST['email'];
        empty($_POST['price_other']) ? $amount = $_SESSION['s_amount'] = $_POST['price'] : $amount = $_SESSION['s_amount'] = $_POST['price_other']; //kwoty datków
        empty($_POST['fullname']) ? $fullname = $_SESSION['s_fullname'] = 'anonimowo' : $fullname = $_SESSION['s_fullname'] = $_POST['fullname']; //dane wpłacającego

        if (isset($_POST['terms'])) $_SESSION['s_terms'] = true; //checkbox
        if (isset($_POST['is_email'])) $_SESSION['s_is_email'] = true; //checkbox - nie posiada e-mail
        
        //walidacja
        if (empty($email) && isset($_POST['is_email'])){
            $email = $mails[1];
        } else {
            $email_safe = filter_var($email,FILTER_SANITIZE_EMAIL); //sanityzacja maila
            if ((filter_var($email_safe, FILTER_VALIDATE_EMAIL)==false)||($email_safe!=$email)){
                //funkcja sanityzuje wprowadzonego maila
                //np łukasz@gmail.com przerobi na ukasz@gmail.com, stąd drugi warunek w ifie
                $validation = false;
                $validationMsg .= '<li>Podaj poprawny adres email</li>';
            }
        }

        if (empty($amount)){
            $validation = false;
            $validationMsg .= '<li>Wybierz kwotę</li>';
        }

        if (!isset($_POST['terms'])){
            $validation = false;
            $validationMsg .= '<li>Musisz zaakceptować Regulamin Serwisu</li>';
        }

        //UDANA WALIDACJA
        if ($validation==true){

            if ($sendMail == true) {

                $charset = "utf-8";
                
                $msg = file_get_contents( __DIR__."/p24/new_payment.html" );
                /* Zastępuje zmienne danymi */
                $msg = str_replace("*title*", $title, $msg);
                $msg = str_replace("*parafia*", $title, $msg);
                $msg = str_replace("*alias*", $alias, $msg);
                $msg = str_replace("*fullname*", $fullname, $msg);
                $msg = str_replace("*amount*", $amount, $msg);
                $msg = str_replace("*email*", $email, $msg);
                $msg = str_replace("*root*", $root, $msg);
                $msg = str_replace("*site*", $_SERVER['HTTP_REFERER'], $msg);

                $serwisMail = "<".$mails[0].">";
                $boundary = md5( uniqid() . microtime() );
                $headers = "MIME-Version: 1.0\r\n"; 
                $headers .= "From: YOURDOMAIN.pl - $title $serwisMail\r\n";
                $headers .= "Return-Path: $serwisMail\r\n";
                $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";

                // wiadomość plain/text (zwiększa punkty wiarygodności maila | mailtester.com)
                $body = "--$boundary\r\n" .
                    "Content-Type: text/plain; charset=UTF-8\r\n" .
                    "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split( base64_encode( 'Deklaracja wpłaty dla Działalności: '.$title.'. '.date("H:i").' - wpłynęła kolejna deklaracja wpłaty na kwotę '.$amount.' zł od '.$fullname.' ('.$email.') - sprawdź szczegóły | YOURDOMAIN.pl '.strip_tags($msg) ) );
                // wiadomość w formacie HTML
                $body .= "--$boundary\r\n" .
                    "Content-Type: text/html; charset=UTF-8\r\n" .
                    "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split( base64_encode( $msg ) );
                $body .= "--$boundary--";
                
                //maile do odbiorców
                foreach ($mails as $key=>$mail) {
                    
                    if ($key==0){
                        //mail do YOURDOMAIN.pl
                        $subject = 'Nowa deklaracja wpłaty - '.$amount.' zł - '.$title;
                    } else {
                        //mail do włąścicieli
                        $subject = 'Nowa deklaracja wpłaty - '.$fullname.' - '.$amount.' zł';
                    }

                    $newsubject='=?UTF-8?B?'.base64_encode($subject).'?=';
                    $subject = $newsubject;
                    
                    if ( !@mail($mail, $subject, $body, $headers) ){
                        //Jeśli nie wysłano wiadomości fallback dla serwerów na home.pl
                        if ( !@mail($mail, $subject, $msg, $headers, "-f ".$mail) ){
                            echo 'nie dziala';
                        }
                    }
                }
                
            }
            session_destroy();
        }
        //NIEUDANA WALIDACJA
        else {
            $error_message = '<ul id="'.$formID.'-request" class="input-errors p24_alert serwer">'.$validationMsg.'</ul>';
            // print_r($_FILES);
        }
    }
  
    //wyswietlanie bledow
    if ($error_message != ''){
        echo $error_message;
        $error_message = '';
    } else {
        echo '<ul id="'.$formID.'-request"></ul>'; //ul, którego zastąpią komuniktaty o blędach - musi mieć to samo ID co odpowiedź z serwera (powtarzalność wykonywania się skryptu)
        echo '<div id="placeholder-sent" data-send="true"></div>'; //zdefiniowanie diva, którego zastąpi komunikat o wyslaniu wiadomości
    }

?>
       
<?php if($validation==false){ ?>

<form action="<?php echo $action.'#'.$formID ?>" id="<?php echo $formID ?>" class="form p24_form <?php if(empty($p24_id) || !$status) echo inactive() ?>" method="post">

    <?php if(empty($p24_id) || !$status) echo inactive('alert') ?>

    <?php /* ?> <h1 class="text-header-2 text-center stage text-switch active" data-stage="1"><span class="text-signature">1.&nbsp;</span>Wybierz kwotę wpłaty</h1>
    <h1 class="text-header-2 text-center stage text-switch" data-stage="2"><span class="text-signature">2.&nbsp;</span>Wypełnij dane kontaktowe</h1>
    <h1 class="text-header-2 text-center stage text-switch" data-stage="3"><span class="text-signature">3.&nbsp;</span>Dokończ wpłatę</h1><?php */ ?>
    <?php /* ?>
    <div class="stage-progress-container" style="display:none;">
        <div class="stage-progress">
            <div class="stage active" data-stage="1">1</div>
            <div class="stage" data-stage="2">2</div>
            
            <div class="stage" data-stage="3">3</div>
            <div class="stage" >4</div>
            
        </div>
    </div>
    <?php */ ?>
    
    <section class="stages">
        <h2 class="text-header-3 text-semibold">Tutaj możesz złożyć ofiarę</h2>
            <label class="input-label">Wybierz kwotę do wpłaty</label>
        <section class="stage stage-1">
            <?php echo generate_donations($donations, $_POST); ?>
            <input type="hidden" name='amount' value="5"/>
            <?php #print_r($mails) ?>
        </section>
        <section class="stage">
            <section class="input-section email-section">
                <label class="input-label">Email</label>
                <input class="input text" type="text" name="email"/>
            </section>
            <section class="input-section">
                <label class="input-label">Imię i nazwisko <small>(opcjonalne)</small></label>
                <input class="input text" type="text" name="fullname"/>
            </section>
            <section class="input-section">
                <input class="input-check" type="checkbox" name='is_email' id="is_email"/>
                <label class="input-check-label text" for="is_email" <?php if (isset($_SESSION['s_is_email'])){echo "checked"; unset($_SESSION['s_is_email']);} ?>>Nie posiadam adresu e-mail</label>
            </section>
            <div class="hr"></div>
        </section>
            <section class="input-section">
                <input class="input-check required" type="checkbox" name='terms' id="terms" required/>
                <label class="input-check-label text" for="terms" <?php if (isset($_SESSION['s_terms'])){echo "checked"; unset($_SESSION['s_terms']);} ?>>Akceptuję <a target="_blank" href="https://YOURDOMAIN.pl/regulamin/Regulamin-YOURDOMAIN.pdf">Regulamin YOURDOMAIN.pl</a></label>
            </section>
            <section class="stage-buttons">
                <?php /* ?><button type="button" class="button text"<?php if(empty($p24_id)) echo ' disabled' ?>>Cofnij</button><?php */ ?>
                <button type="submit" style="position:relative; z-index:1000;" class="button text active"<?php if(empty($p24_id)) echo ' disabled' ?>>Wpłać</button>
            </section>
        <?php /* ?>
        
        <section class="stage stage-1 active" data-stage="1">
            <?php echo generate_donations($donations, $_POST); ?>
            <input type="hidden" name='amount' value="5"/>
            <?php #print_r($mails) ?>
        </section>

        <section class="stage stage-2" data-stage="2">
            <section class="input-section">
                <label class="input-label">Imię i nazwisko</label>
                <input class="input text" type="text" name="fullname"/>
            </section>
            <section class="input-section input_required">
                <label class="input-label">Email</label>
                <input class="input text" type="text" name="email" required/>
            </section>
            <section class="input-section">
                <input class="input-check required" type="checkbox" name='terms' id="terms"/>
                <label class="input-check-label text" for="terms" <?php if (isset($_SESSION['s_terms'])){echo "checked"; unset($_SESSION['s_terms']);} ?>>Akceptuję <a terget="_blank" href="https://YOURDOMAIN.pl/regulamin/Regulamin-YOURDOMAIN.pl-dla-Wspierających.pdf">Regulamin YOURDOMAIN.pl dla Wspierających</a></label>
            </section>
            <?php /* ?>
            <section class="input-section">
                <input id="confirm" class="button active" type="submit" value="Potwierdź">
            </section>
        </section>
        <?php /* ?>
        <section class="stage stage-3" data-stage="3">
            <h2 class="text-center text-header-4">Podsumowanie</h2>
            <input id="confirm" class="button active" type="submit" value="Potwierdź">
            <h2 class="text-center text-header-4">Wybierz sposób w jaki dokonasz wpłaty.</h2>
            <input id="payOnline" type="radio" name='payMethod' checked/>
            <label class="radio-label text" for="payOnline">Płatność Online</label>
            <input id="payBlik" type="radio" name='payMethod'/>
            <label class="radio-label text" for="payBlik">Blik</label>
            <input id="payTradit" type="radio" name='payMethod'/>
            <label class="radio-label text" for="payTradit">Druk przelewu tradycyjnego</label>
            <section class="input-blik">
                <label class="input-label">Podaj kod Blik</label>
                <input class="input text-center" type="text" maxlength="6" name="payMethodBlik"/>
            </section>
            <div class="hr"></div>
            <section class="stage-summary">
                <h2 class="text-header-2">Wybrana kwota: <span id="price-selected">5</span>&nbsp;zł</h2>
                <section class="summary-provider">
                    <p class="text-signature">Płatności online obsługiwane przez</p>
                    <img src="/img/przelewy24.png" alt="Przelewy24"/>
                </section>
            </section>
        </section>

        <?php */ ?>
    </section>

    <script>
        $('input[name=price]').on('input', function() { $('input[name=amount]').val(this.value) });
        
        function ajaxFailed(e) {
            console.warn('Błąd połączenia ajax...');
            odpowiedz = document.getElementById('odpowiedz');
            alert('pokazano Contactform');
            odpowiedz.setAttribute('class', 'alert alert-danger');
            odpowiedz.innerHTML = '<p>adżax zepsuty</p>';
            alert('zmieminono "odpowiedz"');
            document.getElementById('wyslij-wiadomosc').removeEventListener('submit', sendForm);
            alert(e);
        };

        function sendForm(e) {
            
            e.preventDefault(); //zatrzymanie akcji wyslania formularza
            let currentID = this.id;
            /* var dataToSend = serialize(contactForm); */
            var dataToSend = new FormData(contactForm);
            var request = new XMLHttpRequest();
            // var urlrequest = location.href+'#'+currentID+'-request';
            var urlrequest = location.href;
            request.open('POST', urlrequest, true);
            console.log(urlrequest);
            /* request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8'); */
            request.send(dataToSend);

            request.onload = function() {
                //start 
                console.log('status: '+this.status);
                if (this.status >= 200 && this.status < 400) {

                    var responseDOM = new DOMParser(); //pobieranie odpowiedzi serwera
                    var responseHTML = responseDOM.parseFromString(this.responseText, 'text/html'); //przetwarzanie odpowiedzi na dokument HTML
                    var response = responseHTML.getElementById(currentID+'-request').outerHTML; //pobranie odpowiedzi serwera - lista błędów

                    document.getElementById(currentID+'-request').outerHTML = response;

                //   odpowiedz = document.getElementById(currentID+'-request');

                //   window.scroll({top: document.getElementById('support-form').offsetTop+40, left: 0, behavior: 'smooth' }); //animacja przewinięcia do komunikatu

                    // warunek dla poprawnie wypelnionych danych - jeżeli spelniony, to ukrywa formularz i wyswietla komunikat o powodzeniu
                    if ( responseHTML.getElementById('placeholder-sent').getAttribute('data-send') === 'true' ) {
                            
                    sentResponse = responseHTML.getElementById(currentID+'-sent').outerHTML; //pobranie odpowiedzi serwera o powodzeniu wyslania
                    document.getElementById('placeholder-sent').outerHTML = sentResponse; //umieszczenie komunikatu o powodzeniu wyslania
                    document.getElementById(currentID).style.opacity = "0.5"; //ukrycie formularza
                    document.getElementById(currentID).style.pointerEvents = "none"; //ukrycie formularza

                    // window.scroll({top: document.getElementById('support-form').offsetTop+40, left: 0, behavior: 'smooth' }); //animacja przewinięcia do komunikatu
            
                    var closeBtn = document.querySelector('#close');                    
                    closeBtn.addEventListener('click', function(){
                        var msgBox = document.querySelector('#formularz-sent');
                        msgBox.style.display = "none";
                    })

                    setTimeout(function(){
                        document.getElementById('p24link').click();
                    }, 5000);
                    //   gtag('event', 'wysłanie formularza', { 'event_category': 'kontakt' });

                    }

                } else { alert('błąd 332:342');ajaxFailed(); }
            };
            request.onerror = function() { ajaxFailed(); };
        }

    //mechanika - kliknięcie wyslania wiadomości uruchamia polączenie AJAX
    var contactForm = document.querySelector('.p24_form');
    contactForm.addEventListener('submit', sendForm);
    // for (i = 0; i < contactForm.length; i++) { 
    //     contactForm[i].addEventListener('submit', sendForm);
    // }

    </script>

</form>

<?php } else { ?>
    
    <div id="<?php echo $formID; ?>-sent" class="p24_thanks serwer" data-send="true">
        <div class="p24_thanks_container">
            <div class="p24_thanks_logo"><span class="text-bold">your</span>domain.pl</div>
            <h2 class="header-sect text-semibold text-center text-header-3">Dziękujemy za wsparcie!</h2>
            <p class="text-center">Za chwilę nastąpi przekierowanie na stronę z płatnością.</p>
            <span id="close">x</span>
            
            <form action="/plugins/p24_payment.php<?php echo $mode?>" method="POST" class="summary">
                <input type="hidden" name="parish" value="<?php echo $title?>"/>
                <input type="hidden" name="ksiadz" value="<?php echo $priest?>"/>
                <input type="hidden" name="city" value="<?php echo $city?>"/>
                <input type="hidden" name="phone" value="<?php echo $phone?>"/>
                <input type="hidden" name="system" value="<?php echo $mails[1]?>"/>
                <input type="hidden" name="p24_session_id" value="<?php echo $p24_session?>"/> 
                <input type="hidden" name="p24_merchant_id" value="<?php echo $p24_id?>"/> 
                <input type="hidden" name="p24_pos_id" value="<?php echo $p24_id?>"/> 
                <input type="hidden" name="p24_amount" value="<?php echo $amount*100 ?>"/> 
                <input type="hidden" name="p24_description" value="YOURDOMAIN.pl - <?php echo $title?>"/>
                <input type="hidden" name="p24_client" value="<?php echo $fullname ?>"/>
                <input type="hidden" name="p24_url_return" value="https://YOURDOMAIN.pl/<?php echo $alias ?>"/>
                <input type="hidden" name="p24_email" value="<?php echo $email?>"/> 
                <input type="hidden" name="p24_sign" value="<?php echo generate_p24_sign($p24_session, $p24_id, $amount*100, 'PLN', $CRC) ?>"/> 
                <input id="p24link" class="button active" name="submit_send" value="Przejdź" type="submit"/>
            </form>
        </div>
        
    </div>
    

<?php }
}
