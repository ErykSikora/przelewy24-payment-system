<?php

    echo '<pre>';
    print_r($_POST);
    echo '</pre>';

    $content = date("Y.m.d").' '.date("H:i").' --- '.file_get_contents("ok.html").'<br><br>';
    echo file_put_contents("ok.html",$content.implode(" ", $_POST));
?>