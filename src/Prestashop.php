<?php

namespace LucasGiovanny\LaravelPrestashop;

use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use LucasGiovanny\LaravelPrestashop\Exceptions\ConfigException;
use LucasGiovanny\LaravelPrestashop\Exceptions\CouldNotConnectException;
use LucasGiovanny\LaravelPrestashop\Exceptions\CouldNotFindFilter;
use LucasGiovanny\LaravelPrestashop\Exceptions\CouldNotFindResource;
use LucasGiovanny\LaravelPrestashop\Exceptions\PrestashopWebserviceException;
use LucasGiovanny\LaravelPrestashop\Resources\Model;

class Prestashop
{
    /**
     * All available resources on Prestashop web service
     */
    public const RESOURCES = [
        'addresses',
        'carriers',
        'cart_rules',
        'carts',
        'categories',
        'combinations',
        'configurations',
        'contacts',
        'content_management_system',
        'countries',
        'currencies',
        'customer_messages',
        'customer_threads',
        'customers',
        'customizations',
        'deliveries',
        'employees',
        'groups',
        'guests',
        'image_types',
        'images',
        'languages',
        'manufacturers',
        'messages',
        'order_carriers',
        'order_details',
        'order_histories',
        'order_invoices',
        'order_payments',
        'order_slip',
        'order_states',
        'orders',
        'price_ranges',
        'product_customization_fields',
        'product_feature_values',
        'product_features',
        'product_option_values',
        'product_options',
        'product_suppliers',
        'products',
        'search',
        'shop_groups',
        'shop_urls',
        'shops',
        'specific_price_rules',
        'specific_prices',
        'states',
        'stock_availables',
        'stock_movement_reasons',
        'stock_movements',
        'stocks',
        'stores',
        'suppliers',
        'supply_order_details',
        'supply_order_histories',
        'supply_order_receipt_histories',
        'supply_order_states',
        'supply_orders',
        'tags',
        'tax_rule_groups',
        'tax_rules',
        'taxes',
        'translated_configurations',
        'warehouse_product_locations',
        'warehouses',
        'weight_ranges',
        'zones',
    ];

    /**
     * All allowed filters
     */
    public const FILTER_OPERATORS = [
        '|',
        ',',
        '=',
        'OR',
        'INTERVAL',
        'LITERAL',
        'BEGIN',
        'END',
        'CONTAINS',
        'INNER',
    ];

    /**
     * Resource that will be called
     *
     * @var string
     */
    protected ?string $resource = null;

    /**
     * Field from the resource to be added to the request
     */
    protected array $display = [];

    /**
     * Filters to be added to the request
     */
    public array $filters = [];

    /**
     * Define the limit for the request
     *
     * @var array|int
     */
    protected $limit;

    /**
     * Define the sort fields for the request
     */
    protected array $sort = [];

    /**
     * On-demand endpoint definition
     */
    protected string $endpoint = '/api';

    protected ?string $shop_url = null;

    /**
     * On-demand token definition
     *
     * @var string
     */
    public ?string $token = null;

    /**
     * Shop ID
     *
     * @var string
     */
    public ?string $shop = null;

    /**
     * Headers for request
     */
    protected array $headers = [
        'Io-Format' => 'JSON',
        'Output-Format' => 'JSON',
    ];

    protected HttpClient $http;

    private ?string $method;

    /**
     * Construct the class with dependencies
     *
     * @param  HttpClient  $http
     * @return void
     */
    public function __construct(HttpClient $http = null)
    {
        if ($http) {
            $this->http = $http;
        } else {
            $this->http = new HttpClient();
        }
    }

    /**
     * Configure the Prestashop store
     *
     * @param  int  $shop
     * @return $this
     */
    public function shop(string $shop_url, string $endpoint, string $token, int $shop = null): Prestashop
    {
        $this->shop_url = $shop_url;
        $this->endpoint = $endpoint;
        $this->token = $token;
        $this->shop = $shop;

        return $this;
    }

    /**
     * Configure the Prestashop store
     *
     * @param  int  $shop
     * @return $this
     */
    public function store(string $shop_url, string $endpoint, string $token, int $shop = null): Prestashop
    {
        $this->shop($shop_url, $endpoint, $token, $shop);

        return $this;
    }

