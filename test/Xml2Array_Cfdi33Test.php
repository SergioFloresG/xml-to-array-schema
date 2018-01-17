<?php
/**
 * Created by PhpStorm.
 * User: Sergio Flores Genis
 * Date: 2018-01-17T16:31
 */

class Xml2Array_Cfdi33Test extends PHPUnit_Framework_TestCase
{

    public function test001()
    {
        $file = test_path('comprobante-001.timbre.xml');
        $content = file_get_contents($file);
        $array = \MrGenis\Library\XmlToArray::convert($content);
        $this->assertNotNull($array, 'La conversion no retorno un arreglo');
        $this->assertCount(90, $array['Conceptos']['Concepto'], 'No se encontraron los 90 conceptos');
    }

    public function test002()
    {
        $file = test_path('comprobante-002-ccpagos.timbre.xml');
        $content = file_get_contents($file);
        $array = \MrGenis\Library\XmlToArray::convert($content);
        $this->assertNotNull($array, 'La conversion no retorno un arreglo');
        //$this->assertCount(90, count($array['Conceptos']['Concepto']), 'No se encontraron los 90 conceptos');
    }

    public function test004()
    {
        $file = test_path('comprobante-004-ccpagos.timbre.xml');
        $content = file_get_contents($file);
        $array = \MrGenis\Library\XmlToArray::convert($content);
        $this->assertNotNull($array, 'La conversion no retorno un arreglo');
        //$this->assertCount(90, count($array['Conceptos']['Concepto']), 'No se encontraron los 90 conceptos');
    }
}