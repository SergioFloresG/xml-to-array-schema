<?php
/**
 * Created by PhpStorm.
 * User: Sergio Flores Genis
 * Date: 2017-11-14T13:44
 */

namespace MrGenis\Library;

/**
 * Class XmlToArray
 *
 * @package MrGenis\Library
 */
class XmlToArray
{

    /** @var \DOMDocument */
    protected $document;
    /** @var \DOMXPath */
    protected $domxpath;

    /**
     * XmlToArray constructor.
     *
     * @param string $xml cuerpo del xml
     * @param string $schema_file
     */
    private function __construct($xml, $schema_file = null)
    {
        $this->document = new \DOMDocument();
        $this->document->loadXML($xml);

        if ($schema_file) {
            $simplexml = new \DOMDocument();
            $simplexml->load($schema_file);
            $this->domxpath = new \DOMXPath($simplexml);
        }

    }

    /**
     * @param string $xml xml body
     * @param string $schema
     *
     * @return array
     */
    public static function convert($xml, $schema = null)
    {
        /** @var XmlToArray $converter */
        $converter = new static($xml, $schema);
        return $converter->toArray();
    }

    public function toArray(): array
    {
        $root = $this->document->documentElement;
        $result_root = $this->convertAttributes($root->attributes);
        $result_root['nodeName'] = $root->localName;

        $xpath_root = new \DOMXPath($this->document);
        foreach ($xpath_root->query('namespace::*', $root) as $item) {
            /** @var \DOMNode $item */
            $result_root['namespaceURI'][ $item->nodeName ] = $item->nodeValue;
        }

        $result = [];
        if ($root->hasChildNodes()) {
            $result = $this->convertDomElement($root);
            unset($result['_attributes']);
        }


        return array_merge($result, ['_root' => $result_root]);
    }

    protected function convertAttributes(\DOMNamedNodeMap $nodeMap)
    {
        if ($nodeMap->length === 0) {
            return [];
        }

        $result = [];

        /** @var \DOMAttr $item */
        foreach ($nodeMap as $item) {
            $result[ $item->nodeName ] = $item->value;
        }

        return ['_attributes' => $result];
    }

    protected function convertDomElement(\DOMElement $element)
    {
        $result_arr = $this->convertAttributes($element->attributes);
        $result_str = '';

        foreach ($element->childNodes as $node) {
            if ($node instanceof \DOMElement) {


                // ya se encuentra definido, por lo que se transforma en un array
                if ($this->domxpath && $this->needMoreThanOne($node->localName)) {
                    $result_arr[ $node->localName ][] = $this->convertDomElement($node);

                }
                else if (array_key_exists($node->localName, $result_arr)) {
                    $keys = array_keys($result_arr);
                    // is assoc
                    if (array_keys($keys) !== $keys) {
                        $result_arr[ $node->localName ] = [$result_arr[ $node->localName ]];
                    }
                    $result_arr[ $node->localName ][] = $this->convertDomElement($node);
                }
                else {
                    $result_arr[ $node->localName ] = $this->convertDomElement($node);
                }
                continue;

            }
            else if ($node instanceof \DOMCdataSection) {
                $result_arr['_cdata'] = $node->data;
                continue;

            }
            else if ($node instanceof \DOMText) {
                $result_str .= $node->textContent;
                continue;

            }
            else if ($node instanceof \DOMComment) {
                // Comments are ignored
                continue;
            }
        }

        if (empty($result_arr)) return $result_str;
        else return $result_arr;
    }

    protected function needMoreThanOne($tagname)
    {

        /** @var \DOMNodeList $elements */
        $elements = $this->domxpath->evaluate("//xs:element[@name=\"{$tagname}\"]");
        if ($elements->length) {
            $element = $elements->item(0);
            $min = $element->getAttribute('minOccurs');
            $max = $element->getAttribute('maxOccurs');

            return ($min > 1 || $max > 1 || $max == 'unbounded');
        }

        $elements = $this->domxpath->evaluate("//xs:complexType[@name=\"{$tagname}\"]");
        if ($elements->length) {
            $element = $elements->item(0);
            $min = $element->getAttribute('minOccurs');
            $max = $element->getAttribute('maxOccurs');

            return ($min > 1 || $max > 1 || $max == 'unbounded');
        }

    }
}