    /**
     * Set the resource to be used
     *
     * @param  mixed  ...$arguments
     * @return $this
     */
    public function resource(string $resource, ...$arguments): Prestashop
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Define the request limit or index and limit
     *
     * @param  int  $index
     * @return $this
     */
    public function limit(int $limit, int $index = null): Prestashop
    {
        $this->limit = $index ? [$index, $limit] : $limit;

        return $this;
    }

    /**
     * Execute the get request
     *
     * @throws CouldNotConnectException
     */
    public function get($url = null): array
    {
        try {
            return $this->call('get', $url);
        } catch (Exception|GuzzleException $e) {
            throw new CouldNotConnectException($e->getMessage());
        }
    }

    /**
     * Handle post request
     *
     * @throws CouldNotConnectException
     */
    public function post($url, $body)
    {
        try {
            $this->filters = [];

            return $this->call('post', $url, $body);
        } catch (CouldNotConnectException|ConfigException|GuzzleException $e) {
            throw new CouldNotConnectException($e->getMessage());
        }
    }

    /**
     * @throws CouldNotConnectException
     */
    public function put($url, $body)
    {
        try {
            return $this->call('put', $url, $body);
        } catch (Exception|GuzzleException $e) {
            throw new CouldNotConnectException($e->getMessage());
        }
    }

    /**
     * @throws CouldNotConnectException
     */
    public function destroy($url, $id)
    {
        try {
            return $this->call('delete', $url, ['id' => $id]);
        } catch (GuzzleException|ConfigException|CouldNotConnectException|PrestashopWebserviceException $e) {
            throw new PrestashopWebserviceException($e->getMessage());
        }
    }

    /**
     * Internal method to make the correct request call
     *
     *
     * @throws ConfigException
     * @throws CouldNotConnectException
     * @throws GuzzleException
     * @throws PrestashopWebserviceException
     */
    protected function call(string $method, string $url = null, mixed $body = null): array
    {
        $this->method = in_array($method, ['get', 'post', 'put', 'delete']) ? $method : null;

        if ($this->canExecute()) {
            $result = $this->exec($url, $body);

            return $this->response($result);
        }
        throw new CouldNotConnectException('Error occur when trying to execute the API call');
    }

    /**
     * Check if the request can be executed
     *
     *
     * @throws ConfigException|CouldNotConnectException
     */
    protected function canExecute(): bool
    {
        if (! $this->method) {
            throw new ConfigException('You need to define a method.');
        }

        if (! $this->shopUrl()) {
            throw new ConfigException('No endpoint/ URL defined.');
        }

        if (! $this->token()) {
            throw new ConfigException('Token is not configured');
        }

        return true;
    }

    /**
     * Execute the request to Prestashop web service
     *
     * @param  null  $url
     * @return array
     *
     * @throws GuzzleException
     * @throws PrestashopWebserviceException
     * @throws Exception
     */
    protected function exec($url = null, mixed $body = null)
    {
        if (isset($url)) {
            $url = $this->formatUrl($url);
        } elseif (isset($this->resource)) {
            $url = trim($this->shopUrl(), '/').'/'.trim($this->resource, '/');
        }

        if ($this->method == 'post') {
            $headers = [
                'Content-Type' => 'text/xml; charset=UTF8',
                'Io-Format' => 'JSON',
                'Output-Format' => 'JSON',
            ];
        } else {
            $headers = $this->headers;
        }
        if ($this->method == 'delete') {
            $query = ['id' => '['.$body['id'].']'];
            $body = null; //reset body
        } else {
            $query = $this->query();
        }

        try {
            $res = $this->http->request(
                strtoupper($this->method),
                $url,
                [
                    RequestOptions::ALLOW_REDIRECTS,
                    RequestOptions::AUTH => [$this->token(), null],
                    RequestOptions::HEADERS => $headers,
                    RequestOptions::QUERY => $query,
                    'body' => $body,
                ],
            );

            return $res->getBody() ? json_decode($res->getBody(), true) : null;
        } catch (ServerException|ClientException  $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            throw new PrestashopWebserviceException($responseBodyAsString, $e->getCode());
        } catch (Exception $e) {
            throw new Exception('A global error which is undefined ?');
        }
    }

    public function innerFilter($filter)
    {
    }

