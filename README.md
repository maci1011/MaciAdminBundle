
MaciAdminBundle
===============

<sup>SUPPORTS SYMFONY 2.5.x</sup>

<img src="https://github.com/maci1011/MaciAdminBundle/raw/master/Resources/doc/images/maciadmin-promo.png" alt="Symfony Backends created with MaciAdmin" />

MaciAdminBundle lets you simply create administration backends for Symfony 2.5.* applications.

**Requirements**
----------------

  * Doctrine ORM entities (Doctrine ODM and Propel not supported).

> **❮ NOTE ❯** you are reading the documentation of the bundle's **development** version.

Installation
------------

### Step 1: Download the Bundle

```bash
$ composer require maci/admin-bundle
```

This command requires you to have Composer installed globally, as explained
in the [Composer documentation](https://getcomposer.org/doc/00-intro.md).

### Step 2: Enable the Bundle

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Maci\AdminBundle\MaciAdminBundle(),
        );
    }

    // ...
}
```

### Step 3: Load the Routes of the Bundle

```yaml
# app/config/routing.yml
maci_admin:
    resource: "@MaciAdminBundle/Resources/config/routing.yml"
    prefix:   /

# ...
```

### Step 4: Prepare the Web Assets of the Bundle

```cli
# Symfony 2
php app/console assets:install --symlink
```

That's it! Now everything is ready to create your first admin backend.

Your First Backend
------------------

Creating your first backend will take you less than 30 seconds. Let's suppose
that your Symfony application defines two Doctrine ORM entities called
`Product` and `Category`.

Open the `app/config/config.yml` file and add the following configuration:

```yaml
# app/config/config.yml
maci_admin:
    sections:
        all:
            entities:
                product: 'AppEntityBundle:Product'
                category: 'AppEntityBundle:Category'
            config:
                roles: [ROLE_SUPER_ADMIN]
```

**Congratulations! You've just created your first fully-featured backend!**
Browse the `/admin/mcm` URL in your Symfony application and you'll get access to
the admin backend:

![Default MaciAdmin Backend interface](https://github.com/maci1011/MaciAdminBundle/raw/master/Resources/doc/images/maciadmin-promo.png)

Keep reading the rest of the documentation to learn how to create complex backends (...coming soon).


License
-------

This software is published under the [MIT License](LICENSE.md)
