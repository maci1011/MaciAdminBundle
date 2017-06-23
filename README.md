
MaciAdminBundle
===============


<img src="https://github.com/maci1011/MaciAdminBundle/raw/master/Resources/doc/images/maciadmin-promo.png" alt="Symfony Backends created with MaciAdmin" />


MaciAdminBundle lets you simply create administration backends for Symfony 2.7.* & 3.* applications.


### Warning: this is an alpha version under development.


### Missing:
 - tests
 - documentation with examples
 - some other things


### Features:
 - sections for differents roles
 - entities actions: list, show, new, edit, trash, remove
 - entities relations: add and remove


**Requirements**
----------------

  * SUPPORTS SYMFONY 2.7.x & 3.x
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
    prefix:   /mcm

# ...
```

### Step 4: Prepare the Web Assets of the Bundle

```cli
# Symfony 2.7
php app/console assets:install --symlink
# Symfony 3
php bin/console assets:install --symlink
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
                page:
                    class: 'MaciPageBundle:Page'
                    list: ['title', 'path', 'template', 'locale']
            config:
                roles: [ROLE_ADMIN]
```

**Congratulations! You've just created your first fully-featured backend!**
Browse the `/mcm` URL in your Symfony application and you'll get access to
the admin backend:

![Default MaciAdmin Backend interface](https://github.com/maci1011/MaciAdminBundle/raw/master/Resources/doc/images/maciadmin-promo.png)

Full configuration:

```yaml
# app/config/config.yml
maci_admin:
    sections:
        media:
            entities:
                album: 'MaciMediaBundle:Album'
                media:
                    label: Media
                    class: 'MaciPageBundle:Page'
                    list: ['_preview', 'name', 'type']
                    templates:
                        list: 'AppBundle:Default:list.html.twig'
                        # or: new, edit, show
                    form: 'AppBundle\Form\Type\FormType'
                    trash_attr: 'removed'
                    uploadable: true
                media_item:
                    class: 'MaciMediaBundle:Item'
                    bridges: 'media'
                    remove_in_relation: true
                    sort_attr: 'position'
            config:
                roles: [ROLE_ADMIN]
                dashboard: 'AppBundle:Default:media_dashboard.html.twig'
```

Keep reading the rest of the documentation to learn how to create complex backends (...coming soon).


License
-------

This software is published under the [MIT License](LICENSE.md)
