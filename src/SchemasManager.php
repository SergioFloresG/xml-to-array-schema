<?php
/**
 * Created by PhpStorm.
 * User: SFGenis
 * Date: 26/09/2018
 * Time: 17:35
 */

namespace MrGenis\Library;

use MrGenis\Library\Contracts\SchemasManager as IntSchemasManager;

class SchemasManager implements IntSchemasManager
{
    /** @var \DOMXPath */
    protected $domxpath;
    /** @var string */
    private $schemas_directory;
    /** @var array (string => \DOMXpath) */
    protected $schemas;
    /** @var array (preefix => namespace) */
    protected $prefix;

    public function __construct(\DOMDocument $document)
    {
        $path = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
        if (!file_exists($path)) mkdir($path, 0777, true);
        $this->schemas_directory = realpath($path);
        $this->domxpath = new \DOMXPath($document);
        $this->schemas = [$this->hashNs(null) => null];
        $this->prefix = [];

        $this->addElementSchemas($document->documentElement);
    }

    /**
     * @param \DOMElement $element
     *
     * @return \DOMXPath|mixed|null
     */
    public function getXPath(\DOMElement $element)
    {
        $namespace = $this->getNamespace($element);
        $hash = $this->hashNs($namespace);

        if (!array_key_exists($hash, $this->schemas)) {
            $this->addElementSchemas($element);
        }
        return $this->schemas[$hash] ?? null;
    }


    /**
     * Registra los nombres de espacios encontrados en el elemento
     *
     * @param \DOMElement $element
     */
    public function addElementSchemas(\DOMElement $element)
    {
        $schemas = $this->getElementSchemas($element);
        foreach ($schemas as $ns => $uri) {
            $this->addSchema($ns, $uri);
        }
    }


    /**
     * Obtiene los nombre de espacion con la direccion URI de su xsd.
     *
     * @param \DOMElement $element
     *
     * @return array (namespace => uri)
     */
    private function getElementSchemas(\DOMElement $element)
    {
        $namespace = $this->getNamespace($element);
        $schemas = $element->getAttributeNodeNS($namespace, 'schemaLocation');
        if (empty($schemas)) {

            $nodestr = $this->domxpath->document->saveXML($element);
            $exp = "/<[^>]*schemaLocation=(\"|')([^\"']+)(\"|')[^>]*>/i";
            preg_match_all($exp, $nodestr, $matches, PREG_SET_ORDER);
            $schemas = '';
            foreach ($matches as $match) {
                $line = trim(preg_replace('/\s+/', ' ', $match[2]));
                $schemas .= sprintf(' %s', $line);
            }
        }

        $schemas = explode(' ', trim(preg_replace('/\s+/', ' ', $schemas)));

        $schemas_map = [];
        $schemas_count = count($schemas);
        if ($schemas_count > 1 && $schemas_count % 2 === 0) {
            for ($i = 0;
                 $i < count($schemas);
                 $i += 2) {
                $schemas_map[$schemas[$i]] = $schemas[$i + 1];
            }
        }

        return $schemas_map;
    }

    /**
     * Register a schema indicating its namespace and the location of its xsd
     *
     * @param string $xmlns namespace
     * @param string $schema uri to XML Schema Definition
     * @param string $prefix xml prefix node
     *
     * @return bool
     */
    public function addSchema($xmlns, $schema, $prefix = null)
    {
        $cache_path = $this->schemas_directory;
        if (!file_exists($cache_path)) mkdir($cache_path, 0777, true);

        $hash = $this->hashNs($xmlns);
        $cache_file = $cache_path . DIRECTORY_SEPARATOR . $hash;
        unset($cache_path);

        if (array_key_exists($hash, $this->schemas) && null !== $this->schemas[$hash]) {
            return true;
        }

        $result = false;
        if (file_exists($cache_file)) {
            $DomSchema = new \DOMDocument();
            $DomSchema->load($cache_file);
            $XPathSchema = new \DOMXPath($DomSchema);
            $this->schemas[$hash] = $XPathSchema;
            $result = false;
        }
        else {
            try {
                $DomSchema = new \DOMDocument();
                $DomSchema->preserveWhiteSpace = false;
                $DomSchema->formatOutput = false;
                $DomSchema->load($schema);
                $DomSchema->save($cache_file);
                $XPathSchema = new \DOMXPath($DomSchema);
                $this->schemas[$hash] = $XPathSchema;
                $result = true;
            }
            catch (\Exception $e) {
                $this->schemas[$hash] = null;
            }
        }

        if($prefix) {
            $this->prefix[$prefix] = $xmlns;
        }

        return $result;
    }

    /**
     * @param \DOMElement $element
     *
     * @return string name space uri
     */
    private function getNamespace(\DOMElement $element)
    {
        $prefix = $this->elementPrefix($element);
        $namespace = $element->lookupNamespaceUri($prefix) ?? $element->namespaceURI;
        if(!$namespace && array_key_exists($prefix, $this->prefix)) {
            $namespace = $this->prefix[$prefix];
        }

        return $namespace;
    }

    /**
     * @param string $ns name spaces definition
     *
     * @return string md5
     */
    private function hashNs($ns)
    {
        return md5($ns);
    }

    /**
     * @param \DOMElement $element
     *
     * @return string
     */
    public function elementName(\DOMElement $element){
        $name = $element->localName;
        if (preg_match('/:(.+)$/i', $name, $matches) === 1) {
            $name = $matches[1];
        }
        return $name;
    }

    /**
     * @param \DOMElement $element
     *
     * @return string
     */
    public function elementPrefix(\DOMElement $element){
        $prefix = $element->prefix ?? '';
        if(empty($prefix)) {
            $nodekey = $element->localName;
            if (preg_match('/^(.+):.*$/i', $nodekey, $matches) === 1) {
                $prefix = $matches[1];
            }
        }
        return $prefix;
    }
}