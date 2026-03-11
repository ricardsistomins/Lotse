<?php

namespace app\Storage;

use PDO;
use Phalcon\Di\Di;
use Phalcon\Db\Adapter\Pdo\Mysql;

abstract class AbstractStorage 
{
    protected string $table = '';
    protected array $fieldMap = [];
    
    /**
     * Get database adapter instance
     */
    protected function getDb(): Mysql
    {
        return Di::getDefault()->get('db');
    }
    
    /**
    * Get raw PDO instance
    */
    protected function getPdo(): PDO
    {
        return $this->getDb()->getInternalHandler();
    }

    /**
    * Convert fieldMap to SQL SELECT column list
    */
    public function mapFields(): string
    {
        $fields = [];

        foreach ($this->fieldMap as $column => $alias) {
            $fields[] = '`' . $this->table . '`.`' . $column . '` AS `' . $alias . '`';
        }

        return implode(', ', $fields);
    }
}
