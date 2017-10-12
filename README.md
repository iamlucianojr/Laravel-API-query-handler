# [WIP] Laravel API query handler
This Laravel package helps to handle a query request properly 

## Synopsis

In order to help

## Code Example

Show what the library does as concisely as possible, developers should be able to figure out **how** your project solves their problem by looking at the code example. Make sure the API you are showing off is obvious, and that your code is short and concise.

## Motivation

A short description of the motivation behind the creation and maintenance of the project. This should explain **why** the project exists.

## Installation

Require this package with composer using the following command:

```bash
composer require luciano-jr/laravel-api-query-handler
```

After updating composer, add the service provider to the `providers` array in `config/app.php`

```php
Luciano-Jr\LaravelAPIQueryHandler\Services\ServiceProvider::class,
```

### Configuration File

**If you use Lumen please skip this step**

If you want to change the default parameters, run this command on the terminal in order to publish the vendor config file:

`php artisan vendor:publish --provider="Luciano-Jr\LaravelApiQueryHandler\Services\ServiceProvider"`


## Usage

```public function index(Request $request)
    {
        $collection = $this->repository->all();
        
        $collectionHandler = new CollectionHandler($collection, $request);
        $collectionHandler->handle();
    }
```

## Tests

Describe and show how to run the tests with code examples.

## Contributors

Let people know how they can dive into the project, include important links to things like issue trackers, irc, twitter accounts if applicable.

## License

The Laravel API query handler is open-sourced software licensed under the MIT license.