    /**
     * Add a filter to the web service call
     *
     * @param  string|array  $value
     * @return $this
     *
     * @throws Exception
     */
    public function filter(string $field, string $operatorOrValue, $value = null): Prestashop
    {
        $operator = $value ? $operatorOrValue : '=';

        if (! in_array(strtoupper($operator), Prestashop::FILTER_OPERATORS)) {
            throw new CouldNotFindFilter('Invalid filter operator');
        }

        $this->filters[] = [
            'field' => $field,
            'operator' => strtoupper($operator),
            'value' => $value ?: $operatorOrValue,
        ];

        return $this;
    }

    /**
     * Prepare query for request
     */
    protected function query(): array
    {
        $query = [];

        $query = [
            'display' => $this->display ?
                '['.implode(',', $this->display).']' : 'full',
        ];

        if ($this->limit) {
            $query['limit'] = is_array($this->limit) ?
                "{$this->limit[0]}, {$this->limit[1]}"
                : $this->limit;
        }

        if ($this->filters) {
            foreach ($this->filters as $filter) {
                if (isset($filter['operator'])) {
                    $value = null;
                    if ($filter['operator'] === '|' || $filter['operator'] === 'OR') {
                        $value = '['.implode('|', $filter['value']).']';
                    }

                    if ($filter['operator'] === ',' || $filter['operator'] === 'INTERVAL') {
                        $value = '['.implode(',', $filter['value']).']';
                    }

                    if ($filter['operator'] === '=' || $filter['operator'] === 'LITERAL') {
                        $value = '['.$filter['value'].']';
                    }

                    if ($filter['operator'] === 'BEGIN') {
                        $value = '['.$filter['value'].']%';
                    }

                    if ($filter['operator'] === 'END') {
                        $value = '%['.$filter['value'].']';
                    }

                    if ($filter['operator'] === 'CONTAINS') {
                        $value = '%['.$filter['value'].']%';
                    }

                    if ($filter['operator'] === 'INNER') {
                        $query[$filter['field']] = $filter['value'];
                    }
                    if (isset($value)) {
                        $query['filter['.$filter['field'].']'] = $value;
                    }
                }

                if (isset($filter['schema'])) {
                    $query = []; // clear because we wanted only a blank schema!
                    $query['schema'] = $filter['schema'];
                }
                if (isset($filter['field'])) {
                    if (Str::contains($filter['field'], 'date')) {
                        $query['date'] = 1;
                    }
                }
            }
        }

        if ($this->sort) {
            $sortQuery = [];
            foreach ($this->sort as $sort) {
                $sortQuery[] = "{$sort['value']}_{$sort['order']}";
            }

            $query['sort'] = '['.implode(',', $sortQuery).']';
        }

        if ($this->shop) {
            $query['id_shop'] = $this->shop;
        }

        return $query;
    }

    /**
     * Define the endpoint for the request
     */
    protected function token(): string
    {
        return $this->token ?: config('prestashop.shop.token');
    }

    /**
     * Define the endpoint for the request
     *
     * @return string
     */
    protected function shopUrl(): ?string
    {
        return $this->shop_url != null ? $this->shop_url : config('prestashop.shop.shop_url');
    }

    private function getApiUrl(): string
    {
        return $this->shopUrl().$this->endpoint;
    }

    private function formatUrl($endpoint): string
    {
        return implode('/', [
            $this->getApiUrl(),
            $endpoint,
        ]);
    }

    /**
     * Handle basic response
     *
     * @param  array  $response
     *
     * @throws CouldNotConnectException
     */
    protected function response(?array $response): array
    {
        $response = $response[$this->resource] ?? $response;

        if (count($response) >= 2) {
            return $response;
        }
        if (array_filter(array_keys($response), 'is_string')) {
            $firstKey = array_key_first($response);

            return $response[$firstKey];
        }
        if (count($response) == 1) {
            return $response[0];
        }

        return $response;
    }

    /**
     * Create the method for each web service resource
     *
     *
     * @return mixed
     *
     * @throws CouldNotFindResource
     */
    public function __call(string $method, array $arguments)
    {
        $method = strtolower($method);
        if (in_array(strtolower($method), self::RESOURCES)) {
            //@todo return Model instance
            $this->resource = $method;

            $class = "\LucasGiovanny\LaravelPrestashop\Resources\\".ucfirst($method);

            return new $class($this, $arguments);
        }
        throw new CouldNotFindResource();
    }
}
