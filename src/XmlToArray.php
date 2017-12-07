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
    /** @var \DOMXPath[] */
    protected $domxpath;

    /** @var string */
    protected $nodekey;

    /**
     * XmlToArray constructor.
     *
     * @param \DOMDocument|\SimpleXMLElement|string $xml     string or {@link \DOMDocument} or {@link \SimpleXMLElement}
     * @param string                                $nodekey key for tag map. <i>localName, tagName, nodeName</i>
     */
    public function __construct($xml, $nodekey = 'localName')
    {
        $this->document = new \DOMDocument();
        $this->nodekey = $nodekey;
        $this->domxpath = [];

        if ($xml instanceof \DOMDocument) {
            $this->document = $xml;
        }
        else if ($xml instanceof \SimpleXMLElement) {
            $this->document->loadXML($xml->asXML());
        }
        else {
            $this->document->loadXML($xml);
        }


        // schema location : nodo padre
        $docElement = $this->document->documentElement;
        $docXpath = $this->xpath($docElement);
        $this->domxpath['?'] = $docXpath;

    }

    /**
     * @param \DOMDocument|\SimpleXMLElement|string $xml     string or {@link \DOMDocument} or {@link \SimpleXMLElement}
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

    public function toArray(): array
    {
        $root = $this->document->documentElement;
        $result_root = $this->convertAttributes($root->attributes);
        $result_root['nodeName'] = $root->{"{$this->nodekey}"};

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
                $nodekey = $node->{"{$this->nodekey}"};


                // ya se encuentra definido, por lo que se transforma en un array
                if ($this->needMoreThanOne($node)) {
                    $result_arr[ $nodekey ][] = $this->convertDomElement($node);

                }
                else if (array_key_exists($nodekey, $result_arr)) {
                    $keys = array_keys($result_arr);
                    // is assoc
                    if (array_keys($keys) !== $keys) {
                        $result_arr[ $nodekey ] = [$result_arr[ $nodekey ]];
                    }
                    $result_arr[ $nodekey ][] = $this->convertDomElement($node);
                }
                else {
                    $result_arr[ $nodekey ] = $this->convertDomElement($node);
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

    protected function needMoreThanOne(\DOMElement $node)
    {
        $tagname = $node->localName;
        $result = false;
        if ($domxpath = $this->xpath($node)) {
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
     * @param \DOMElement $element
     *
     * @return \DOMXPath
     */
    private function xpath(\DOMElement $element)
    {
        $nsURI = $element->lookupNamespaceUri($element->prefix);
        try {
            $hash = md5($nsURI);
            if (!array_key_exists($hash, $this->domxpath)) {
                $this->domxpath[ $hash ] = $this->makeXPATH($element);
            }
            return $this->domxpath[ $hash ];
        } catch (\Exception $e) {
            // nada
            $this->domxpath[ $hash ] = null;
        }
        return null;
    }

    /**
     * @param \DOMElement $element
     *
     * @return \DOMXPath
     */
    private function makeXPATH(\DOMElement $element)
    {
        $xsiURI = $element->lookupNamespaceUri('xsi');
        $file = $element->getAttributeNS($xsiURI, 'schemaLocation');

        list($ns, $file) = explode(' ', $file);
        if (empty($file)) $file = $ns;

        $schemaDOM = new \DOMDocument();
        $schemaDOM->load($file);
        return new \DOMXPath($schemaDOM);
    }
}