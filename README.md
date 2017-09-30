
MaciAdminBundle
===============


<img src="https://github.com/maci1011/MaciAdminBundle/raw/master/Resources/doc/images/maciadmin-promo.png" alt="Symfony Backends created with MaciAdmin" />


MaciAdminBundle lets you simply create administration backends for Symfony 2.7.* & 3.* applications.


### Warning: this is an alpha version under development.


### Missing:
 - tests
 - filters
 - documentation with examples
 - some other little thing


### Features:
 - sections for differents roles
 - entities actions: list, show, new, edit, trash, remove
 - entities relations: list, add and remove


**Requirements**
----------------

  * SUPPORTS SYMFONY 2.7.x & 3.x
  * Doctrine ORM entities (Doctrine ODM and Propel not supported).

> **❮ NOTE ❯** you are reading the documentation of the bundle's **development** version.


Installation
------------

### Step 1: Download the Bundle

```bash
$ composer require maci/admin-bundle:dev-master
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

### Step 4: Set the thumbnails size for the "list" pages (for entities with a preview)

```yaml
# Liip Configuration
liip_imagine:
    filter_sets:
        maci_admin_list_preview:
            quality: 80
            filters:
                thumbnail: { size: [90, 90], mode: inbound }
```

### Step 5: Prepare the Web Assets of the Bundle

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
                    class: 'AppBundle:Page'
                    list: ['title', 'path', 'template', 'locale']
            config: # optional
                roles: [ROLE_ADMIN] # default
                dashboard: 'AppBundle:Default:my_dashboard.html.twig' # optional
```

**Congratulations! You've just created your first fully-featured backend!**
Browse the `/mcm` URL in your Symfony application and you'll get access to
the admin backend:

![Default MaciAdmin Backend interface](https://github.com/maci1011/MaciAdminBundle/raw/master/Resources/doc/images/maciadmin-promo.png)


Full configuration
------------------

```yaml
# app/config/config.yml
maci_admin:
    sections:
        media:
            entities:
                album: 'AppBundle:Album'
                media:
                    label: Media
                    class: 'AppBundle:Media'
                    list: ['_preview', 'name', 'type']
                    templates:
                        list: 'AppBundle:Default:list.html.twig'
                        # or: show, new, edit, trash, remove
                    relations:
                        items:
                            enabled: true # by default
                    form: 'AppBundle\Form\Type\FormType'
                    trash_attr: 'removed'
                    uploadable: true
                # Relations of this example: Album >1toM< MediaItem >Mto1< Media
                media_item:
                    class: 'AppBundle:MediaItem'
                    bridges: 'media' # or ['media','foo'] allow to add directly media to, in this example, an album
                    remove_in_relation: false # in relations, remove the association, or, with true, delete the item
                    sort_attr: 'position' # allow to sort items in relations, here, the items of an album
            config:
                roles: [ROLE_ADMIN]
                dashboard: 'AppBundle:Default:media_dashboard.html.twig'
        blog:
            entities:
                post: 'AppBundle:Post'
                tag: 'AppBundle:Tag'
```


License
-------

This software is published under the [MIT License](LICENSE.md)


