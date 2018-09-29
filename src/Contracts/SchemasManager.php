<?php
/**
 * Created by PhpStorm.
 * User: SFGenis
 * Date: 28/09/2018
 * Time: 18:46
 */

namespace MrGenis\Library\Contracts;


interface SchemasManager
{

    /**
     * @param \DOMElement $element
     *
     * @return \DOMXPath|null para el espacio de nombre del <code>\DOMElement</code>
     */
    public function getXPath(\DOMElement $element);

}