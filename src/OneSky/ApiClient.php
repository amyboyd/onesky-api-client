<?php

namespace OneSky;

use BadMethodCallException;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * OneSky API client, for the Platform API version 1.
 */
class ApiClient
{
    /**
     * The OneSky API endpoint.
     */
    const ENDPOINT = 'https://platform.api.onesky.io/1';

    /**
     * API authentiction key.
     */
    protected $apiKey;

    /**
     * API authentiction secret.
     */
    protected $secret;

    /**
     * Resources with actions
     */
    protected $resources = array(
        'project_groups' => array(
            'list'              => 'GET /project-groups',
            'show'              => 'GET /project-groups/:project_group_id',
            'create'            => 'POST /project-groups',
            'delete'            => 'DELETE /project-groups/:project_group_id',
            'languages'         => 'GET /project-groups/:project_group_id/languages',
        ),
        'projects' => array(
            'list'              => 'GET /project-groups/:project_group_id/projects',
            'show'              => 'GET /projects/:project_id',
            'create'            => 'POST /project-groups/:project_group_id/projects',
            'update'            => 'PUT /projects/:project_id',
            'delete'            => 'DELETE /projects/:project_id',
            'languages'         => 'GET /projects/:project_id/languages',
        ),
        'files' => array(
            'list'              => 'GET /projects/:project_id/files',
            'upload'            => 'POST /projects/:project_id/files',
            'delete'            => 'DELETE /projects/:project_id/files',
        ),
        'translations' => array(
            'export'            => 'GET /projects/:project_id/translations',
            'status'            => 'GET /projects/:project_id/translations/status',
        ),
        'import_tasks' => array(
            'show'              => 'GET /projects/:project_id/import-tasks/:import_id'
        ),
        'quotations' => array(
            'show'              => 'GET /projects/:project_id/quotations'
        ),
        'orders' => array(
            'list'              => 'GET /projects/:project_id/orders',
            'show'              => 'GET /projects/:project_id/orders/:order_id',
            'create'            => 'POST /projects/:project_id/orders'
        ),
        'locales' => array(
            'list'              => 'GET /locales'
        ),
        'project_types' => array(
            'list'              => 'GET /project-types'
        ),
        // See https://github.com/onesky/api-documentation-platform/blob/master/resources/phrase_collection.md
        'phrase_collections' => array(
            'list'              => 'GET /projects/:project_id/phrase-collections',
            'show'              => 'GET /projects/:project_id/phrase-collections/show',
            // For the import format, see https://github.com/onesky/api-documentation-platform/blob/master/reference/phrase_collection_format.md
            'import'            => 'POST /projects/:project_id/phrase-collections',
            'delete'            => 'DELETE /projects/:project_id/phrase-collections',
        ),
    );

    /**
     * Actions to use multipart to upload file
     */
    protected $multiPartActions = array(
        'files' => array('upload'),
    );

    /**
     * Actions to use multipart to upload file
     */
    protected $exportFileActions = array(
        'translations' => array('export'),
    );

    /**
     * Default curl settings
     */
    protected $curlSettings = array(
        CURLOPT_RETURNTRANSFER => true,
    );

    public function __construct($apiKey, $secret)
    {
        $this->setApiKey($apiKey);
        $this->setSecret($secret);
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function setSecret($secret)
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * Retrieve resources
     * @return array
     */
    public function getResources()
    {
        return array_keys($this->resources);
    }

    /**
     * Retrieve actions of a resource
     * @param  string $resource Resource name
     * @return array|null
     */
    public function getActionsByResource($resource)
    {
        if (!isset($this->resources[$resource])) {
            return null; // no resource found
        }

        $actions = array();
        foreach ($this->resources[$resource] as $action => $path) {
            $actions[] = $action;
        }

        return $actions;
    }

    /**
     * Determine if using mulit-part to upload file
     * @param  string  $resource Resource name
     * @param  string  $action   Action name
     * @return boolean
     */
    public function isMultiPartAction($resource, $action)
    {
        return isset($this->multiPartActions[$resource]) && in_array($action, $this->multiPartActions[$resource]);
    }

    /**
     * Determine if it is to export (download) file
     * @param  string  $resource Resource name
     * @param  string  $action   Action name
     * @return boolean
     */
    public function isExportFileAction($resource, $action)
    {
        return isset($this->exportFileActions[$resource]) && in_array($action, $this->exportFileActions[$resource]);
    }

    /**
     * For developers to initial request to Onesky API
     *
     * Example:
     *     $onesky = new Onesky_Api();
     *     $onesky->setApiKey('<api-key>')->setSecret('<api-secret>');
     *
     *     // To list project type
     *     $onesky->projectTypes('list');
     *
     *     // To create project
     *     $onesky->projects('create', array('project_group_id' => 999));
     *
     *     // To upload string file
     *     $onesky->files('upload', array('project_id' => 1099, 'file' => 'path/to/file.yml', 'file_format' => 'YAML'));
     *
     * @param  string $fn_name Function name acted as resource name
     * @param  array  $params  Parameters passed in request
     * @return array  Response
     */
    public function __call($fn_name, $params)
    {
        // is valid resource
        $resource = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $fn_name)); // camelcase to underscore
        if (!in_array($resource, $this->getResources())) {
            throw new BadMethodCallException('Invalid resource');
        }

