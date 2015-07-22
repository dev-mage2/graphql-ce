<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Customer\Model\Indexer;

use Magento\Framework\App\Resource;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Indexer\Model\IndexStructureInterface;
use Magento\Indexer\Model\ScopeResolver\FlatScopeResolver;

class GridStructure implements IndexStructureInterface
{
    /**
     * @var Resource
     */
    private $resource;
    /**
     * @var FlatScopeResolver
     */
    private $flatScopeResolver;

    /**
     * @param Resource|Resource $resource
     * @param \Magento\Indexer\Model\ScopeResolver\FlatScopeResolver $flatScopeResolver
     * @param array $columnTypesMap
     */
    public function __construct(
        Resource $resource,
        FlatScopeResolver $flatScopeResolver,
        array $columnTypesMap = []
    ) {
        $this->resource = $resource;
        $this->flatScopeResolver = $flatScopeResolver;
        $this->columnTypesMap = array_merge($this->columnTypesMap, $columnTypesMap);
    }

    /**
     * @param string $index
     * @param Dimension[] $dimensions
     * @return void
     */
    public function delete($index, array $dimensions = [])
    {
        $adapter = $this->getAdapter();
        $tableName = $this->flatScopeResolver->resolve($index, $dimensions);
        if ($adapter->isTableExists($tableName)) {
            $adapter->dropTable($tableName);
        }
    }

    /**
     * @param string $index
     * @param array $fields
     * @param Dimension[] $dimensions
     * @return void
     */
    public function create($index, array $fields, array $dimensions = [])
    {
        $this->createFlatTable($this->flatScopeResolver->resolve($index, $dimensions), $fields);
    }

    /**
     * @param string $tableName
     * @param array $fields
     * @throws \Zend_Db_Exception
     * @return void
     */
    protected function createFlatTable($tableName, array $fields)
    {
        $adapter = $this->getAdapter();
        $table = $adapter->newTable($tableName);
        $table->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
            10,
            ['unsigned' => true, 'nullable' => false],
            'Entity ID'
        );
        $searchableFields = [];
        foreach ($fields as $field) {
            if ($field['type'] !== 'searchable') {
                $searchableFields[] = $field['name'];
            }
            $columnMap = isset($field['dataType']) && isset($this->columnTypesMap[$field['dataType']])
                ? $this->columnTypesMap[$field['dataType']]
                : ['type' => $field['type'], 'size' => isset($field['size']) ? $field['size'] : null];
            $name = $field['name'];
            $type = $columnMap['type'];
            $size = $columnMap['size'];
            $table->addColumn($name, $type, $size);
        }
        $table->addIndex(
            $this->resource->getIdxName(
                $tableName,
                $searchableFields,
                AdapterInterface::INDEX_TYPE_FULLTEXT
            ),
            $searchableFields,
            AdapterInterface::INDEX_TYPE_FULLTEXT
        );
        $adapter->createTable($table);
    }

    /**
     * @return false|AdapterInterface
     */
    private function getAdapter()
    {
        $adapter = $this->resource->getConnection('write');
        return $adapter;
    }
}
