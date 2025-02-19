<?php

namespace LucasGiovanny\LaravelPrestashop\Query;

use Exception;
use LucasGiovanny\LaravelPrestashop\Exceptions\CouldNotConnectException;
use LucasGiovanny\LaravelPrestashop\Exceptions\CouldNotFindFilter;
use LucasGiovanny\LaravelPrestashop\Prestashop;

trait Findable
{
    abstract public function connection(): Prestashop;

    abstract protected function isFillable($key);

    abstract public function url(): string;

    /**
     * Add sort fields by ASC
     *
     * @return $this
     */
    protected function sort(string $field, string $order)
    {
        $this->connection()->sort[] = [
            'value' => $field,
            'order' => $order,
        ];

        return $this;
    }

    /**
     * Add sort fields by DESC
     *
     *
     * @return $this
     */
    public function sortBy(string $field)
    {
        $this->sort($field, 'ASC');

        return $this;
    }

    /**
     * Add sort fields by DESC
     *
     *
     * @return $this
     */
    public function sortByDesc(string $field)
    {
        $this->sort($field, 'DESC');

        return $this;
    }

    /**
     * Alias for sortBy
     *
     *
     * @return $this
     */
    public function orderBy(string $field)
    {
        $this->sort($field, 'ASC');

        return $this;
    }

    /**
     * Alias for sortByDesc
     *
     *
     * @return $this
     */
    public function orderByDesc(string $field)
    {
        $this->sort($field, 'DESC');

        return $this;
    }

    /**
     * Shortcut for display method
     *
     * @param  string|array  $fields
     * @return $this
     */
    public function select($fields)
    {
        return $this->display($fields);
    }

    /**
     * Select fields to be returned by web service
     *
     * @param  array|string  $fields
     * @return $this
     */
    public function display($fields)
    {
        $this->connection()->display = is_array($fields) ? $fields : [$fields];

        return $this;
    }

    /**
     * Shortcut to filter method
     *
     * @param  array|string|null  $value
     * @return $this
     *
     * @throws Exception
     */
    public function where(string $field, string $operatorOrValue, $value = null)
    {
        $this->connection()->filter($field, $operatorOrValue, $value);

        return $this;
    }

    /**
     * Execute the get request and return first result
     *
     * @return Findable
     *
     * @throws CouldNotConnectException
     */
    public function first()
    {
        $get = $this->connection()->get($this->url());
        if (array_keys($get) == 0) {
            $response = $get[0];
        } else {
            $response = $get;
        }

        return new static($this->connection(), $response);
    }

    /**
     * Execute the get request with the condition applied
     *
     *
     * @throws CouldNotFindFilter
     * @throws CouldNotConnectException
     */
    public function find(int $id)
    {
        if ($this->connection()->filters) {
            throw new CouldNotFindFilter('You can not use find method along with filters');
        }

        $this->connection()->filters = [
            [
                'field' => 'id',
                'operator' => '=',
                'value' => $id,
            ],
        ];

        $r = $this->connection()->get($this->url());

        return new static($this->connection(), $r);
    }

    /**
     * Add a filter to query
     *
     * @throws Exception
     */
    public function filter(string $field, string $operatorOrValue, $value = null)
    {
        $this->connection()->filter($field, $operatorOrValue, $value);

        return $this;
    }

    /**
     * @throws CouldNotConnectException
     */
    public function getBlank()
    {
        $this->connection()->filters = [
            [
                'schema' => 'synopsis',
            ],
        ];
        $r = $this->connection()->get($this->url());
    }

    public function getResultSet(array $params = []): Resultset
    {
        return new Resultset($this->connection(), $this->url(), get_class($this), $params);
    }

    /**
     * get Resources
     *
     * @throws CouldNotConnectException
     */
    public function get(array $params = []): array
    {
        return iterator_to_array($this->getAsGenerator($params));
    }

    /**
     * @throws CouldNotConnectException
     */
    public function getAsGenerator(array $params = []): \Generator
    {
        $result = $this->connection()->get($this->url(), $params);

        return $this->collectionFromResultAsGenerator($result);
    }

    public function collectionFromResult($result, array $headers = []): array
    {
        return iterator_to_array(
            $this->collectionFromResultAsGenerator($result, $headers)
        );
    }

    public function collectionFromResultAsGenerator($result, array $headers = []): \Generator
    {
        // If we have one result which is not an assoc array, make it the first element of an array for the
        // collectionFromResult function so we always return a collection from filter
        if ((bool) count(array_filter(array_keys($result), 'is_string'))) {
            $result = [$result];
        }
        foreach ($result as $row) {
            yield new static($this->connection(), $row);
        }
    }
}
