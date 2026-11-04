<?php

/*
 * Web service del RNDC (Manual RNDC Web-Service v2.3): método AtenderMensajeRNDC con un solo
 * parámetro "Request" (XML en ISO-8859-1). La URL y el espacio de nombres se pueden cambiar sin tocar
 * el código si el Ministerio los actualiza. Las credenciales son de cada empresa (Integraciones).
 */
return [
    'url' => env('RNDC_URL', 'http://rndcws.mintransporte.gov.co:8080/ws/svr008w.dll/soap/IBPMServices'),
    'url_pruebas' => env('RNDC_URL_PRUEBAS', env('RNDC_URL', 'http://rndcws.mintransporte.gov.co:8080/ws/svr008w.dll/soap/IBPMServices')),
    'namespace' => env('RNDC_NAMESPACE', 'urn:BPMServicesIntf-IBPMServices'),
    'timeout' => (int) env('RNDC_TIMEOUT', 60),
];
