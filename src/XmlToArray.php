<?php
/**
 * Created by PhpStorm.
 * User: SFGenis
 * Date: 28/09/2018
 * Time: 17:33
 */

namespace MrGenis\Library;


use MrGenis\Library\Contracts\ToArray;

class XmlToArray implements ToArray
{

    /** @var \DOMDocument */
    protected $document;
    /** @var SchemasManager */
    protected $schemas;
    /** @var string */
    protected $nodekey;

    public function __construct($xml, $nodekey = 'localName')
    {
        $this->document = new \DOMDocument();
        $this->nodekey = $nodekey;

        if ($xml instanceof \DOMDocument) {
            $this->document = $xml;
        }
        else if ($xml instanceof \SimpleXMLElement) {
            $this->document->loadXML($xml->asXML());
        }
        else {
            try {
                $this->document->loadXML($xml);

            }
            catch (\Exception $e) {

                if (mb_strpos($e->getMessage(), 'Namespace') !== false) {
                    $subxml = preg_replace("(\/?>)", " xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' $0", $xml, 1);
                    $subxml = simplexml_load_string($subxml);
                    $this->document->loadXML($subxml->saveXML());
                }


            }
        }

        $this->schemas = new SchemasManager($this->document);
    }

    /**
     * @param \DOMDocument|\SimpleXMLElement|string $xml string or {@link \DOMDocument} or {@link \SimpleXMLElement}
     * @param string                                $nodekey key for tag map. <i>localName, tagName, nodeName</i>
     *
     * @return array
     */
    public static function convert($xml, $nodekey = 'localName')
    {
        /** @var XmlToArray $converter */
        $converter = new static($xml, $nodekey);
        return $converter->toArray();
    }

    public function toArray()
    {
        $root = $this->document->documentElement;
        $result_root = $this->convertAttributes($root->attributes);
        $result_root['nodeName'] = $root->{"{$this->nodekey}"};

        $xpath_root = new \DOMXPath($this->document);
        foreach ($xpath_root->query('namespace::*', $root) as $item) {
            /** @var \DOMNode $item */
            $result_root['namespaceURI'][$item->nodeName] = $item->nodeValue;
        }

        $result = [];
        if ($root->hasChildNodes()) {
            $result = $this->convertDomElement($root);
            unset($result['_attributes']);
        }


        return array_merge($result, ['_root' => $result_root]);
    }

    public function flatAttributes()
    {
        $array = $this->toArray();
        $func = function ($item) use (&$func) {
            if (is_array($item)) {
                if (isset($item['_attributes'])) {
                    $attrs = $item['_attributes'];
                    unset($item['_attributes']);
                    $item = array_merge($item, $attrs);
                }
                $result = array_map($func, $item);
            }
            else {
                $result = $item;
            }

            return $result;
        };

        $root_attributes = [];
        if(isset($array['_root']['_attributes'])){
            $root_attributes = $array['_root']['_attributes'];
            unset($array['_root']['_attributes']);
        }

        $result = array_map($func, $array);
        return array_merge($result, $root_attributes);
    }

    /**
     * Register a schema indicating its namespace and the location of its xsd
     *
     * @param string $namespace
     * @param string $xsdUri uri to XML Schema Definition
     *
     * @return bool
     */
    public function addSchema($namespace, $xsdUri, $prefix = null)
    {
        return $this->schemas->addSchema($namespace, $xsdUri, $prefix);
    }

    protected function convertDomElement(\DOMElement $element)
    {
        $result_arr = $this->convertAttributes($element->attributes);
        $result_str = '';


        foreach ($element->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                $nodekey = $this->schemas->elementName($node);


                // ya se encuentra definido, por lo que se transforma en un array
                if ($this->needMoreThanOne($node)) {
                    $result_arr[$nodekey][] = $this->convertDomElement($node);

                }
                else if (array_key_exists($nodekey, $result_arr)) {
                    if ($this->isAssoc($result_arr[$nodekey])) {
                        $result_arr[$nodekey] = [$result_arr[$nodekey]];
                    }

                    $result_arr[$nodekey][] = $this->convertDomElement($node);
                }
                else {
                    $result_arr[$nodekey] = $this->convertDomElement($node);
                }
                unset($nodekey);
            }
            else if ($node instanceof \DOMCdataSection) {
                $result_arr['_cdata'] = $node->data;
            }
            else if ($node instanceof \DOMText) {
                $result_str .= $node->textContent;
            }
            else if ($node instanceof \DOMComment) {
                // Comments are ignored
            }

        }

        $result_str = trim($result_str);

        if (empty($result_arr)) return $result_str;
        else {
            if (!empty($result_str)) {
                $result_arr['_text'] = $result_str;
            }

            return $result_arr;
        }
    }

    /**
     * @param \DOMNamedNodeMap $nodeMap
     *
     * @return array <code>[ _attributes => [ $key => $value ] ]</code>.
     */
    protected function convertAttributes(\DOMNamedNodeMap $nodeMap)
    {
        if ($nodeMap->length === 0) {
            return [];
        }

        $result = [];

        /** @var \DOMAttr $item */
        foreach ($nodeMap as $item) {
            $result[$item->nodeName] = $item->value;
        }

        return ['_attributes' => $result];
    }


    /**
     * @param \DOMElement $node
     *
     * @return bool
     */
    protected function needMoreThanOne(\DOMElement $node)
    {
        $tagname = $this->schemas->elementName($node);
        $result = false;
        if ($domxpath = $this->schemas->getXPath($node)) {
            $exp = "//xs:element[@name=\"{$tagname}\"] | //xs:complexType[@name=\"{$tagname}\"]";
            /** @var \DOMNodeList $elements */
            $elements = $domxpath->evaluate($exp);
            if ($elements->length) {
                $element = $elements->item(0);
                $min = $element->getAttribute('minOccurs');
                $max = $element->getAttribute('maxOccurs');

                $result = ($min > 1 || $max > 1 || $max == 'unbounded');
            }
        }
        return $result;
    }

    /**
     * @param mixed $arr
     *
     * @return bool <strong>TRUE</strong> cuando el arreglo es asociativo (clave => valor)
     */
    protected function isAssoc($arr)
    {
        if(!is_array($arr)) return true;
        else if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}