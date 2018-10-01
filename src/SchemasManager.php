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
    /** @var \DOMXPath  */
    protected $domxpath;
    /** @var string */
    private $schemas_directory;
    /** @var array (string => \DOMXpath) */
    protected $schemas;

    public function __construct(\DOMDocument $document)
    {
        $path = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
        if(!file_exists($path)) mkdir($path,0777, true);
        $this->schemas_directory = realpath($path);
        $this->domxpath = new \DOMXPath($document);
        $this->schemas = [$this->hashNs(null) => null];

        $this->addSchemasFromElement($document->documentElement);
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
            $this->addSchemasFromElement($element);
        }
        return $this->schemas[$hash] ?? null;
    }


    /**
     * Registra los nombres de espacios encontrados en el elemento
     *
     * @param \DOMElement $element
     */
    public function addSchemasFromElement(\DOMElement $element)
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
     * Agrega el nombre de espacio el registro obteniendo su definicion del xsd
     *
     * @param string      $xmlns nombre de la direccion del nombre de espacio
     * @param string|null $schema direccion de donde se obtiene la definicion del nombre de espacio
     */
    private
    function addSchema($xmlns, $schema)
    {
        $cache_path = $this->schemas_directory;
        if (!file_exists($cache_path)) mkdir($cache_path, 0777, true);

        $hash = $this->hashNs($xmlns);
        $cache_file = $cache_path . DIRECTORY_SEPARATOR . $hash;
        unset($cache_path);

        if (array_key_exists($hash, $this->schemas)) {
            return;
        }

        if (file_exists($cache_file)) {
            $DomSchema = new \DOMDocument();
            $DomSchema->load($cache_file);
            $XPathSchema = new \DOMXPath($DomSchema);
            $this->schemas[$hash] = $XPathSchema;
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
            }
            catch (\Exception $e) {
                $this->schemas[$hash] = null;
            }
        }
    }

    /**
     * @param \DOMElement $element
     *
     * @return string name space uri
     */
    private
    function getNamespace(\DOMElement $element)
    {
        return $element->lookupNamespaceUri($element->prefix) ?? $element->namespaceURI;
    }

    /**
     * @param string $ns name spaces definition
     *
     * @return string md5
     */
    private
    function hashNs($ns)
    {
        return md5($ns);
    }
}