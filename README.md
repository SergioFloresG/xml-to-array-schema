# xml-to-array-schema
 
Convert xml document to associative array.

The function automatically detects the schematic of the document and uses it to format the elements that may appear 
more than once in an index array.

If it is not possible to detect the scheme, it generates an array of those elements that appear more than once in the 
form of an indexed array.

```bash
composer require mrgenis/xml-to-array-schema
```

## Example


* \DomDocument 
* \SimpleXMLElement
* string

```php
$data = \MrGenis\Library\XmlToArray::convert($xml);
```
