<?php

namespace RM_PagSeguro\Object;
require __DIR__ . '/../../vendor/autoload.php';

$amount = new Amount();
$amount->setValue(1000);
$amount->setCurrency('BRL');

$address = new Address();
$address->setPostalCode('01452002');
$address->setStreet('Rua Funchal');
$address->setNumber('129');
$address->setComplement('4º andar');
$address->setLocality('São Paulo');
$address->setCity('São Paulo');
$address->setRegion('SP');
$address->setRegionCode('SP');
$address->setCountry('BRA');

echo json_encode([
    'amount' => $amount,
    'address' => $address,
], JSON_PRETTY_PRINT);
