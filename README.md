[![Build Status](https://travis-ci.org/jeslopcru/VarnishAdmin.svg?branch=master)](https://travis-ci.org/jeslopcru/VarnishAdmin)
# VarnishAdmin PHP Library

VarnishAdmin is a PHP Library for manage Varnish reverse proxy cache commands using PHP.

Based on [timwhitlock/php-varnish](https://github.com/timwhitlock/php-varnish)

# Pull Request
Pull request are welcome using PSR-2

# Requirements

VarnishAdmin is supported on PHP 5.5.* and up.

#Example

## Varnish 4
```php
  $varnish = new VarnishAdminSocket('192.168.10.10', 6082, '4.0.3');
  $varnish->purgeUrl('example.com');
  $varnish->quit();
```

## Varnish 3
```php
  //purge postId  (id = 354)
  //www.example.com?id=354
  $varnish = new VarnishAdminSocket();
  $varnish->purgeUrl('id=354');
  $varnish->quit();
```

# License

The whole VarnishAdmin package, is released under the MIT license, see LICENSE.

