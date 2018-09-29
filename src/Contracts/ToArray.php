<?php
/**
 * Created by PhpStorm.
 * User: SFGenis
 * Date: 28/09/2018
 * Time: 18:50
 */

namespace MrGenis\Library\Contracts;


interface ToArray
{

    public static function convert($xml, $nodekey = 'localName');

    public function toArray();

}