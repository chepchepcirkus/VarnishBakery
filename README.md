# VarnishBakery plugin for CakePHP

[![Build Status](https://travis-ci.org/chepchepcirkus/VarnishBakery.svg?branch=master)](https://travis-ci.org/chepchepcirkus/VarnishBakery)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)](https://php.net/)

This plugin allow you to manage varnish tasks from cake shell.

## Features

* Manage your vcl
* You can use varnishadm command from cake shell.

## Installation

In your application you need to load the plugin.

config/bootstrap.php

```
Plugin::load('VarnishBakery', ['bootstrap' => true, 'routes' => true]);
```

You need to set your own configuration in the plugins/VarnishBakery/config/bootstrap.php file.

"vcl" configuration are used to generate vcl.

"varnish" configuration are used to communicate with varnish service.

## Usage

You can use it reach varnishadm command
```
cd bin/
./cake vcl_cook
--------------------------------------
Welcome to varnish bakery cooking Shell

help [command]
ping [timestamp]
auth response
quit
banner
status
start
stop
vcl.load <configname> <filename>
vcl.inline <configname> <quoted_VCLstring>
vcl.use <configname>
vcl.discard <configname>
vcl.list
param.show [-l] [<param>]
param.set <param> <value>
panic.show
panic.clear
storage.list
vcl.show <configname>
backend.list
backend.set_health matcher state
ban <field> <operator> <arg> [&& <field> <oper> <arg>]...
ban.list
```

or you can use varnish_bakery shell subcommand

``` 
./cake vcl_cook apply_vcl
```

To know all subcommand available
``` 
./cake vcl_cook -h
```

## Requirements

* Varnish version > 4.0

(c) 2017 https://github.com/chepchepcirkus  
License: [BSD-3-Clause](https://opensource.org/licenses/BSD-3-Clause)
