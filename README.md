
MaciAdminBundle
===============


<img src="https://github.com/maci1011/MaciAdminBundle/raw/master/Resources/doc/images/maciadmin-promo.png" alt="Symfony Backends created with MaciAdmin" />


MaciAdminBundle lets you simply create administration backends for Symfony 2.7.* & 3.* applications.


### Warning: this is an alpha version under development.


### Missing:
 - tests
 - filters (but there is the search)
 - more examples in documentation
 - some other little thing


### Features:
 - sections for differents roles
 - entities actions: list, new, trash, show, edit, relations, remove, uploader and reorder
 - entities relations: list, set/add, remove (item/association), uploader and reorder


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
        medias:
            entities:
                # in this example an entity 'media' is associated to an 'album' trough a 'media item'
                # then: Album >1toM< MediaItem >Mto1< Media
                # 'media', 'album' and 'media item' are here in a section named 'medias'
                album: 'AppBundle:Album'
                media:
                    bridges: [] # default
                    class: 'AppBundle:Media'
                    form: 'AppBundle\Form\Type\FormType'
                    label: 'Image' #example, default is the name of the section capitalized
                    list: ['_preview', 'name', 'type'] # columns in list views, default is [] (= all fields)
                    relations:
                        items:
                            # these options are inherited from the -section- config:
                            label: 'Image Items' #example
                            list: []
                            enabled: true
                            roles: []
                            sortable: false # if true, in this example allow to sort the 'media items' of an 'album'
                            sort_field: 'position'
                            actions: []
                            trash: true
                            trash_field: 'removed'
                            uploadable: true
                            upload_field: 'file'
                    # these options are inherited from the -section- config:
                    enabled: true
                    roles: []
                    sortable: false
                    sort_field: 'position'
                    actions: # default is []
                        list: 'AppBundle:Default:list.html.twig'
                        show:
                            template: 'AppBundle:Default:list.html.twig'
                        # actions are: list, show, new, trash, show, edit, relation, remove, uploader,
                        #   relations_list, relations_add, ('list' and 'add' for the sides of relations with multiple elements, like -MANY-toOne)
                        #   relations_show, relations_set ('list' and 'set' for the sides of relations with a single element, like -ONE-toMany),
                        #   relations_uploader (in this example this action can be used to directly upload some media in an album)
                    trash: true
                    trash_field: 'removed'
                    uploadable: true
                    upload_field: 'file'
                media_item:
                    class: 'AppBundle:MediaItem'
                    bridges: 'media' # or ['media', ...] in this example allow to add directly media to an album
            config:
                dashboard: 'AppBundle:Default:media_dashboard.html.twig' # optional
                # these options are inherited from the default inherited config:
                enabled: true
                roles: [ROLE_ADMIN]
                sortable: false
                sort_field: 'position'
                actions: []
                trash: true
                trash_field: 'removed'
                uploadable: true
                upload_field: 'file'
        # other optional sections
        blog:
            entities:
                post: 'AppBundle:Post'
                tag: 'AppBundle:Tag'
    config:
        # default inherited config:
        controller: 'maci.admin.controller' # the service controller that contain the Action functions
        enabled: true
        roles: [ROLE_ADMIN]
        sortable: false # if true, allow to sort items in the 'list' action, usually this is needed only in relations
        sort_field: 'position' # sort is made by field 'position'
        actions: []
        trash: true # allow to trash items of an entity
        trash_field: 'removed' # trash folder is filtered by field 'removed'
        uploadable: true
        upload_field: 'file'
```


License
-------

This software is published under the [MIT License](LICENSE.md)


