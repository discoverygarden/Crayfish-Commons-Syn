
# Crayfish Commons Syn Integration

## Introduction

Syn parsing removed with Crayfish-Commons 4.x, (to be) re-implemented on top of Lexik JWT.

## Requirements

* PHP 7.2+
* [Composer](https://getcomposer.org/)

## Installation

Can be installed with Composer with something like:

```
composer require discoverygarden/crayfish-commons-syn
```

## Configuration

Into your main `config/packages/security.yaml` (or equivalent):

* Ensure/set `security.enable_authenticator_manager` to `true`
* Ensure a user provider exists:
    ```yaml
    security:
      [...]
      providers:
        users_in_memory:
          memory: ~
    ```

* Have the main firewall use the user provider, and reference our custom authenticator:
    ```yaml
    security:
      [...]
      firewalls:
        main:
          anonymous: false
          provider: users_in_memory
          custom_authenticators:
            - islandora_crayfish_commons_syn.jwt.authenticator
    ```

## Documentation

Further documentation for this module is available on the [Islandora 8 documentation site](https://islandora.github.io/documentation/).


## Troubleshooting/Issues

Having problems or solved a problem? Check out the Islandora google groups for a solution.

* [Islandora Group](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora)
* [Islandora Dev Group](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora-dev)

## Maintainers

* [Eli Zoller](https://github.com/elizoller)

This project has been sponsored by:
* UPEI
* discoverygarden inc.
* LYRASIS
* McMaster University
* University of Limerick
* York University
* University of Manitoba
* Simon Fraser University
* PALS
* American Philosophical Society
* common media inc.

## Development

If you would like to contribute, please get involved by attending our weekly [Tech Call](https://github.com/Islandora/documentation/wiki). We love to hear from you!

If you would like to contribute code to the project, you need to be covered by an Islandora Foundation [Contributor License Agreement](http://islandora.ca/sites/default/files/islandora_cla.pdf) or [Corporate Contributor License Agreement](http://islandora.ca/sites/default/files/islandora_ccla.pdf). Please see the [Contributors](http://islandora.ca/resources/contributors) pages on Islandora.ca for more information.

We recommend using the [islandora-playbook](https://github.com/Islandora-Devops/islandora-playbook) to get started. If you want to pull down the submodules for development, don't forget to run `git submodule update --init --recursive` after cloning.

## License

[MIT](./LICENSE)


