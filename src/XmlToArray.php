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
            try {
                $this->document->loadXML($xml);

            } catch (\Exception $e) {

                if (mb_strpos($e->getMessage(), 'Namespace') !== false) {
                    $subxml = preg_replace("(\/?>)", " xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' $0", $xml, 1);
                    $subxml = simplexml_load_string($subxml);
                    $this->document->loadXML($subxml->saveXML());
                }


            }
        }


        // schema location : nodo padre
        $docElement = $this->document->documentElement;
        $docXpath = $this->__xpath($docElement);
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
        if ($domxpath = $this->__xpath($node)) {
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
    private function __xpath(\DOMElement $element)
    {
        $cache_path = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
        if (!file_exists($cache_path)) mkdir($cache_path, 0777, true);

        $nsURI = $element->lookupNamespaceUri($element->prefix);
        $hash = md5($nsURI);
        $cache_file = $cache_path . DIRECTORY_SEPARATOR . $hash;
        unset($cache_path);

        try {

            if (array_key_exists($hash, $this->domxpath)) {
                $XPathSchema = $this->domxpath[ $hash ];
            }
            else if (file_exists($cache_file)) {
                $DomSchema = new \DOMDocument();
                $DomSchema->load($cache_file);
                $XPathSchema = new \DOMXPath($DomSchema);
                $this->domxpath[ $hash ] = $XPathSchema;
            }
            else {
                $xsiURI = $element->lookupNamespaceUri('xsi');
                $nsDefinitions = $element->getAttributeNS($xsiURI, 'schemaLocation');
                if(empty($nsDefinitions)) {
                    $nsDefinitions = $this->document->documentElement->getAttributeNS($xsiURI, 'schemaLocation');
                }
                $nsDefinitions = explode(' ', trim(preg_replace('/\s+/', ' ', $nsDefinitions)));
                $idx = array_search($nsURI, $nsDefinitions);
                $schema = $nsDefinitions[ $idx + 1 ];

                $DomSchema = new \DOMDocument();
                $DomSchema->preserveWhiteSpace = false;
                $DomSchema->formatOutput = false;
                $DomSchema->load($schema);
                $DomSchema->save($cache_file);
                $XPathSchema = new \DOMXPath($DomSchema);
                $this->domxpath[ $hash ] = $XPathSchema;
            }
            return $XPathSchema;
        } catch (\Throwable $th) {
            $this->domxpath[ $hash ] = null;
        }
    }

}