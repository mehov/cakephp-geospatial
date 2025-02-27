<?php
declare(strict_types=1);

namespace Bakeoff\Geospatial\Model\Behavior;

use Cake\Event\EventInterface;

class GeospatialBehavior extends \Cake\ORM\Behavior
{

    /**
     * Goes through all columns on a table and returns only geospatial
     *
     * @param \Cake\ORM\Table $table an instance of model table
     * @return array [column name => column type]
     */
    private function detectGeospatialColumns($table)
    {
        // Shorthand to $table schema
        $schema = $table->getSchema();
        // Shorthand to types. Flipped, so types are now keys: ['point' => 0,]
        $types = array_flip($schema::GEOSPATIAL_TYPES);
        // Results will be here
        $fields = array();
        // Go through all types in $table
        foreach ($schema->typeMap() as $name => $type) {
            // Pick if $type is present in (flipped) GEOSPATIAL_TYPES
            if (isset($types[$type])) {
                $fields[$name] = $type;
            }
        }
        return $fields;
    }

    /**
     * Add formatter aka afterFind parsing geospatial values
     *
     * @param EventInterface $event
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param \ArrayObject $options
     */
    public function beforeFind(EventInterface $event, \Cake\ORM\Query\SelectQuery $query, \ArrayObject $options)
    {
        // Shorthand to table object
        $table = $event->getSubject();
        // Detect geospatial
        $columns = $this->detectGeospatialColumns($table);
        if (empty($columns)) {
            return;
        }
        // Go through each result and unpack() the binary data
        $query->formatResults(function($results) use($columns) {
            return $results->map(function($entity) use($columns) {
                foreach ($columns as $column => $type) {
                    if (!isset($entity->{$column})) {
                        continue;
                    }
                    // [TypeError] unpack(): Argument #2 ($string) must be of type string
                    if (!is_string($entity->{$column})) {
                        continue;
                    }
                    switch ($type) {
                        // TODO support other types, not only POINT
                        case 'point':
                            $entity->{$column} = unpack('x/x/x/x/corder/Ltype/dx/dy', $entity->{$column});
                            break;
                    }
                }
                return $entity;
            });
        });
    }

    /**
     * Once entity is marshalled, prepare geospatial values to be saved into database
     *
     * @param EventInterface $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $data
     * @param \ArrayObject $options
     * @return \Cake\Datasource\EntityInterface|void
     */
    public function afterMarshal(EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $data, \ArrayObject $options)
    {
        // Shorthand to table object
        $table = $event->getSubject();
        // Detect geospatial
        $columns = $this->detectGeospatialColumns($table);
        if (empty($columns)) {
            return;
        }
        foreach ($columns as $column => $type) {
            // Skip if the column is not present in $data
            if (!isset($data[$column])) {
                continue;
            }
            // We expect an array like [12, 34] otherwise skip
            if (!is_array($data[$column])) {
                continue;
            }
            switch ($type) {
                // TODO support other types, not only POINT
                case 'point':
                    $value = sprintf('\'%s(%s)\'', strtoupper($type), implode(' ', $data[$column]));
                    break;
            }
            // Set $value on $entity using ST_GeomFromText()
            $entity->{$column} = $table->query()->func()->ST_GeomFromText([
                $value => 'literal',
            ]);
        }
        return $entity;
    }

}
