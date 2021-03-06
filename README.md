OneSky library for PHP
======================

## Requirements

- PHP 5.3 or higher
- CURL extension

## Installation

In your `composer.json`, require `amyboyd/onesky-api-client`.

## How to use

**Create instance**

    $client = new \OneSky\ApiClient('<api-key>', '<api-secret>');

**Way to make request**

    // resource   => name of resource in camelcase with 's' tailing such as 'projectTypes', 'quotations', 'importTasks'
    // action     => action to take such as 'list', 'show', 'upload', 'export'
    // parameters => parameters passed in the request including URL param such as 'project_id', 'files', 'locale'
    $client->{resource}('{action}', array({parameters}));

**Sample request and get response in array**

    // list project groups
    $response = $client->projectTypes('list');
    $response = json_decode($response, true);

    // show a project
    $response = $client->projects('show', array('project_id' => 999));
    $response = json_decode($response, true);

    // upload file
    $response = $client->files('upload', array(
        'project_id'  => 999,
        'file'        => 'path/to/string.yml',
        'file_format' => 'YAML',
        'locale'      => 'fr'
    ));
    $response = json_decode($response, true);

    // create order
    $response = $client->orders('create', array(
        'project_id' => 999,
        'files'      => 'string.yml',
        'to_locale'  => 'de'
    ));
    $response = json_decode($response, true);

## TODO

- Test with PHPUnit
- Implement missing resources according to [Onesky API document](https://github.com/onesky/api-documentation-platform)
