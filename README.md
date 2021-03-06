# Grav Rest API Consumer

`Rest API Consumer` is a [Grav][grav] Plugin proof of concept which allows you to use a Rest API backend.

# Installation

Clone or download and enable the plugin.

# Usage

`Rest API Consumer` creates a **route** *issue*.

# Settings Defaults

    route: /issue
    project_nid: 2721905
    cache_ttl: 3600

# Quick test

```
wget https://github.com/getgrav/grav/releases/download/1.1.0-rc.1/grav-v1.1.0-rc.1.zip
unzip grav-v1.1.0-rc.1.zip
cd grav/user/plugins
wget https://github.com/attiks/poc-getgrav-rest-consumer/archive/master.zip
unzip master.zip
mv poc-getgrav-rest-consumer-master/ poc-getgrav-rest-consumer
cd ../..
php -S localhost:33333
open http://localhost:33333/issue
```
