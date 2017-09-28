<?php
/**
 * Created by PhpStorm.
 * User: Sebastian König
 * Date: 28.09.2017
 * Time: 10:52
 */


// only cli mode allowed
if(php_sapi_name() !== 'cli') {
    die();
}

/**
 * @param DOMNodeList $nodeList
 * @param int $index
 * @return string
 */
function getNodeValue(DOMNodeList $nodeList, $index = 0) {
    if($nodeList->length >= $index + 1) {
        return $nodeList->item($index)->nodeValue;
    }
    return '';
}

function serializeNode(DOMElement $node) {
    if($node->childNodes->length > 0) {
        $nodeArray = [];
        /** @var DOMElement $childNode */
        foreach ($node->childNodes as $childNode) {
            $nodeArray[$childNode->nodeName] = serializeNode($childNode);
        }
        return $nodeArray;
    }
    return $node->nodeValue;
}

$options = getopt('',[
    'dbhost:',
    'dbname:',
    'dbuser:',
    'dbpassword:',
    'migrationpath:',
    'migrationnamespace:'
]);

// check required arguments
if(
    !array_key_exists('dbhost', $options) ||
    !array_key_exists('dbname', $options) ||
    !array_key_exists('dbuser', $options) ||
    !array_key_exists('dbpassword', $options)
){
    throw new Exception('required options are --dbhost, --dbname, --dbuser, --dbpassword', -1);
}

// create pdo
$pdo = new PDO(
    'mysql:host=' . $options['dbhost'] . ';dbname=' . $options['dbname'],
    $options['dbuser'],
    $options['dbpassword']
);

// read config.xml
$pluginXml = new DOMDocument();
$pluginXml->load(__DIR__ . '/plugin.xml');

