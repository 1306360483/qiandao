<?php
/**
 * Project Titor
 * Author: kookxiang <r18@ikk.me>
 */

namespace Core;

use ReflectionObject;
use ReflectionProperty;

abstract class Model
{
    public function save()
    {
        $map = array();
        $reflection = new ReflectionObject($this);
        $reflectionProp = $reflection->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC);
        foreach ($reflectionProp as $property) {
            if (strpos($property->getDocComment(), '@ignore')) {
                continue;
            }
            $propertyName = $property->getName();
            if ($propertyName == 'primaryKey') {
                continue;
            }
            if ($property->isProtected()) {
                $property->setAccessible(true);
            }
            $propertyValue = $property->getValue($this);
            $map[$propertyName] = $propertyValue;
        }
        $primaryKey = $reflection->hasProperty('primaryKey') ? $reflection->getProperty('primaryKey')->getValue($this) : 'id';
        $identifier = $map[$primaryKey];
        unset($map[$primaryKey]);
        $tableName = $this->getTableName($reflection);
        if ($identifier) {
            $sql = "UPDATE `{$tableName}` SET ";
            foreach ($map as $key => $value) {
                $sql .= "{$key} = :{$key},";
            }
            $sql = rtrim($sql, ',');
            $sql .= " WHERE {$primaryKey} = :id";
            $statement = Database::getInstance()->prepare($sql);
            $statement->bindValue(':id', $identifier);
            foreach ($map as $key => $value) {
                $statement->bindValue(":{$key}", $value);
            }
        } else {
            $sql = "INSERT INTO `{$tableName}` SET ";
            foreach ($map as $key => $value) {
                $sql .= "{$key} = :{$key},";
            }
            $sql = rtrim($sql, ',');
            $statement = Database::getInstance()->prepare($sql);
            foreach ($map as $key => $value) {
                $statement->bindValue(":{$key}", $value);
            }
        }
        $statement->execute();
        if (!$identifier) {
            $insertId = Database::getInstance()->lastInsertId();
            if ($insertId) {
                $reflection->getProperty($primaryKey)->setValue($this, $insertId);
            }
        }
    }

    private static function getTableName(ReflectionObject $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (!preg_match('/@table ?([A-Za-z\-_0-9]+)/i', $docComment, $matches) || !$matches[1]) {
            throw new Error('Cannot find table name in doc comment');
        }
        return $matches[1];
    }
}
