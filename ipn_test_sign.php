<?php 
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php';
require_once(dirname(__FILE__) . '/freepaymentbox.php');


if (Freepaymentbox::verification_signature())
        echo "Signature ok";
else {
    echo "signature ko";
}


?>