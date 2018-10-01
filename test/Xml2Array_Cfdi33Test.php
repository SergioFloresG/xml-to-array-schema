<?php
/**
 * Created by PhpStorm.
 * User: Sergio Flores Genis
 * Date: 2018-01-17T16:31
 */

use MrGenis\Library\XmlToArray as ToArray;

class Xml2Array_Cfdi33Test extends PHPUnit_Framework_TestCase
{

    public function test001_timbre_99conceptos()
    {
        $file = test_path('comprobante-001.timbre.xml');
        $content = file_get_contents($file);
        $array = ToArray::convert($content);
        $this->assertNotNull($array, 'La conversion no retorno un arreglo');
        $this->assertCount(90, $array['Conceptos']['Concepto'], 'No se encontraron los 90 conceptos');
    }

    public function test002_complemento_pagos()
    {
        $file = test_path('comprobante-002-ccpagos.timbre.xml');
        $content = file_get_contents($file);
        $array = ToArray::convert($content);
        $this->assertNotNull($array, 'La conversion no retorno un arreglo');
        $this->assertNotEmpty($array['Complemento'][0]['Pagos'], 'No existe el complemento de Pagos');
        $this->assertCount(1, $array['Complemento'][0]['Pagos']['Pago'], "Se espera un elemento de complemento de pago");
    }

    public function test004_complemento_pagos()
    {
        $file = test_path('comprobante-004-ccpagos.timbre.xml');
        $content = file_get_contents($file);
        $array = ToArray::convert($content);
        $this->assertNotNull($array, 'La conversion no retorno un arreglo');
        $this->assertNotEmpty($array['Complemento'][0]['Pagos'], 'No existe el complemento de Pagos');
        $this->assertCount(1, $array['Complemento'][0]['Pagos']['Pago'], "Se espera un elemento de complemento de pago");
    }

    public function test005_complemento_registrofiscal()
    {
        $file = test_path('xml-005-schema-registrofiscal.xml');
        $content = file_get_contents($file);
        $array = ToArray::convert($content);
        $this->assertNotNull($array, 'La conversion no retorno un arreglo');
        $this->assertArrayHasKey('Complemento', $array, 'No se encontro el complemento');
        $this->assertArrayHasKey('CFDIRegistroFiscal', $array['Complemento'][0], 'No se encontro el complemento de registro fiscal');
    }
}