// insert ignore into s_core_plugins
$insertPluginStmnt = $pdo->prepare(
    "INSERT IGNORE INTO s_core_plugins 
          (namespace, name, label, description, source, translations, active, added, author, copyright, license, version, support, changes, link, capability_update, capability_install, capability_enable)
        VALUES ( 
            'ShopwarePlugins', 
            'VioDbMigration', 
            :label, 
            :description,
            '', 
            '{\"de\":{\"label\":\"VIO.DbMigration\"},\"en\":{\"label\":\"VIO.DbMigration\"}}', 
            0, 
            NOW(), 
            :author, 
            :copyright, 
            :license, 
            :version, 
            :link, 
            null,
            null,
            1,
            1,
            1);");
$insertPluginStmnt->execute([
    'label' => getNodeValue($pluginXml->getElementsByTagName('label')),
    'description' => getNodeValue($pluginXml->getElementsByTagName('description')),
    'author' => getNodeValue($pluginXml->getElementsByTagName('author')),
    'copyright' => getNodeValue($pluginXml->getElementsByTagName('copyright')),
    'license' => getNodeValue($pluginXml->getElementsByTagName('license')),
    'version' => getNodeValue($pluginXml->getElementsByTagName('version')),
    'link' => getNodeValue($pluginXml->getElementsByTagName('link')),
]);

// get current plugin state
$pluginStatusStmnt = $pdo->prepare('SELECT installation_date IS NOT NULL as installed, active, id FROM s_core_plugins WHERE name = \'VioDbMigration\';');
$pluginStatusStmnt->execute();
/** @var stdClass $pluginStatus */
$pluginStatus = $pluginStatusStmnt->fetch(PDO::FETCH_OBJ);

if(!$pluginStatus->installed) {
    // set installation_date to now
    $pdo->prepare('UPDATE s_core_plugins SET installation_date = NOW() WHERE name = \'VioDbMigration\' AND installation_date IS NULL;')->execute();
}

if(!$pluginStatus->active) {
    // set installation_date to now
    $pdo->prepare('UPDATE s_core_plugins SET active = 1 WHERE name = \'VioDbMigration\' AND installation_date IS NOT NULL;')->execute();
}

// check config elements
// read config.xml
$configXml = new DOMDocument();
$configXml->load(__DIR__ . '/Resources/config.xml');
$elements = $configXml->getElementsByTagName('element');
if($elements->length > 0) {
    // check form
    $formIdStmnt = $pdo->prepare('SELECT id FROM s_core_config_forms WHERE  plugin_id = :pluginId');
    $formIdStmnt->execute(['pluginId' => $pluginStatus->id]);
    $formId = $formIdStmnt->fetchColumn();
    if(!$formId) {
        // create form
        $formInsertStmnt = $pdo->prepare('
            INSERT INTO s_core_config_forms (name, label, description, position, plugin_id)  
            VALUES (\'VioDbMigration\', :label, :description, 0, :pluginId );
        ');
        $formInsertStmnt->execute([
            'label' => getNodeValue($pluginXml->getElementsByTagName('label')),
            'description' => getNodeValue($pluginXml->getElementsByTagName('description')),
            'pluginId' => $pluginStatus->id
        ]);
        $formId = $pdo->lastInsertId();
    }

    $elemntIDs = [];
    $position = 0;

    /** @var DOMElement $element */
    foreach ($elements as $element) {
        $name = getNodeValue($element->getElementsByTagName('name'));
        $searchElementStmnt = $pdo->prepare('SELECT id FROM s_core_config_elements WHERE form_id = :formId AND name = :name;');
        $searchElementStmnt->execute(['formId' => $formId, 'name' => $name]);

        if($searchElementStmnt->rowCount() == 0) {
            // create form element
            $insertElementStmnt = $pdo->prepare('
                INSERT INTO s_core_config_elements (form_id, name, value, label, description, type, required, position, scope, options)
                  VALUES
                    (:formId, :name, :value, :label, :description, :type, :required, :position, :scope, :options);'
            );
            $type = 'text';
            if($element->getAttribute('type')) {
                $type = $element->getAttribute('type');
            }
            $required = true;
            if($element->getAttribute('required')) {
                $required = $element->getAttribute('required') === 'true';
            }
            $scope = 'locale';
            if($element->getAttribute('scope')) {
                $scope = $element->getAttribute('scope');
            }
            $options = null;
            $optionsElement = $element->getElementsByTagName('options')->item(0);
            if($optionsElement) {
                $options = serializeNode($optionsElement);
                $options = serialize($options);
            }
            $insertElementStmnt->execute([
                'formId' => $formId,
                'name' => $name,
                'value' => serialize(getNodeValue($element->getElementsByTagName('value'))),
                'label' => getNodeValue($element->getElementsByTagName('label')),
                'description' => getNodeValue($element->getElementsByTagName('description')),
                'type' => $type,
                'required' => $required,
                'position' => $position,
                'scope' => $scope,
                'options' => $options
            ]);
            $elemntIDs[$name] = $pdo->lastInsertId();
        }
        else{
            $elemntIDs[$name] = $searchElementStmnt->fetchColumn();
        }
        $position++;
    }

    // set configured values

    if(array_key_exists('migrationpath', $options)) {
        $migrationpathStmnt = $pdo->prepare('
              INSERT INTO s_core_config_values (element_id, shop_id, value) 
              VALUES (:elementId, (SELECT id FROM s_core_shops WHERE `default` = 1 LIMIT 1), :value)
              ON DUPLICATE KEY UPDATE s_core_config_values.value = :value');
        $migrationpathStmnt->execute([
            'elementId' => $elemntIDs['VioMigrationPath'],
            'value' => serialize($options['migrationpath'])
        ]);
    };

    if(array_key_exists('migrationnamespace', $options)) {
        $migrationnamespaceStmnt = $pdo->prepare('
              INSERT INTO s_core_config_values (element_id, shop_id, value) 
              VALUES (:elementId, (SELECT id FROM s_core_shops WHERE `default` = 1 LIMIT 1), :value)
              ON DUPLICATE KEY UPDATE s_core_config_values.value = :value');
        $migrationnamespaceStmnt->execute([
            'elementId' => $elemntIDs['VioMigrationNamespace'],
            'value' => serialize($options['migrationnamespace'])
        ]);
    };
}



