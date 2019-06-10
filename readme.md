## About

A distributed crawler

## Requirements

* [PHP >= 7.1](http://php.net)

## Installation

Via [Composer](https://getcomposer.org):

```bash
composer require teamtnt/crawler
```

## Configuration

Each instance needs to have an identifier. This can be added in `.env`
```php
NODE_NAME="Instance 1"
```

The domain feeder needs to start with a seed domain. After that, running 

`php artisan crawler`

For scraping a single url

`php artisan url:frontier www.example.com/something`

### Crawler Topology

![Crawler Topology](https://i.imgur.com/MlC5Dtq.png)

### Domain Feeder

![Domain Feeder](https://i.imgur.com/VXLH0pG.png)

### Single Instance

![Single Instance](https://i.imgur.com/G1N7Z0W.png)

### URL Frontier

![URL Frontier](https://i.imgur.com/i3CrXfx.png)