        // is valid action
        $action = array_shift($params); // action name
        if (!in_array($action, $this->getActionsByResource($resource))) {
            throw new InvalidArgumentException('Invalid resource action');
        }

        $params = count($params) > 0 ? array_shift($params) : array(); // parameters

        list($method, $path) = $this->getRequestMethodAndPath($resource, $action, $params);

        // is multi-part
        $isMultiPart = $this->isMultiPartAction($resource, $action);

        // return response
        return $this->callApi($method, $path, $params, $isMultiPart);
    }

    /**
     * Retrieve request path and replace variables with values
     * @param  string $resource Resource name
     * @param  string $action   Action name
     * @param  array  $params   Parameters
     * @return [string,string] The request method and path.
     */
    private function getRequestMethodAndPath($resource, $action, &$params)
    {
        if (!isset($this->resources[$resource]) || !isset($this->resources[$resource][$action])) {
            throw new UnexpectedValueException('Resource path not found');
        }

        // get path
        $path = $this->resources[$resource][$action];

        // replace variables
        $matchCount = preg_match_all("/:(\w*)/", $path, $variables);
        if ($matchCount) {
            foreach ($variables[0] as $index => $placeholder) {
                if (!isset($params[$variables[1][$index]])) {
                    throw new InvalidArgumentException('Missing parameter: ' . $variables[1][$index]);
                }

                $path = str_replace($placeholder, $params[$variables[1][$index]], $path);
                unset($params[$variables[1][$index]]); // remove parameter from $params
            }
        }

        return explode(' ', $path, 2);
    }

    protected function verifyTokenAndSecret()
    {
        if (empty($this->apiKey) || empty($this->secret)) {
            throw new UnexpectedValueException('Invalid authenticate data of api key or secret');
        }
    }

    /**
     * Initial request to Onesky API
     * @param  string  $method
     * @param  string  $path
     * @param  array   $params
     * @param  boolean $isMultiPart
     * @param  boolean $isExportFile
     * @return array
     */
    private function callApi($method, $path, $params, $isMultiPart)
    {
        // init session
        $ch = curl_init();

        // request settings
        curl_setopt_array($ch, $this->curlSettings); // basic settings

        // url
        $url = self::ENDPOINT . $path;
        if ($method === 'GET') {
            $url .= $this->getAuthQueryStringWithParams($params);
        }
        else {
            $url .= $this->getAuthQueryString();
        }
        curl_setopt($ch, CURLOPT_URL, $url);

        // http header
        if (!$isMultiPart) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        }

        // method specific settings
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);

                // requst body
                if ($isMultiPart) {
                    $params['file'] = '@' . $params['file'];
                    $postBody = $params;
                } else {
                    $postBody = json_encode($params);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);

                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

                break;
        }

        // execute request
        $response = curl_exec($ch);

        // error handling
        if ($response === false) {
            throw new UnexpectedValueException(curl_error($ch));
        }

        // close connection
        curl_close($ch);

        // return response
        return $response;
    }

    private function getAuthQueryStringWithParams($params)
    {
        $queryString = $this->getAuthQueryString();

        if (count($params) > 0) {
            $queryString .= '&' . http_build_query($params);
        }

        return $queryString;
    }

    private function getAuthQueryString()
    {
        $this->verifyTokenAndSecret();

        $timestamp = time();
        $devHash = md5($timestamp . $this->secret);

        $queryString  = '?api_key=' . $this->apiKey;
        $queryString .= '&timestamp=' . $timestamp;
        $queryString .= '&dev_hash=' . $devHash;

        return $queryString;
    }
}
