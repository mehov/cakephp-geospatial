## CakePHP 5: Geospatial Behavior for Tables

CakePHP 5.1.0 [introduced](https://book.cakephp.org/5.next/en/appendices/5-1-migration-guide.html#database) limited support for geospatial types.

> The `geometry`, `point`, `linestring`, and `polygon` types are also known as the “geospatial types”. CakePHP offers limited support for geospatial columns. Currently they can be defined in migrations, read in schema reflection, and have values set as text.

Because internally all of the above [are typemapped to `\Cake\Database\Type\StringType`](https://github.com/cakephp/cakephp/blob/a40a07e0705dad895ceb8d8df0e53a94476a1fc4/src/Database/TypeFactory.php#L56), CakePHP:
- *reads* them as string, resulting in binary gibberish;
- *writes* them as literal string (`'POINT(0 0)'`), resulting in database errors ("*SQLSTATE[22003]: Numeric value out of range: 1416 Cannot get geometry object from data you send to the GEOMETRY field*").

This plugin offers a quick Behavior you can attach to your tables having geospatial columns. In the spirit of *convention over configuration*, these geospatial columns will be detected automatically, so you don't have to explicitly configure anything. It will then become much simpler and faster to read from, and write to them.

**Note**: right now this plugin supports the `POINT` type only.

## Setting up
Install:

```
composer require bakeoff/geospatial
```

In your Table classes:

```
$this->addBehavior('Bakeoff/Geospatial.Geospatial');
```

## Reading `POINT`

Get your entities as usual. The geospatial columns will be parsed.

```
$entity = $myTable->get(123);
debug($entity->position);
```

```
[
    'order' => (int) 1,
    'type' => (int) 1,
    'x' => (float) 12.34,
    'y' => (float) 56.78,
]
```

## Writing `POINT`

```
$data = $this->getRequest()->getData();
// Provide the POINT information as array with two elements
$data['position'] = array($position_x, $position_y);
$entity = $myTable->patchEntity($entity, $data);
$result = $myTable->save($entity);
```
